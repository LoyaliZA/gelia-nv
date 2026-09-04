<?php

namespace App\Support\PuntoVenta\Turnos;

use App\Models\PuntoVenta\TurnoPdv;
use App\Models\PuntoVenta\TurnoPdvAtencion;
use Carbon\CarbonInterface;

final class SerializadorTableroVentasPdv
{
    /**
     * @param  array{
     *   espera_inicial_minutos: int,
     *   prorroga_minutos: int,
     *   ventana_reatencion_minutos: int
     * }  $plazos
     * @return array<string, mixed>
     */
    public static function turno(TurnoPdv $turno, array $plazos, CarbonInterface $ahora): array
    {
        $atencion = $turno->relationLoaded('atencionActual')
            ? $turno->atencionActual
            : null;

        return array_merge(
            self::resumenTurno($turno, $ahora),
            [
                'version' => $turno->version,
                'atencion_actual_id' => $turno->atencion_actual_id,
                'atencion' => $atencion instanceof TurnoPdvAtencion
                    ? self::atencion($atencion, $plazos, $ahora)
                    : null,
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function turnoResumen(TurnoPdv $turno, CarbonInterface $ahora): array
    {
        return self::resumenTurno($turno, $ahora);
    }

    /**
     * @param  array{
     *   espera_inicial_minutos: int,
     *   prorroga_minutos: int,
     *   ventana_reatencion_minutos: int
     * }  $plazos
     * @return array<string, mixed>
     */
    public static function atencion(TurnoPdvAtencion $atencion, array $plazos, CarbonInterface $ahora): array
    {
        $inicioAt = $atencion->inicio_at;
        $atencionInicioAt = $atencion->atencion_inicio_at;

        $esperaExpiraAt = $inicioAt?->copy()->addMinutes($plazos['espera_inicial_minutos']);
        $prorrogaExpiraAt = $atencionInicioAt?->copy()->addMinutes($plazos['prorroga_minutos']);

        $prorrogaRegistrada = $atencion->relationLoaded('prorroga')
            ? $atencion->prorroga !== null
            : false;

        $prorrogaActiva = $prorrogaRegistrada
            || ($atencionInicioAt !== null && $prorrogaExpiraAt !== null && $ahora->greaterThanOrEqualTo($prorrogaExpiraAt));

        return [
            'id' => $atencion->id,
            'user_id' => $atencion->user_id,
            'numero_secuencia' => $atencion->numero_secuencia,
            'inicio_at' => $inicioAt?->toIso8601String(),
            'atencion_inicio_at' => $atencionInicioAt?->toIso8601String(),
            'fin_at' => $atencion->fin_at?->toIso8601String(),
            'es_transferencia' => $atencion->es_transferencia,
            'version' => $atencion->version,
            'espera_inicial_expira_at' => $esperaExpiraAt?->toIso8601String(),
            'espera_inicial_vencida' => $atencionInicioAt === null
                && $esperaExpiraAt !== null
                && $ahora->greaterThanOrEqualTo($esperaExpiraAt),
            'prorroga_expira_at' => $prorrogaExpiraAt?->toIso8601String(),
            'prorroga_activa' => $prorrogaActiva,
            'prorroga_registrada' => $prorrogaRegistrada,
            'atencion_en_curso' => $atencionInicioAt !== null && $atencion->fin_at === null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function resumenTurno(TurnoPdv $turno, CarbonInterface $ahora): array
    {
        return [
            'id' => $turno->id,
            'folio' => $turno->folio,
            'estado' => $turno->estado,
            'servicio' => $turno->servicio,
            'sucursal_id' => $turno->sucursal_id,
            'snapshot_nombre_llamado' => $turno->snapshot_nombre_llamado,
            'prioridad_diamante' => (bool) $turno->prioridad_diamante,
            'prioridad_vip' => (bool) $turno->prioridad_vip,
            'prioridad_adulto_mayor' => (bool) $turno->prioridad_adulto_mayor,
            'prioridad_discapacidad' => (bool) $turno->prioridad_discapacidad,
            'reatencion_expira_at' => $turno->reatencion_expira_at?->toIso8601String(),
            'reatencion_vigente' => $turno->estado === TurnoPdv::ESTADO_EN_REATENCION
                && $turno->reatencion_expira_at !== null
                && $ahora->lessThan($turno->reatencion_expira_at),
        ];
    }
}
