<?php

namespace App\Support\PuntoVenta\Reportes;

final class EtiquetasMetricaReportePdv
{
    /**
     * @var array<string, string>
     */
    private const ETIQUETAS = [
        MetricaResguardoPdvIds::R_01_PENDIENTES_RECIBIR => 'Pendientes por recibir',
        MetricaResguardoPdvIds::R_02_EN_CUSTODIA => 'En custodia',
        MetricaResguardoPdvIds::R_03_INCIDENCIAS_ABIERTAS => 'Incidencias abiertas',
        MetricaResguardoPdvIds::R_04_REZAGADOS => 'Rezagados',
        MetricaResguardoPdvIds::R_05_PROXIMOS_VENCER => 'Próximos a vencer',
        MetricaResguardoPdvIds::R_06_VENCIDOS => 'Vencidos',
        MetricaResguardoPdvIds::R_07_TIEMPO_RECEPCION => 'Tiempo a recepción',
        MetricaResguardoPdvIds::R_08_TIEMPO_CUSTODIA => 'Tiempo en custodia',
        MetricaResguardoPdvIds::R_09_TASA_ENTREGA => 'Tasa de entrega',
        MetricaResguardoPdvIds::R_10_TASA_INCIDENCIA => 'Tasa de incidencia',
        MetricaResguardoPdvIds::R_11_RECEPCIONES_RANGO => 'Recepciones en rango',
        MetricaResguardoPdvIds::R_12_DEVOLUCIONES_RANGO => 'Devoluciones en rango',
        MetricaTurnoOperacionPdvIds::T_01_ALTAS => 'Altas de turno',
        MetricaTurnoOperacionPdvIds::T_02_ASIGNACIONES => 'Asignaciones',
        MetricaTurnoOperacionPdvIds::T_03_TURNOS_CERRADOS => 'Turnos cerrados',
        MetricaTurnoOperacionPdvIds::T_04_BAJAS_COLA => 'Bajas de cola',
        MetricaTurnoOperacionPdvIds::T_05_ESPERA_COLA => 'Espera en cola',
        MetricaTurnoOperacionPdvIds::T_06_ESPERA_INICIAL => 'Espera inicial',
        MetricaTurnoOperacionPdvIds::T_07_DURACION_ATENCION => 'Duración de atención',
        MetricaTurnoOperacionPdvIds::T_08_CICLO_TOTAL => 'Ciclo total',
        MetricaTurnoOperacionPdvIds::T_09_TASA_ABANDONO => 'Tasa de abandono',
        MetricaTurnoOperacionPdvIds::T_10_TASA_NO_SE_PRESENTO => 'Tasa no se presentó',
        MetricaTurnoOperacionPdvIds::T_11_TASA_REATENCION => 'Tasa de reatención',
        MetricaTurnoOperacionPdvIds::T_12_TRANSFERENCIAS => 'Transferencias',
        MetricaTurnoOperacionPdvIds::T_13_PRORROGAS => 'Prórrogas',
        MetricaTurnoOperacionPdvIds::T_14_DISTRIBUCION_PRIORIDAD => 'Distribución por prioridad',
        MetricaTurnoOperacionPdvIds::T_15_PERCENTILES_ESPERA_COLA => 'Percentiles espera en cola',
        MetricaTurnoOperacionPdvIds::T_16_PERCENTILES_DURACION_ATENCION => 'Percentiles duración atención',
        MetricaTurnoOperacionPdvIds::O_01_JORNADAS_ABIERTAS => 'Jornadas abiertas',
        MetricaTurnoOperacionPdvIds::O_02_DURACION_JORNADA => 'Duración de jornada',
        MetricaTurnoOperacionPdvIds::O_03_TIEMPO_PAUSA => 'Tiempo en pausa',
        MetricaTurnoOperacionPdvIds::O_04_TIEMPO_DISPONIBLE => 'Tiempo disponible',
        MetricaTurnoOperacionPdvIds::O_05_TIEMPO_ATENCION_INTERVALOS => 'Tiempo en atención (intervalos)',
        MetricaTurnoOperacionPdvIds::O_06_OCUPACION => 'Ocupación',
        MetricaTurnoOperacionPdvIds::O_07_DISPONIBILIDAD => 'Disponibilidad',
        MetricaTurnoOperacionPdvIds::O_08_DIAS_SIN_ALTAS => 'Días sin altas',
        MetricaTurnoOperacionPdvIds::O_09_CIERRES_HORARIO => 'Cierres por horario',
        MetricaTurnoOperacionPdvIds::O_10_AMPLIACIONES_HORARIO => 'Ampliaciones de horario',
        MetricaTurnoOperacionPdvIds::O_11_ALTAS_DESPUES_CIERRE => 'Altas después del cierre',
    ];

    public static function etiqueta(string $metricaId): string
    {
        return self::ETIQUETAS[$metricaId] ?? $metricaId;
    }

    public static function seccion(string $metricaId): string
    {
        if (str_starts_with($metricaId, 'R-')) {
            return 'resguardos';
        }

        if (str_starts_with($metricaId, 'T-')) {
            return 'turnos';
        }

        return 'operacion';
    }
}
