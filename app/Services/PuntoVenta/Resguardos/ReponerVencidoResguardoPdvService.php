<?php

namespace App\Services\PuntoVenta\Resguardos;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Events\PuntoVenta\ResguardoPdvVencidoRepuesto;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvEvento;
use App\Models\User;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Support\PuntoVenta\Resguardos\AntiguedadOperativaResguardoPdv;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ReponerVencidoResguardoPdvService
{
    public function __construct(
        private readonly ResuelveAlcancePdv $alcance,
        private readonly CalcularAntiguedadOperativaResguardoPdvService $antiguedad,
    ) {}

    public function ejecutar(
        ResguardoPdv $resguardo,
        User $actor,
        int $versionEsperada,
        string $idempotencyKey,
        string $motivo,
    ): ResguardoPdv {
        $this->alcance->asegurarMutacionPiso(
            $actor,
            PuntoVentaModulo::PERMISO_RESGUARDOS_REPONER_VENCIDO,
            (int) $resguardo->sucursal_id
        );

        return DB::transaction(function () use (
            $resguardo,
            $actor,
            $versionEsperada,
            $idempotencyKey,
            $motivo,
        ) {
            $resguardo = ResguardoPdv::query()->lockForUpdate()->findOrFail($resguardo->id);

            $reintento = $this->resolverReintentoIdempotente($resguardo, $idempotencyKey);
            if ($reintento !== null) {
                return $reintento;
            }

            $this->assertVersionEstadoYClasificacion($resguardo, $versionEsperada, $motivo);

            $ahora = now();
            $estado = $resguardo->estado;
            $recepcionAnterior = $resguardo->recepcion_fisica_at?->toIso8601String();

            $resguardo->update([
                'vencido_repuesto_at' => $ahora,
                'version' => $resguardo->version + 1,
            ]);

            try {
                $evento = ResguardoPdvEvento::query()->create([
                    'resguardo_id' => $resguardo->id,
                    'tipo_evento' => ResguardoPdvEvento::TIPO_VENCIDO_REPUESTO,
                    'estado_anterior' => $estado,
                    'estado_nuevo' => $estado,
                    'actor_id' => $actor->id,
                    'ocurrido_at' => $ahora,
                    'snapshot_json' => [
                        'motivo' => $motivo,
                        'recepcion_fisica_at' => $recepcionAnterior,
                        'plazo_reiniciado' => false,
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

            $resguardo = $resguardo->fresh();

            ResguardoPdvVencidoRepuesto::dispatch(
                $resguardo,
                $evento,
                $actor->id,
                (int) $resguardo->sucursal_id,
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

        if ($evento->tipo_evento !== ResguardoPdvEvento::TIPO_VENCIDO_REPUESTO) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'La clave de idempotencia ya fue utilizada en otra operación.',
            ]);
        }

        return $resguardo->fresh();
    }

    private function assertVersionEstadoYClasificacion(
        ResguardoPdv $resguardo,
        int $versionEsperada,
        string $motivo,
    ): void {
        if ((int) $resguardo->version !== $versionEsperada) {
            throw ValidationException::withMessages([
                'version' => 'Otro usuario modificó este resguardo. Actualice la página e intente de nuevo.',
            ]);
        }

        if ($resguardo->estado !== ResguardoPdv::ESTADO_EN_CUSTODIA) {
            throw ValidationException::withMessages([
                'estado' => 'Solo se puede reponer un resguardo en custodia.',
            ]);
        }

        if ($resguardo->vencido_repuesto_at !== null) {
            throw new ConflictHttpException('Este resguardo vencido ya fue repuesto a la bandeja principal.');
        }

        if (! $this->antiguedad->coincideConFiltro($resguardo, AntiguedadOperativaResguardoPdv::VENCIDO)) {
            throw ValidationException::withMessages([
                'clasificacion' => 'El resguardo no está clasificado como vencido.',
            ]);
        }

        if (trim($motivo) === '') {
            throw ValidationException::withMessages([
                'motivo' => 'El motivo de reposición es obligatorio.',
            ]);
        }
    }
}
