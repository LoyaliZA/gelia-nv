<?php

namespace App\Support\PuntoVenta\Reportes;

final class DefinicionesMetricaReportePdv
{
    /**
     * Definiciones de métricas ambiguas para la UI (contrato 7A §5).
     *
     * @return array<string, array{definicion: string, alcance: string}>
     */
    public static function todas(): array
    {
        return [
            MetricaResguardoPdvIds::R_04_REZAGADOS => [
                'definicion' => 'Resguardos sin recepción física que superan el plazo de rezago configurado.',
                'alcance' => 'Clasificación calculada al corte del reporte; no es un estado operativo.',
            ],
            MetricaResguardoPdvIds::R_05_PROXIMOS_VENCER => [
                'definicion' => 'Resguardos en custodia dentro de la ventana de aviso previo al vencimiento.',
                'alcance' => 'Usa plazos de custodia por sucursal; calculada al corte.',
            ],
            MetricaResguardoPdvIds::R_06_VENCIDOS => [
                'definicion' => 'Resguardos en custodia cuyo plazo de custodia ya venció.',
                'alcance' => 'Clasificación calculada; distinta del estado operativo.',
            ],
            MetricaResguardoPdvIds::R_09_TASA_ENTREGA => [
                'definicion' => 'Entregas completadas / resguardos elegibles para entrega en el rango.',
                'alcance' => 'Solo resguardos con recepción física registrada.',
            ],
            MetricaResguardoPdvIds::R_10_TASA_INCIDENCIA => [
                'definicion' => 'Resguardos con al menos una incidencia / total activos en el rango.',
                'alcance' => 'Incidencias abiertas o cerradas cuentan si ocurrieron en el periodo.',
            ],
            MetricaTurnoOperacionPdvIds::T_05_ESPERA_COLA => [
                'definicion' => 'Tiempo desde el alta del turno hasta la primera asignación.',
                'alcance' => 'Excluye bajas de cola sin asignación previa.',
            ],
            MetricaTurnoOperacionPdvIds::T_06_ESPERA_INICIAL => [
                'definicion' => 'Tiempo desde la asignación hasta el inicio real de atención.',
                'alcance' => 'Usa inicio_at y atencion_inicio_at; transferencias no reinician la métrica de la atención origen.',
            ],
            MetricaTurnoOperacionPdvIds::T_09_TASA_ABANDONO => [
                'definicion' => 'Bajas de cola o cierres por no se presentó / altas en el rango.',
                'alcance' => 'Excluye transferencias y turnos aún en cola sin baja al corte.',
            ],
            MetricaTurnoOperacionPdvIds::T_11_TASA_REATENCION => [
                'definicion' => 'Reatenciones válidas / atenciones cerradas con venta o sin venta.',
                'alcance' => 'Solo cuenta reatenciones dentro de la ventana configurada.',
            ],
            MetricaTurnoOperacionPdvIds::T_15_PERCENTILES_ESPERA_COLA => [
                'definicion' => 'Percentiles P50, P90 y P95 de la espera en cola.',
                'alcance' => 'Solo si hay al menos 30 registros cerrados; si no, se muestra promedio y conteo.',
            ],
            MetricaTurnoOperacionPdvIds::T_16_PERCENTILES_DURACION_ATENCION => [
                'definicion' => 'Percentiles P50, P90 y P95 de la duración de atención.',
                'alcance' => 'Solo si hay al menos 30 registros cerrados; excluye atenciones en curso.',
            ],
            MetricaTurnoOperacionPdvIds::O_06_OCUPACION => [
                'definicion' => 'Tiempo en atención (intervalos) / duración de jornada por persona.',
                'alcance' => 'Porcentaje 0–100; excluye jornadas con duración cero.',
            ],
            MetricaTurnoOperacionPdvIds::O_07_DISPONIBILIDAD => [
                'definicion' => 'Tiempo disponible / duración de jornada por persona.',
                'alcance' => 'Complementa ocupación y pausas dentro de la jornada.',
            ],
            MetricaTurnoOperacionPdvIds::O_11_ALTAS_DESPUES_CIERRE => [
                'definicion' => 'Altas de turno registradas después del horario de cierre sin ampliación vigente.',
                'alcance' => 'Debe ser cero en operación correcta; usa horario de cierre configurado.',
            ],
        ];
    }

    /**
     * @return array{definicion: string, alcance: string}|null
     */
    public static function para(string $metricaId): ?array
    {
        return self::todas()[$metricaId] ?? null;
    }
}
