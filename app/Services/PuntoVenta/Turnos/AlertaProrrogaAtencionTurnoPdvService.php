<?php

namespace App\Services\PuntoVenta\Turnos;

use App\Events\PuntoVenta\AtencionProrroga;
use App\Models\PuntoVenta\TurnoPdv;
use App\Models\PuntoVenta\TurnoPdvAtencion;
use App\Models\PuntoVenta\TurnoPdvEvento;
use App\Models\PuntoVenta\TurnoPdvProrroga;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class AlertaProrrogaAtencionTurnoPdvService
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

            if ($atencion->fin_at !== null || $atencion->atencion_inicio_at === null) {
                return false;
            }

            if (TurnoPdvProrroga::query()->where('atencion_id', $atencion->id)->exists()) {
                return false;
            }

            $plazos = $this->plazos->obtener();
            $umbral = $atencion->atencion_inicio_at->copy()->addMinutes($plazos['prorroga_minutos']);
            if ($ahora->lt($umbral)) {
                return false;
            }

            $turno = TurnoPdv::query()
                ->whereKey($atencion->turno_id)
                ->lockForUpdate()
                ->first();

            if (! $turno instanceof TurnoPdv || $turno->estado !== TurnoPdv::ESTADO_ASIGNADO) {
                return false;
            }

            TurnoPdvProrroga::query()->create([
                'atencion_id' => $atencion->id,
                'referencia_inicio_at' => $atencion->atencion_inicio_at,
                'alertado_at' => $ahora,
                'snapshot_json' => [
                    'umbral_minutos' => $plazos['prorroga_minutos'],
                ],
            ]);

            $eventoPendiente = TurnoPdvEvento::query()->create([
                'turno_id' => $turno->id,
                'atencion_id' => $atencion->id,
                'tipo_evento' => TurnoPdvEvento::TIPO_PRORROGA,
                'estado_anterior' => TurnoPdv::ESTADO_ASIGNADO,
                'estado_nuevo' => TurnoPdv::ESTADO_ASIGNADO,
                'actor_id' => null,
                'ocurrido_at' => $ahora,
                'snapshot_json' => [
                    'atencion_inicio_at' => $atencion->atencion_inicio_at->toIso8601String(),
                    'umbral_minutos' => $plazos['prorroga_minutos'],
                ],
                'idempotency_key' => 'pdv:prorroga:'.$atencion->id,
            ]);

            $turnoPendiente = $turno->fresh(['cliente', 'sucursal', 'atencionActual']);
            $atencionPendiente = $atencion->fresh();

            return true;
        });

        if ($emitido && $eventoPendiente instanceof TurnoPdvEvento && $turnoPendiente instanceof TurnoPdv && $atencionPendiente instanceof TurnoPdvAtencion) {
            AtencionProrroga::dispatch(
                $turnoPendiente,
                $atencionPendiente,
                $eventoPendiente,
                (int) $turnoPendiente->sucursal_id,
            );
        }

        return $emitido;
    }
}
