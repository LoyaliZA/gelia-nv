<?php

namespace App\Services\PuntoVenta\Resguardos;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvBulto;
use App\Models\PuntoVenta\ResguardoPdvEvento;
use App\Models\PuntoVenta\ResguardoPdvIncidencia;
use App\Models\User;
use App\Services\ControlPedidos\NotificarPedidoBmaService;
use App\Services\ControlPedidos\RegistrarHistorialPedidoService;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use App\Support\ControlPedidos\Municipal\RecorridoFisicoMunicipal;
use App\Support\PuntoVenta\Resguardos\EstadoResguardoPdv;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Salida a terminal de un municipal de ruta nueva desde la custodia compartida (Gerencia PDV, con receptor).
 * El resguardo sale de custodia con ESTADO_ENTREGADO y la marca `snapshot_json.salida_terminal`; no crea fila de
 * entrega al cliente ni dispara su evento. El pedido conserva su estatus y recibe la marca `salida_terminal`.
 * No cobra ni da por pagado ningún flete.
 */
class RegistrarSalidaTerminalResguardoPdvService
{
    public const MAX_RECEPTOR = 120;

    private const PREFIJO_CLAVE = 'terminal:';

    public function __construct(
        private readonly ResuelveAlcancePdv $alcance,
        private readonly RegistrarHistorialPedidoService $historial,
        private readonly SincronizarEstatusSucursalPedidoBmaService $sincronizarEstatusSucursal,
        private readonly NotificarPedidoBmaService $notificar,
    ) {}

    public function ejecutar(
        ResguardoPdv $resguardo,
        User $actor,
        int $versionEsperada,
        string $idempotencyKey,
        string $receptor,
    ): ResguardoPdv {
        $this->alcance->asegurarMutacionPiso(
            $actor,
            PuntoVentaModulo::PERMISO_RESGUARDOS_SALIDA_TERMINAL,
            (int) $resguardo->sucursal_id
        );

        $receptor = trim($receptor);
        if ($receptor === '') {
            throw ValidationException::withMessages([
                'receptor' => 'Indique el nombre de quien recibe el paquete para llevarlo a terminal.',
            ]);
        }
        if (mb_strlen($receptor) > self::MAX_RECEPTOR) {
            throw ValidationException::withMessages([
                'receptor' => 'El nombre de quien recibe admite hasta '.self::MAX_RECEPTOR.' caracteres.',
            ]);
        }

        return DB::transaction(function () use ($resguardo, $actor, $versionEsperada, $idempotencyKey, $receptor) {
            $pedidoId = (int) ($resguardo->pedido_bma_id ?? 0);
            // Mismo orden de bloqueo que la custodia nacional: pedido y después resguardo.
            $pedido = $pedidoId > 0 ? PedidoBma::query()->lockForUpdate()->find($pedidoId) : null;
            $resguardo = ResguardoPdv::query()->with('bultos')->lockForUpdate()->findOrFail($resguardo->id);

            $reintento = $this->resolverReintentoIdempotente($resguardo, $idempotencyKey);
            if ($reintento !== null) {
                return $reintento;
            }

            if ($resguardo->salidaTerminal() !== null) {
                throw new ConflictHttpException('Este paquete ya salió a terminal.');
            }
            if ($resguardo->estado === ResguardoPdv::ESTADO_ENTREGADO) {
                throw new ConflictHttpException('Este resguardo ya salió de custodia.');
            }
            if ((int) $resguardo->version !== $versionEsperada) {
                throw ValidationException::withMessages([
                    'version' => 'Otro usuario modificó este resguardo. Actualice la página e intente de nuevo.',
                ]);
            }
            $motivo = $this->motivoBloqueo($resguardo, $pedido);
            if ($motivo !== null) {
                throw ValidationException::withMessages(['estado' => $motivo]);
            }

            return $this->registrar($resguardo, $pedido, $actor, $idempotencyKey, $receptor);
        });
    }

    /**
     * Para el detalle: el actor puede registrar la salida ahora (permiso, sucursal y reglas del recorrido).
     */
    public function puedeRegistrar(User $actor, ResguardoPdv $resguardo): bool
    {
        if (! $this->alcance->permiteMutacionPiso(
            $actor,
            PuntoVentaModulo::PERMISO_RESGUARDOS_SALIDA_TERMINAL,
            (int) $resguardo->sucursal_id
        )) {
            return false;
        }
        if ($resguardo->salidaTerminal() !== null) {
            return false;
        }
        $pedido = $resguardo->pedido_bma_id ? PedidoBma::query()->find($resguardo->pedido_bma_id) : null;

        return $this->motivoBloqueo($resguardo, $pedido) === null;
    }

    private function motivoBloqueo(ResguardoPdv $resguardo, ?PedidoBma $pedido): ?string
    {
        if (! $resguardo->esOrigenMunicipal()) {
            return 'Solo un envío municipal sale a terminal desde la custodia.';
        }
        if (! $pedido instanceof PedidoBma || ! RecorridoFisicoMunicipal::aplica($pedido)) {
            return 'Este pedido no sigue el recorrido municipal hacia terminal.';
        }
        if ($pedido->cancelado_at) {
            return 'El pedido está cancelado.';
        }
        if ($resguardo->estado !== ResguardoPdv::ESTADO_EN_CUSTODIA || ! EstadoResguardoPdv::custodiaCompleta($resguardo)) {
            return 'El paquete debe estar completo en custodia para registrar la salida a terminal.';
        }
        $incidenciaAbierta = ResguardoPdvIncidencia::query()
            ->where('resguardo_id', $resguardo->id)
            ->where('estado', ResguardoPdvIncidencia::ESTADO_ABIERTA)
            ->exists();
        if ($resguardo->entrega_bloqueada || $incidenciaAbierta) {
            return 'Hay una incidencia abierta o un bloqueo en este paquete. Resuélvalo antes de la salida a terminal.';
        }

        return null;
    }

    private function registrar(
        ResguardoPdv $resguardo,
        PedidoBma $pedido,
        User $actor,
        string $idempotencyKey,
        string $receptor,
    ): ResguardoPdv {
        $ahora = now();
        $marca = [
            'at' => $ahora->toIso8601String(),
            'receptor' => $receptor,
            'actor_id' => (int) $actor->id,
        ];

        try {
            ResguardoPdvEvento::query()->create([
                'resguardo_id' => $resguardo->id,
                'tipo_evento' => ResguardoPdvEvento::TIPO_SALIDA_TERMINAL,
                'estado_anterior' => $resguardo->estado,
                'estado_nuevo' => ResguardoPdv::ESTADO_ENTREGADO,
                'actor_id' => $actor->id,
                'ocurrido_at' => $ahora,
                'snapshot_json' => $marca + [
                    'pedido_bma_id' => (int) $pedido->id,
                    'cantidad_bultos' => $resguardo->bultos
                        ->filter(fn (ResguardoPdvBulto $b) => ResguardoPdvBulto::estaEnCustodiaOperativa($b->estado))
                        ->count(),
                ],
                'idempotency_key' => self::PREFIJO_CLAVE.$idempotencyKey,
            ]);
        } catch (UniqueConstraintViolationException $e) {
            $recuperado = $this->resolverReintentoIdempotente($resguardo, $idempotencyKey);
            if ($recuperado !== null) {
                return $recuperado;
            }

            throw $e;
        }

        foreach ($resguardo->bultos as $bulto) {
            if (! ResguardoPdvBulto::estaEnCustodiaOperativa($bulto->estado)) {
                continue;
            }
            // Salida física de custodia; el detalle del resguardo distingue la salida a terminal.
            $bulto->update([
                'estado' => ResguardoPdvBulto::ESTADO_ENTREGADO,
                'entrega_at' => $ahora,
                'version' => $bulto->version + 1,
            ]);
        }

        $snapshot = is_array($resguardo->snapshot_json) ? $resguardo->snapshot_json : [];
        $snapshot['salida_terminal'] = $marca;
        $resguardo->update([
            'estado' => ResguardoPdv::ESTADO_ENTREGADO,
            'entrega_completada_at' => $ahora,
            'snapshot_json' => $snapshot,
            'version' => $resguardo->version + 1,
        ]);

        $json = is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];
        $json['salida_terminal'] = $marca;
        $pedido->update(['solicitud_preparacion_json' => $json]);

        $estatusId = (int) $pedido->catalogo_estatus_pedido_id;
        $this->historial->ejecutar(
            (int) $pedido->id,
            (int) $actor->id,
            $estatusId,
            $estatusId,
            'Salida a terminal registrada. Recibe: '.$receptor.'.',
            AccionesHistorialPedidoBma::SALIDA_TERMINAL,
            null,
            ['salida_terminal' => $marca, 'resguardo_id' => (int) $resguardo->id],
        );

        // Señal para Ventas: el paquete ya no está en custodia. Se envía tras confirmar la transacción.
        $this->notificar->ejecutar(
            $pedido,
            'pedido_salida_terminal',
            'Salida a terminal registrada. Recibe: '.$receptor.'.',
            [],
            (int) $actor->id,
            true,
            ['url' => '/control-pedidos?tab=ENVIADOS&q='.urlencode((string) ($pedido->folio_remision ?: $pedido->folio ?: $pedido->id))]
        );

        $resguardo = $resguardo->fresh(['bultos']);
        $this->sincronizarEstatusSucursal->desdeResguardo($resguardo);

        return $resguardo;
    }

    private function resolverReintentoIdempotente(ResguardoPdv $resguardo, string $idempotencyKey): ?ResguardoPdv
    {
        $evento = ResguardoPdvEvento::query()
            ->where('idempotency_key', self::PREFIJO_CLAVE.$idempotencyKey)
            ->first();

        if (! $evento) {
            return null;
        }

        if ((int) $evento->resguardo_id !== (int) $resguardo->id
            || $evento->tipo_evento !== ResguardoPdvEvento::TIPO_SALIDA_TERMINAL) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'La clave de idempotencia ya fue utilizada en otra operación.',
            ]);
        }

        return $resguardo->fresh(['bultos']);
    }
}
