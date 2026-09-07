<?php

namespace App\Services\PuntoVenta\Reportes;

use Carbon\Carbon;

class TituloExportacionReportePdvService
{
    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function desdeFiltros(array $filtros): string
    {
        $tipo = ReportePdvExportacionTipo::desdeFiltros($filtros);
        $formato = (string) ($filtros['formato'] ?? ReportePdvExportacionFormato::CSV);
        $etiquetaTipo = match ($tipo) {
            ReportePdvExportacionTipo::RESGUARDOS => 'Resguardos',
            ReportePdvExportacionTipo::TURNOS_OPERACION => 'Turnos y operación',
            default => 'Conjunto',
        };
        $etiquetaFormato = $formato === ReportePdvExportacionFormato::PDF ? 'PDF' : 'CSV';

        $desde = self::formatearFecha($filtros['desde'] ?? null);
        $hasta = self::formatearFecha($filtros['hasta'] ?? null);

        if ($desde !== '' && $hasta !== '') {
            return "Reporte {$etiquetaTipo} ({$etiquetaFormato}) {$desde} – {$hasta}";
        }

        return "Reporte {$etiquetaTipo} ({$etiquetaFormato})";
    }

    private static function formatearFecha(mixed $valor): string
    {
        if ($valor === null || $valor === '') {
            return '';
        }

        try {
            return Carbon::parse((string) $valor)->timezone(config('app.timezone'))->format('Y-m-d');
        } catch (\Throwable) {
            return (string) $valor;
        }
    }
}
