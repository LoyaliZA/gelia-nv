<?php

namespace App\Support\PuntoVenta\Turnos;

use App\Models\PuntoVenta\TurnoPdv;
use App\Models\PuntoVenta\TurnoPdvAtencion;

final class SerializadorTurnoPdv
{
    /**
     * @return array<string, mixed>
     */
    public static function turno(TurnoPdv $turno): array
    {
        return [
            'id' => $turno->id,
            'folio' => $turno->folio,
            'estado' => $turno->estado,
            'servicio' => $turno->servicio,
            'sucursal_id' => $turno->sucursal_id,
            'cliente_id' => $turno->cliente_id,
            'snapshot_nombre_llamado' => $turno->snapshot_nombre_llamado,
            'reatencion_expira_at' => $turno->reatencion_expira_at?->toIso8601String(),
            'cerrado_at' => $turno->cerrado_at?->toIso8601String(),
            'atencion_actual_id' => $turno->atencion_actual_id,
            'version' => $turno->version,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function atencion(TurnoPdvAtencion $atencion): array
    {
        return [
            'id' => $atencion->id,
            'turno_id' => $atencion->turno_id,
            'user_id' => $atencion->user_id,
            'numero_secuencia' => $atencion->numero_secuencia,
            'inicio_at' => $atencion->inicio_at?->toIso8601String(),
            'atencion_inicio_at' => $atencion->atencion_inicio_at?->toIso8601String(),
            'fin_at' => $atencion->fin_at?->toIso8601String(),
            'motivo_cierre' => $atencion->motivo_cierre,
            'motivo_cierre_detalle' => $atencion->motivo_cierre_detalle,
            'es_transferencia' => $atencion->es_transferencia,
            'transferido_por_id' => $atencion->transferido_por_id,
            'version' => $atencion->version,
        ];
    }
}
