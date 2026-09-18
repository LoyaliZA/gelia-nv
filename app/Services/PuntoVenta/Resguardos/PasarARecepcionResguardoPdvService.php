<?php

namespace App\Services\PuntoVenta\Resguardos;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvEvento;
use App\Models\User;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Support\PuntoVenta\Resguardos\EstadoResguardoPdv;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class PasarARecepcionResguardoPdvService
{
    public function __construct(
        private readonly ResuelveAlcancePdv $alcance,
        private readonly SincronizarEstatusSucursalPedidoBmaService $sincronizarEstatusSucursal,
        private readonly NotificarResguardoPdvService $notificar,
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

        return DB::transaction(function () use ($resguardo, $actor, $versionEsperada, $idempotencyKey) {
            $resguardo = ResguardoPdv::query()
                ->with('bultos')
                ->lockForUpdate()
                ->findOrFail($resguardo->id);

            $reintento = $this->resolverReintentoIdempotente($resguardo, $idempotencyKey);
            if ($reintento !== null) {
                return $reintento;
            }

            if ((int) $resguardo->version !== $versionEsperada) {
                throw ValidationException::withMessages([
                    'version' => 'Otro usuario modificó este resguardo. Actualice la página e intente de nuevo.',
                ]);
            }

            if (! EstadoResguardoPdv::admitePasarARecepcion($resguardo)) {
                if ($resguardo->estado === ResguardoPdv::ESTADO_EN_RECEPCION) {
                    throw new ConflictHttpException('Este resguardo ya está en recepción.');
                }

                throw ValidationException::withMessages([
                    'estado' => 'El resguardo no puede pasarse a recepción desde su estado actual.',
                ]);
            }

            $estadoAnterior = $resguardo->estado;
            $ahora = now();

            $resguardo->update([
                'estado' => ResguardoPdv::ESTADO_EN_RECEPCION,
                'version' => $resguardo->version + 1,
            ]);

            try {
                ResguardoPdvEvento::query()->create([
                    'resguardo_id' => $resguardo->id,
                    'tipo_evento' => ResguardoPdvEvento::TIPO_PASADO_A_RECEPCION,
                    'estado_anterior' => $estadoAnterior,
                    'estado_nuevo' => ResguardoPdv::ESTADO_EN_RECEPCION,
                    'actor_id' => $actor->id,
                    'ocurrido_at' => $ahora,
                    'snapshot_json' => [
                        'paso' => 'gerente',
                        'cantidad_bultos' => EstadoResguardoPdv::cantidadRecibidaGerente($resguardo),
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
            $this->notificar->pasadoARecepcion($resguardo, (int) $resguardo->sucursal_id, $idempotencyKey);

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

        if ($evento->tipo_evento !== ResguardoPdvEvento::TIPO_PASADO_A_RECEPCION) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'La clave de idempotencia corresponde a otra transición.',
            ]);
        }

        return $resguardo->fresh(['bultos', 'almacen']);
    }
}
