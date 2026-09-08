<?php

namespace App\Services\PuntoVenta\Turnos;

use App\Events\PuntoVenta\AtencionEsperaProximoVencer;
use App\Models\PuntoVenta\TurnoPdv;
use App\Models\PuntoVenta\TurnoPdvAtencion;
use App\Models\PuntoVenta\TurnoPdvEvento;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class AlertaEsperaProximoVencerTurnoPdvService
{
    public function __construct(
        private readonly PlazosTurnosPdvConfig $plazos,
    ) {}

    public function ejecutar(int $atencionId, CarbonInterface $ahora): bool
    {
        $eventoPendiente = null;
        $turnoPendiente = null;
        $atencionPendiente = null;

        $emitido = DB::transaction(function () use ($atencionId, $ahora, &$eventoPendiente, &$turnoPendiente, &$atencionPendiente): bool {
            $atencion = TurnoPdvAtencion::query()
                ->whereKey($atencionId)
                ->lockForUpdate()
                ->first();

            if (! $atencion instanceof TurnoPdvAtencion) {
                return false;
            }

            if ($atencion->fin_at !== null || $atencion->atencion_inicio_at !== null) {
                return false;
            }

            if ($atencion->inicio_at === null) {
                return false;
            }

            $plazos = $this->plazos->obtener();
            $expiraAt = $atencion->inicio_at->copy()->addMinutes($plazos['espera_inicial_minutos']);
            if ($ahora->greaterThanOrEqualTo($expiraAt)) {
                return false;
            }

            $turno = TurnoPdv::query()
                ->whereKey($atencion->turno_id)
                ->lockForUpdate()
                ->first();

            if (! $turno instanceof TurnoPdv || $turno->estado !== TurnoPdv::ESTADO_ASIGNADO) {
                return false;
            }

            $idempotencyKey = 'pdv:espera_proximo:'.$atencion->id;
            if (TurnoPdvEvento::query()->where('idempotency_key', $idempotencyKey)->exists()) {
                return false;
            }

            $eventoPendiente = TurnoPdvEvento::query()->create([
                'turno_id' => $turno->id,
                'atencion_id' => $atencion->id,
                'tipo_evento' => TurnoPdvEvento::TIPO_ESPERA_PROXIMO_VENCER,
                'estado_anterior' => TurnoPdv::ESTADO_ASIGNADO,
                'estado_nuevo' => TurnoPdv::ESTADO_ASIGNADO,
                'actor_id' => null,
                'ocurrido_at' => $ahora,
                'snapshot_json' => [
                    'espera_expira_at' => $expiraAt->toIso8601String(),
                    'espera_inicial_minutos' => $plazos['espera_inicial_minutos'],
                ],
                'idempotency_key' => $idempotencyKey,
            ]);

            $turnoPendiente = $turno->fresh(['cliente', 'sucursal', 'atencionActual']);
            $atencionPendiente = $atencion->fresh();

            return true;
        });

        if ($emitido && $eventoPendiente instanceof TurnoPdvEvento && $turnoPendiente instanceof TurnoPdv && $atencionPendiente instanceof TurnoPdvAtencion) {
            AtencionEsperaProximoVencer::dispatch(
                $turnoPendiente,
                $atencionPendiente,
                $eventoPendiente,
                (int) $turnoPendiente->sucursal_id,
            );
        }

        return $emitido;
    }
}
