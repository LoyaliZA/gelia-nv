<?php

namespace App\Services\PuntoVenta\Reportes;

use Carbon\Carbon;

class EstimarExportacionReportePdvService
{
    /**
     * @param  array<string, mixed>  $filtros
     */
    public function esPesado(array $filtros): bool
    {
        $formato = (string) ($filtros['formato'] ?? ReportePdvExportacionFormato::CSV);
        $tipo = ReportePdvExportacionTipo::desdeFiltros($filtros);

        if ($formato === ReportePdvExportacionFormato::PDF) {
            return true;
        }

        if ($tipo === ReportePdvExportacionTipo::CONJUNTO) {
            return true;
        }

        $dias = $this->diasRango($filtros);
        $umbral = (int) config('punto_venta.reportes.exportacion.pesado_dias_rango', 31);

        return $dias > $umbral;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function diasRango(array $filtros): int
    {
        try {
            $desde = Carbon::parse((string) ($filtros['desde'] ?? now()->startOfMonth()));
            $hasta = Carbon::parse((string) ($filtros['hasta'] ?? now()->addSecond()));

            return max(1, (int) $desde->diffInDays($hasta));
        } catch (\Throwable) {
            return 1;
        }
    }
}
