<?php

namespace App\Services\PuntoVenta\Resguardos;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Events\PuntoVenta\RecepcionFisicaPdvCompletada;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvBulto;
use App\Models\PuntoVenta\ResguardoPdvEvento;
use App\Models\User;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Support\PuntoVenta\Resguardos\BultosEsperadosResguardoPdv;
use App\Support\PuntoVenta\Resguardos\EstadoRecepcionResguardoPdv;
use App\Support\PuntoVenta\Resguardos\GeneradorCodigoEtiquetaResguardoPdv;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class RegistrarRecepcionFisicaPdvService
{
    /** @var list<string> */
    private const TIPOS_EVENTO_RECEPCION = [
        ResguardoPdvEvento::TIPO_RECEPCION_COMPLETA,
        ResguardoPdvEvento::TIPO_RECEPCION_PARCIAL,
    ];

    public function __construct(
        private readonly ResuelveAlcancePdv $alcance,
        private readonly SincronizarEstatusSucursalPedidoBmaService $sincronizarEstatusSucursal,
    ) {}

    public function ejecutar(
        ResguardoPdv $resguardo,
        User $actor,
        int $versionEsperada,
        string $idempotencyKey,
    ): ResguardoPdv {
        $this->alcance->asegurarMutacionPiso(
            $actor,
            PuntoVentaModulo::PERMISO_RESGUARDOS_RECIBIR_GERENTE,
            (int) $resguardo->sucursal_id
        );

        return DB::transaction(function () use (
            $resguardo,
            $actor,
            $versionEsperada,
            $idempotencyKey,
        ) {
            $resguardo = ResguardoPdv::query()
                ->with(['bultos', 'pedido.bultosEmpaque'])
                ->lockForUpdate()
                ->findOrFail($resguardo->id);

            $reintento = $this->resolverReintentoIdempotente($resguardo, $idempotencyKey);
            if ($reintento !== null) {
                return $reintento;
            }

            if ((int) $resguardo->cantidad_bultos_esperada < 1) {
                $resguardo = app(SincronizarCantidadBultosEsperadaResguardoPdvService::class)->ejecutar($resguardo);
            }

            $estadoAnterior = $resguardo->estado;
            $this->assertVersionYEstado($resguardo, $versionEsperada);
            $bultosNormalizados = BultosEsperadosResguardoPdv::desdeResguardo($resguardo);
            $esperada = (int) $resguardo->cantidad_bultos_esperada;
            $ahora = now();

            foreach ($bultosNormalizados as $dato) {
                try {
                    ResguardoPdvBulto::query()->create([
                        'resguardo_id' => $resguardo->id,
                        'pedido_bma_id' => $resguardo->pedido_bma_id,
                        'folio' => $dato['folio'],
                        'codigo_etiqueta' => GeneradorCodigoEtiquetaResguardoPdv::generar(),
                        'tipo' => $dato['tipo'],
                        'piezas' => $dato['piezas'],
                        'estado' => ResguardoPdvBulto::ESTADO_RECIBIDO_GERENTE,
                        'recepcion_at' => $ahora,
                        'recepcion_por_id' => $actor->id,
                        'version' => 1,
                    ]);
                } catch (UniqueConstraintViolationException) {
                    throw ValidationException::withMessages([
                        'bultos' => 'Uno o más folios ya fueron recibidos en este resguardo.',
                    ]);
                }
            }

            $resguardo->update([
                'estado' => ResguardoPdv::ESTADO_RECIBIDO,
                'recepcion_fisica_at' => $resguardo->recepcion_fisica_at ?? $ahora,
                'version' => $resguardo->version + 1,
            ]);

            try {
                $evento = ResguardoPdvEvento::query()->create([
                    'resguardo_id' => $resguardo->id,
                    'tipo_evento' => ResguardoPdvEvento::TIPO_RECEPCION_COMPLETA,
                    'estado_anterior' => $estadoAnterior,
                    'estado_nuevo' => ResguardoPdv::ESTADO_RECIBIDO,
                    'actor_id' => $actor->id,
                    'ocurrido_at' => $ahora,
                    'snapshot_json' => [
                        'paso' => 'gerente',
                        'bultos' => $bultosNormalizados,
                        'cantidad_llegada' => count($bultosNormalizados),
                        'cantidad_recibida' => count($bultosNormalizados),
                        'cantidad_esperada' => $esperada,
                        'cantidad_pendiente' => 0,
                        'recepcion_completa' => true,
                    ],
                    'idempotency_key' => $idempotencyKey,
                ]);
            } catch (UniqueConstraintViolationException $e) {
                $recuperado = $this->resolverReintentoIdempotente($resguardo, $idempotencyKey);
                if ($recuperado !== null) {
                    return $recuperado;
                }

                throw $e;
            }

            $resguardo = $resguardo->fresh(['bultos', 'almacen']);
            $this->sincronizarEstatusSucursal->desdeResguardo($resguardo);

            RecepcionFisicaPdvCompletada::dispatch(
                $resguardo,
                $evento,
                (int) $resguardo->sucursal_id
            );

            return $resguardo;
        });
    }

    private function resolverReintentoIdempotente(ResguardoPdv $resguardo, string $idempotencyKey): ?ResguardoPdv
    {
        $evento = ResguardoPdvEvento::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if (! $evento) {
            return null;
        }

        if ((int) $evento->resguardo_id !== (int) $resguardo->id) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'La clave de idempotencia ya fue utilizada en otra operación.',
            ]);
        }

        if (! in_array($evento->tipo_evento, self::TIPOS_EVENTO_RECEPCION, true)) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'La clave de idempotencia corresponde a otra transición.',
            ]);
        }

        return $resguardo->fresh(['bultos', 'almacen']);
    }

    private function assertVersionYEstado(ResguardoPdv $resguardo, int $versionEsperada): void
    {
        if ((int) $resguardo->version !== $versionEsperada) {
            throw ValidationException::withMessages([
                'version' => 'Otro usuario modificó este resguardo. Actualice la página e intente de nuevo.',
            ]);
        }

        if (! EstadoRecepcionResguardoPdv::admiteRecepcion($resguardo)) {
            if (EstadoRecepcionResguardoPdv::recepcionCompleta($resguardo)) {
                throw new ConflictHttpException('Este resguardo ya fue recibido por gerencia.');
            }

            throw ValidationException::withMessages([
                'estado' => 'El resguardo no admite recepción física desde su estado actual.',
            ]);
        }
    }
}
