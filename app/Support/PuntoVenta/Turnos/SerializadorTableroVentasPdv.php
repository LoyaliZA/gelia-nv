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
    public static function turno(
        TurnoPdv $turno,
        array $plazos,
        CarbonInterface $ahora,
        ?TurnoPdvAtencion $atencionPrevia = null,
        ?CarbonInterface $habilitarDesde = null,
    ): array {
        $atencion = $turno->relationLoaded('atencionActual')
            ? $turno->atencionActual
            : null;

        $marca = self::marcaDe($turno, $atencion);
        $esReatencion = $marca['marca_atencion'] === MarcaAtencionTurnoPdv::REATENCION;

        return array_merge(
            self::resumenTurno($turno, $ahora),
            $marca,
            [
                'es_reatencion' => $esReatencion,
                'atencion_previa' => $esReatencion && $atencionPrevia instanceof TurnoPdvAtencion
                    ? self::atencionPrevia($atencionPrevia)
                    : null,
                'version' => $turno->version,
                'atencion_actual_id' => $turno->atencion_actual_id,
                'atencion' => $atencion instanceof TurnoPdvAtencion
                    ? self::atencion($atencion, $plazos, $ahora, $habilitarDesde, $marca)
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
     * @param  array{marca_atencion?: string, folio_visible?: string, numero_reatencion?: int|null}|null  $marca
     * @return array<string, mixed>
     */
    public static function atencion(
        TurnoPdvAtencion $atencion,
        array $plazos,
        CarbonInterface $ahora,
        ?CarbonInterface $habilitarDesde = null,
        ?array $marca = null,
    ): array {
        $inicioAt = $atencion->inicio_at;
        $atencionInicioAt = $atencion->atencion_inicio_at;
        $enCooldown = $atencionInicioAt === null
            && $habilitarDesde instanceof CarbonInterface
            && $habilitarDesde->greaterThan($ahora);

        $baseEspera = $enCooldown ? $habilitarDesde : $inicioAt;
        $esperaExpiraAt = $baseEspera?->copy()->addMinutes($plazos['espera_inicial_minutos']);
        $prorrogaExpiraAt = $atencionInicioAt?->copy()->addMinutes($plazos['prorroga_minutos']);

        $prorrogaRegistrada = $atencion->relationLoaded('prorroga')
            ? $atencion->prorroga !== null
            : false;

        $prorrogaActiva = $prorrogaRegistrada
            || ($atencionInicioAt !== null && $prorrogaExpiraAt !== null && $ahora->greaterThanOrEqualTo($prorrogaExpiraAt));

        $marca ??= [
            'marca_atencion' => $atencion->es_transferencia
                ? MarcaAtencionTurnoPdv::TRANSFERENCIA
                : MarcaAtencionTurnoPdv::NORMAL,
            'folio_visible' => null,
            'numero_reatencion' => null,
        ];

        return [
            'id' => $atencion->id,
            'user_id' => $atencion->user_id,
            'numero_secuencia' => $atencion->numero_secuencia,
            'inicio_at' => $inicioAt?->toIso8601String(),
            'atencion_inicio_at' => $atencionInicioAt?->toIso8601String(),
            'atencion_habilitada_desde' => $enCooldown ? $habilitarDesde->toIso8601String() : null,
            'fin_at' => $atencion->fin_at?->toIso8601String(),
            'es_transferencia' => $atencion->es_transferencia,
            'marca_atencion' => $marca['marca_atencion'],
            'folio_visible' => $marca['folio_visible'],
            'numero_reatencion' => $marca['numero_reatencion'],
            'version' => $atencion->version,
            'espera_inicial_expira_at' => $esperaExpiraAt?->toIso8601String(),
            'espera_inicial_vencida' => ! $enCooldown
                && $atencionInicioAt === null
                && $esperaExpiraAt !== null
                && $ahora->greaterThanOrEqualTo($esperaExpiraAt),
            'prorroga_expira_at' => $prorrogaExpiraAt?->toIso8601String(),
            'prorroga_activa' => $prorrogaActiva,
            'prorroga_registrada' => $prorrogaRegistrada,
            'atencion_en_curso' => $atencionInicioAt !== null && $atencion->fin_at === null,
        ];
    }

    /**
     * @return array{cierre_at: string|null, motivo_cierre: string|null}
     */
    private static function atencionPrevia(TurnoPdvAtencion $atencion): array
    {
        return [
            'cierre_at' => $atencion->fin_at?->toIso8601String(),
            'motivo_cierre' => $atencion->motivo_cierre,
        ];
    }

    /**
     * @return array{marca_atencion: string, folio_visible: string, numero_reatencion: int|null}
     */
    private static function marcaDe(TurnoPdv $turno, ?TurnoPdvAtencion $atencion): array
    {
        if (! $atencion instanceof TurnoPdvAtencion) {
            return MarcaAtencionTurnoPdv::desde((string) $turno->folio, false, 0);
        }

        if (! $atencion->relationLoaded('turno')) {
            $atencion->setRelation('turno', $turno);
        }

        return MarcaAtencionTurnoPdv::paraAtenciones([$atencion])[(int) $atencion->id]
            ?? MarcaAtencionTurnoPdv::desde((string) $turno->folio, (bool) $atencion->es_transferencia, 0);
    }

    /**
     * @return array<string, mixed>
     */
    private static function resumenTurno(TurnoPdv $turno, CarbonInterface $ahora): array
    {
        $nombreVisible = IdentidadVisibleTurnoPdv::nombreVisible($turno);

        return [
            'id' => $turno->id,
            'folio' => $turno->folio,
            'estado' => $turno->estado,
            'servicio' => $turno->servicio,
            'sucursal_id' => $turno->sucursal_id,
            'snapshot_nombre_llamado' => $nombreVisible !== ''
                ? $nombreVisible
                : $turno->snapshot_nombre_llamado,
            'es_tercero' => IdentidadVisibleTurnoPdv::esTercero($turno),
            'cliente_titular' => IdentidadVisibleTurnoPdv::nombreTitular($turno),
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
