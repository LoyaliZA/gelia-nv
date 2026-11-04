<?php

namespace App\Services\PuntoVenta\Turnos;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Models\PuntoVenta\JornadaPdv;
use App\Models\PuntoVenta\TurnoPdv;
use App\Models\PuntoVenta\TurnoPdvAtencion;
use App\Models\User;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Support\PuntoVenta\Operacion\EstadoJornadaPdv;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class IniciarAtencionTurnoPdvService
{
    use ResuelveIdempotenciaTurnoPdv;

    public function __construct(
        private readonly ResuelveAlcancePdv $alcance,
        private readonly AplicarInicioAtencionTurnoPdvService $inicioAtencion,
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
            PuntoVentaModulo::PERMISO_TURNOS_INICIAR_ATENCION,
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

            $this->assertCooldownVencido($atencion, $turnoBloqueado, $ahora);

            $atencion = $this->inicioAtencion->ejecutar($atencion, $ahora);

            return [
                'turno' => $turnoBloqueado->fresh(['cliente', 'sucursal', 'atencionActual']),
                'atencion' => $atencion,
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

    private function assertCooldownVencido(TurnoPdvAtencion $atencion, TurnoPdv $turno, CarbonInterface $ahora): void
    {
        $jornada = JornadaPdv::query()
            ->where('user_id', $atencion->user_id)
            ->where('sucursal_id', $turno->sucursal_id)
            ->where('estado', EstadoJornadaPdv::Abierta)
            ->first();

        if ($jornada?->disponible_desde instanceof CarbonInterface && $ahora->lt($jornada->disponible_desde)) {
            throw ValidationException::withMessages([
                'turno' => 'La atención inicia al terminar el tiempo de espera.',
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
