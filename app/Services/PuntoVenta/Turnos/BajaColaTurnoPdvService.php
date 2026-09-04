<?php

namespace App\Services\PuntoVenta\Turnos;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Models\PuntoVenta\TurnoPdv;
use App\Models\PuntoVenta\TurnoPdvEvento;
use App\Models\User;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Support\PuntoVenta\Turnos\MotivosBajaColaTurnoPdv;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BajaColaTurnoPdvService
{
    use ResuelveIdempotenciaTurnoPdv;

    public function __construct(
        private readonly ResuelveAlcancePdv $alcance,
    ) {}

    /**
     * @return array{turno: TurnoPdv, evento: TurnoPdvEvento}
     */
    public function ejecutar(
        TurnoPdv $turno,
        User $actor,
        int $versionEsperada,
        string $idempotencyKey,
        string $motivo,
        ?string $motivoDetalle,
        CarbonInterface $ahora,
    ): array {
        $this->alcance->asegurarMutacionPiso(
            $actor,
            PuntoVentaModulo::PERMISO_TURNOS_BAJA_COLA,
            (int) $turno->sucursal_id,
        );

        if (! MotivosBajaColaTurnoPdv::esValido($motivo)) {
            throw ValidationException::withMessages([
                'motivo' => 'El motivo de baja no es válido.',
            ]);
        }

        if ($motivo === MotivosBajaColaTurnoPdv::OTRO && trim((string) $motivoDetalle) === '') {
            throw ValidationException::withMessages([
                'motivo_detalle' => 'Debe indicar el detalle cuando el motivo es "otro".',
            ]);
        }

        return DB::transaction(function () use (
            $turno,
            $actor,
            $versionEsperada,
            $idempotencyKey,
            $motivo,
            $motivoDetalle,
            $ahora,
        ): array {
            $reintento = $this->resolverReintentoIdempotente($idempotencyKey, TurnoPdvEvento::TIPO_BAJA_COLA);
            if ($reintento !== null) {
                return $reintento;
            }

            $turnoBloqueado = TurnoPdv::query()
                ->whereKey($turno->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertVersionTurno($turnoBloqueado, $versionEsperada);

            if ($turnoBloqueado->estado !== TurnoPdv::ESTADO_EN_COLA) {
                throw ValidationException::withMessages([
                    'turno' => 'Solo se puede dar de baja un turno en cola.',
                ]);
            }

            if ($turnoBloqueado->atencion_actual_id !== null) {
                throw ValidationException::withMessages([
                    'turno' => 'No se puede dar de baja un turno ya asignado.',
                ]);
            }

            $versionAnterior = (int) $turnoBloqueado->version;
            $actualizado = TurnoPdv::query()
                ->whereKey($turnoBloqueado->id)
                ->where('version', $versionAnterior)
                ->update([
                    'estado' => TurnoPdv::ESTADO_CERRADO,
                    'cerrado_at' => $ahora,
                    'baja_por_id' => $actor->id,
                    'baja_at' => $ahora,
                    'baja_motivo' => $motivo,
                    'baja_motivo_detalle' => $motivoDetalle,
                    'version' => $versionAnterior + 1,
                ]);

            if ($actualizado !== 1) {
                throw ValidationException::withMessages([
                    'version' => 'Otro usuario modificó este turno. Actualice la página e intente de nuevo.',
                ]);
            }

            try {
                $evento = TurnoPdvEvento::query()->create([
                    'turno_id' => $turnoBloqueado->id,
                    'tipo_evento' => TurnoPdvEvento::TIPO_BAJA_COLA,
                    'estado_anterior' => TurnoPdv::ESTADO_EN_COLA,
                    'estado_nuevo' => TurnoPdv::ESTADO_CERRADO,
                    'actor_id' => $actor->id,
                    'ocurrido_at' => $ahora,
                    'snapshot_json' => [
                        'motivo' => $motivo,
                        'motivo_detalle' => $motivoDetalle,
                    ],
                    'idempotency_key' => $idempotencyKey,
                ]);
            } catch (UniqueConstraintViolationException $exception) {
                $recuperado = $this->manejarColisionIdempotencia(
                    $exception,
                    $idempotencyKey,
                    TurnoPdvEvento::TIPO_BAJA_COLA,
                );
                if ($recuperado !== null) {
                    return $recuperado;
                }

                throw $exception;
            }

            return [
                'turno' => $turnoBloqueado->fresh(['cliente', 'sucursal']),
                'evento' => $evento,
            ];
        });
    }
}
