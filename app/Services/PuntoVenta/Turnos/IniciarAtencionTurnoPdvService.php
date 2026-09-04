<?php

namespace App\Services\PuntoVenta\Turnos;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Jobs\PuntoVenta\Turnos\AlertaProrrogaAtencionTurnoPdvJob;
use App\Models\PuntoVenta\TurnoPdv;
use App\Models\PuntoVenta\TurnoPdvAtencion;
use App\Models\User;
use App\Services\PuntoVenta\PuntoVentaModulo;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class IniciarAtencionTurnoPdvService
{
    use ResuelveIdempotenciaTurnoPdv;

    public function __construct(
        private readonly ResuelveAlcancePdv $alcance,
        private readonly PlazosTurnosPdvConfig $plazos,
    ) {}

    /**
     * @return array{turno: TurnoPdv, atencion: TurnoPdvAtencion}
     */
    public function ejecutar(
        TurnoPdv $turno,
        User $actor,
        int $versionEsperada,
        CarbonInterface $ahora,
    ): array {
        $this->alcance->asegurarMutacionPiso(
            $actor,
            PuntoVentaModulo::PERMISO_TURNOS_CERRAR_ATENCION,
            (int) $turno->sucursal_id,
        );

        return DB::transaction(function () use ($turno, $actor, $versionEsperada, $ahora): array {
            $turnoBloqueado = TurnoPdv::query()
                ->whereKey($turno->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertVersionTurno($turnoBloqueado, $versionEsperada);
            $this->assertEstadoAsignado($turnoBloqueado);

            $atencion = TurnoPdvAtencion::query()
                ->whereKey($turnoBloqueado->atencion_actual_id)
                ->lockForUpdate()
                ->first();

            if (! $atencion instanceof TurnoPdvAtencion) {
                throw ValidationException::withMessages([
                    'turno' => 'El turno no tiene una atención activa.',
                ]);
            }

            $this->assertActorAtiende($actor, $atencion);

            if ($atencion->fin_at !== null) {
                throw ValidationException::withMessages([
                    'turno' => 'La atención ya fue cerrada.',
                ]);
            }

            if ($atencion->atencion_inicio_at !== null) {
                return [
                    'turno' => $turnoBloqueado->fresh(['cliente', 'sucursal', 'atencionActual']),
                    'atencion' => $atencion->fresh(),
                ];
            }

            $atencion->update([
                'atencion_inicio_at' => $ahora,
            ]);

            $plazos = $this->plazos->obtener();
            $disparo = $ahora->copy()->addMinutes($plazos['prorroga_minutos']);

            AlertaProrrogaAtencionTurnoPdvJob::dispatch($atencion->id)
                ->delay($disparo);

            return [
                'turno' => $turnoBloqueado->fresh(['cliente', 'sucursal', 'atencionActual']),
                'atencion' => $atencion->fresh(),
            ];
        });
    }

    private function assertEstadoAsignado(TurnoPdv $turno): void
    {
        if ($turno->estado !== TurnoPdv::ESTADO_ASIGNADO) {
            throw ValidationException::withMessages([
                'turno' => 'Solo se puede iniciar atención en turnos asignados.',
            ]);
        }
    }

    private function assertActorAtiende(User $actor, TurnoPdvAtencion $atencion): void
    {
        if ((int) $atencion->user_id !== (int) $actor->id) {
            throw ValidationException::withMessages([
                'turno' => 'Solo quien atiende puede iniciar esta atención.',
            ]);
        }
    }
}
