<?php

namespace App\Services\PuntoVenta\Resguardos;

use App\Events\PuntoVenta\RecepcionEsperadaPdvCreada;
use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvEvento;
use App\Services\ControlPedidos\ValidarSucursalDestinoPedidoBma;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CrearRecepcionEsperadaPdvService
{
    public const HANDOFF = 'envio';

    public function __construct(
        private ValidarSucursalDestinoPedidoBma $validarDestino,
    ) {}

    public static function claveIdempotencia(int $pedidoBmaId, int $sucursalId): string
    {
        return 'pdv:esp:'.$pedidoBmaId.':'.$sucursalId.':'.self::HANDOFF;
    }

    public function ejecutar(PedidoBma $pedido, ?int $actorId = null): ResguardoPdv
    {
        return DB::transaction(function () use ($pedido, $actorId) {
            $pedido = PedidoBma::query()
                ->with(['origen', 'cliente', 'estatus', 'sucursalDestino', 'cajas', 'tareaPreparacionVigente.modalidad'])
                ->lockForUpdate()
                ->findOrFail($pedido->id);

            $this->assertElegible($pedido);

            $sucursalId = (int) $pedido->sucursal_destino_id;

            $existente = ResguardoPdv::query()
                ->where('pedido_bma_id', $pedido->id)
                ->where('sucursal_id', $sucursalId)
                ->lockForUpdate()
                ->first();

            if ($existente) {
                return $existente;
            }

            try {
                return $this->crear($pedido, $sucursalId, $actorId);
            } catch (UniqueConstraintViolationException $e) {
                $recuperado = ResguardoPdv::query()
                    ->where('pedido_bma_id', $pedido->id)
                    ->where('sucursal_id', $sucursalId)
                    ->first();

                if ($recuperado) {
                    return $recuperado;
                }

                throw $e;
            }
        });
    }

    private function assertElegible(PedidoBma $pedido): void
    {
        if (! $pedido->requiereSucursalDestino()) {
            throw ValidationException::withMessages([
                'modalidad' => 'Este pedido no tiene una modalidad compatible con recepción en sucursal.',
            ]);
        }

        $this->validarDestino->ejecutar(
            $pedido,
            $pedido->sucursal_destino_id !== null ? (int) $pedido->sucursal_destino_id : null,
            $pedido->codigoModalidadPreparacionVigente(),
            true
        );

        if ($pedido->estatus?->fase_ciclo !== CatalogoEstatusPedido::FASE_ENVIADO) {
            throw ValidationException::withMessages([
                'estado' => 'El pedido aún no está en tránsito hacia sucursal.',
            ]);
        }
    }

    private function crear(PedidoBma $pedido, int $sucursalId, ?int $actorId): ResguardoPdv
    {
        $clave = self::claveIdempotencia((int) $pedido->id, $sucursalId);
        $ahora = now();
        $bultos = $this->cantidadBultosEsperada($pedido);

        $resguardo = ResguardoPdv::query()->create([
            'pedido_bma_id' => $pedido->id,
            'cliente_id' => $pedido->cliente_id,
            'sucursal_id' => $sucursalId,
            'almacen_id' => $pedido->almacen_id,
            'estado' => ResguardoPdv::ESTADO_PENDIENTE_RECEPCION,
            'cantidad_bultos_esperada' => $bultos,
            'salida_cedis_at' => $ahora,
            'snapshot_folio' => $pedido->folio_remision ?: $pedido->folio,
            'snapshot_cliente_nombre' => $pedido->cliente?->nombre,
            'snapshot_json' => [
                'pedido_bma_id' => (int) $pedido->id,
                'folio' => $pedido->folio,
                'folio_remision' => $pedido->folio_remision,
                'sucursal_id' => $sucursalId,
                'handoff' => self::HANDOFF,
            ],
            'version' => 1,
        ]);

        ResguardoPdvEvento::query()->create([
            'resguardo_id' => $resguardo->id,
            'tipo_evento' => ResguardoPdvEvento::TIPO_RECEPCION_ESPERADA_CREADA,
            'estado_anterior' => null,
            'estado_nuevo' => ResguardoPdv::ESTADO_PENDIENTE_RECEPCION,
            'actor_id' => $actorId,
            'ocurrido_at' => $ahora,
            'snapshot_json' => [
                'pedido_bma_id' => (int) $pedido->id,
                'sucursal_id' => $sucursalId,
                'handoff' => self::HANDOFF,
            ],
            'idempotency_key' => $clave,
        ]);

        RecepcionEsperadaPdvCreada::dispatch($resguardo, (int) $pedido->id, $sucursalId);

        return $resguardo;
    }

    private function cantidadBultosEsperada(PedidoBma $pedido): int
    {
        $activas = $pedido->cajas
            ->filter(fn ($caja) => method_exists($caja, 'estaActiva') ? $caja->estaActiva() : true)
            ->count();

        if ($activas > 0) {
            return $activas;
        }

        $numero = (int) ($pedido->numero_cajas ?? 0);

        return $numero > 0 ? $numero : 0;
    }
}
