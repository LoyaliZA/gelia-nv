<?php

namespace App\Support\PuntoVenta\Reportes;

final class ColumnasExportacionReportePdv
{
    /**
     * Contrato de columnas del CSV de métricas PDV.
     *
     * @return array<string, string>
     */
    public static function metricas(): array
    {
        return [
            'seccion' => 'Sección',
            'metrica_id' => 'Métrica',
            'etiqueta' => 'Descripción',
            'sucursal_id' => 'Sucursal ID',
            'unidad' => 'Unidad',
            'valor' => 'Valor',
            'conteo' => 'Conteo',
            'promedio_segundos' => 'Promedio (s)',
            'p50' => 'P50 (s)',
            'p90' => 'P90 (s)',
            'p95' => 'P95 (s)',
            'en_curso' => 'En curso',
            'detalle' => 'Detalle',
        ];
    }

    /**
     * Contrato de columnas de parámetros del reporte en CSV.
     *
     * @return array<string, string>
     */
    public static function parametros(): array
    {
        return [
            'clave' => 'Parámetro',
            'valor' => 'Valor',
        ];
    }

    /**
     * Secciones del PDF de métricas PDV.
     *
     * @return list<string>
     */
    public static function seccionesPdf(): array
    {
        return [
            'parametros',
            'resguardos',
            'turnos',
            'operacion',
            'desglose_sucursal',
        ];
    }
}
