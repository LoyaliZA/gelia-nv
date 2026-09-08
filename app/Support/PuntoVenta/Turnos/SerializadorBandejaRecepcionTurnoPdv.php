<?php

namespace App\Support\PuntoVenta\Turnos;

use App\Models\PuntoVenta\TurnoPdv;
use App\Models\PuntoVenta\TurnoPdvAtencion;
use App\Models\User;
use Carbon\CarbonInterface;

final class SerializadorBandejaRecepcionTurnoPdv
{
    /**
     * @return array<string, mixed>
     */
    public static function turnoEnCola(TurnoPdv $turno, CarbonInterface $ahora): array
    {
        return array_merge(
            self::resumenTurno($turno, $ahora),
            [
                'version' => $turno->version,
                'puede_baja_cola' => true,
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * @param  array{
     *   espera_inicial_minutos: int,
     *   prorroga_minutos: int,
     *   ventana_reatencion_minutos: int
     * }|null  $plazos
     */
    public static function turnoAsignado(TurnoPdv $turno, CarbonInterface $ahora, ?array $plazos = null): array
    {
        $atencion = $turno->relationLoaded('atencionActual')
            ? $turno->atencionActual
            : null;

        return array_merge(
            self::resumenTurno($turno, $ahora),
            [
                'puede_baja_cola' => false,
                'atencion' => $atencion instanceof TurnoPdvAtencion
                    ? self::atencionResumen($atencion, $plazos, $ahora)
                    : null,
            ],
        );
    }

    /**
     * @param  array{
     *   espera_inicial_minutos: int,
     *   prorroga_minutos: int,
     *   ventana_reatencion_minutos: int
     * }|null  $plazos
     * @return array<string, mixed>
     */
    private static function atencionResumen(TurnoPdvAtencion $atencion, ?array $plazos, CarbonInterface $ahora): array
    {
        $persona = $atencion->relationLoaded('user')
            ? $atencion->user
            : null;

        $inicioAt = $atencion->inicio_at;
        $atencionInicioAt = $atencion->atencion_inicio_at;
        $esperaExpiraAt = $plazos !== null
            ? $inicioAt?->copy()->addMinutes($plazos['espera_inicial_minutos'])
            : null;

        return [
            'id' => $atencion->id,
            'user_id' => $atencion->user_id,
            'primer_nombre' => $persona instanceof User
                ? self::primerNombre($persona->name)
                : '—',
            'inicio_at' => $inicioAt?->toIso8601String(),
            'atencion_inicio_at' => $atencionInicioAt?->toIso8601String(),
            'atencion_en_curso' => $atencionInicioAt !== null && $atencion->fin_at === null,
            'espera_inicial_vencida' => $plazos !== null
                && $atencionInicioAt === null
                && $esperaExpiraAt !== null
                && $ahora->greaterThanOrEqualTo($esperaExpiraAt),
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
            'cliente_id' => $turno->cliente_id,
            'estado' => $turno->estado,
            'servicio' => $turno->servicio,
            'sucursal_id' => $turno->sucursal_id,
            'snapshot_nombre_llamado' => $turno->snapshot_nombre_llamado,
            'prioridad_diamante' => (bool) $turno->prioridad_diamante,
            'prioridad_vip' => (bool) $turno->prioridad_vip,
            'prioridad_adulto_mayor' => (bool) $turno->prioridad_adulto_mayor,
            'prioridad_discapacidad' => (bool) $turno->prioridad_discapacidad,
            'alta_at' => $turno->alta_at?->toIso8601String(),
            'espera_segundos' => $turno->alta_at !== null
                ? max(0, (int) $turno->alta_at->diffInSeconds($ahora))
                : null,
            'reatencion_expira_at' => $turno->reatencion_expira_at?->toIso8601String(),
            'reatencion_vigente' => $turno->estado === TurnoPdv::ESTADO_EN_REATENCION
                && $turno->reatencion_expira_at !== null
                && $ahora->lessThan($turno->reatencion_expira_at),
        ];
    }

    private static function primerNombre(?string $nombreCompleto): string
    {
        $partes = preg_split('/\s+/', trim((string) $nombreCompleto)) ?: [];

        return $partes[0] ?? '—';
    }
}
