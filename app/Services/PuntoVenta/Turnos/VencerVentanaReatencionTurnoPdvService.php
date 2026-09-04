<?php

namespace App\Services\PuntoVenta\Turnos;

use App\Events\PuntoVenta\TurnoVentanaReatencionVencida;
use App\Models\PuntoVenta\TurnoPdv;
use App\Models\PuntoVenta\TurnoPdvEvento;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class VencerVentanaReatencionTurnoPdvService
{
    public function ejecutar(int $turnoId, CarbonInterface $ahora): bool
    {
        $eventoPendiente = null;
        $turnoPendiente = null;

        $emitido = DB::transaction(function () use ($turnoId, $ahora, &$eventoPendiente, &$turnoPendiente): bool {
            $turno = TurnoPdv::query()
                ->whereKey($turnoId)
                ->lockForUpdate()
                ->first();

            if (! $turno instanceof TurnoPdv) {
                return false;
            }

            if ($turno->estado !== TurnoPdv::ESTADO_EN_REATENCION) {
                return false;
            }

            if ($turno->reatencion_expira_at === null || $ahora->lt($turno->reatencion_expira_at)) {
                return false;
            }

            $versionAnterior = (int) $turno->version;
            $actualizado = TurnoPdv::query()
                ->whereKey($turno->id)
                ->where('version', $versionAnterior)
                ->update([
                    'estado' => TurnoPdv::ESTADO_CERRADO,
                    'cerrado_at' => $ahora,
                    'version' => $versionAnterior + 1,
                ]);

            if ($actualizado !== 1) {
                return false;
            }

            $eventoPendiente = TurnoPdvEvento::query()->create([
                'turno_id' => $turno->id,
                'tipo_evento' => TurnoPdvEvento::TIPO_VENTANA_REATENCION_VENCIDA,
                'estado_anterior' => TurnoPdv::ESTADO_EN_REATENCION,
                'estado_nuevo' => TurnoPdv::ESTADO_CERRADO,
                'actor_id' => null,
                'ocurrido_at' => $ahora,
                'snapshot_json' => [
                    'reatencion_expira_at' => $turno->reatencion_expira_at->toIso8601String(),
                ],
                'idempotency_key' => 'pdv:ventana-reatencion:'.$turno->id,
            ]);

            $turnoPendiente = $turno->fresh(['cliente', 'sucursal']);

            return true;
        });

        if ($emitido && $eventoPendiente instanceof TurnoPdvEvento && $turnoPendiente instanceof TurnoPdv) {
            TurnoVentanaReatencionVencida::dispatch(
                $turnoPendiente,
                $eventoPendiente,
                (int) $turnoPendiente->sucursal_id,
            );
        }

        return $emitido;
    }
}
