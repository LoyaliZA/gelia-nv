<?php

namespace App\Services\PuntoVenta\Reportes;

use App\Models\User;
use App\Services\PuntoVenta\Reportes\Resguardos\CalcularMetricasReporteResguardoPdvService;
use App\Services\PuntoVenta\Reportes\TurnosOperacion\CalcularMetricasReporteTurnoOperacionPdvService;

class ObtenerPayloadMetricasReportePdvService
{
    public function __construct(
        private readonly CalcularMetricasReporteResguardoPdvService $metricasResguardos,
        private readonly CalcularMetricasReporteTurnoOperacionPdvService $metricasTurnosOperacion,
    ) {}

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{
     *   resguardos?: array<string, mixed>,
     *   turnos_operacion?: array<string, mixed>
     * }
     */
    public function ejecutar(User $usuario, array $filtros): array
    {
        $tipo = ReportePdvExportacionTipo::desdeFiltros($filtros);
        $payload = [];

        if (in_array($tipo, [ReportePdvExportacionTipo::RESGUARDOS, ReportePdvExportacionTipo::CONJUNTO], true)) {
            $payload['resguardos'] = $this->metricasResguardos->ejecutar($usuario, $this->filtrosResguardos($filtros));
        }

        if (in_array($tipo, [ReportePdvExportacionTipo::TURNOS_OPERACION, ReportePdvExportacionTipo::CONJUNTO], true)) {
            $payload['turnos_operacion'] = $this->metricasTurnosOperacion->ejecutar(
                $usuario,
                $this->filtrosTurnosOperacion($filtros)
            );
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    private function filtrosResguardos(array $filtros): array
    {
        return array_filter([
            'desde' => $filtros['desde'] ?? null,
            'hasta' => $filtros['hasta'] ?? null,
            'corte_reporte_at' => $filtros['corte_reporte_at'] ?? null,
            'sucursal_id' => $filtros['sucursal_id'] ?? null,
            'estado' => $filtros['estado'] ?? null,
            'antiguedad' => $filtros['antiguedad'] ?? null,
            'tipo_incidencia' => $filtros['tipo_incidencia'] ?? null,
        ], static fn ($valor) => $valor !== null && $valor !== '');
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    private function filtrosTurnosOperacion(array $filtros): array
    {
        return array_filter([
            'desde' => $filtros['desde'] ?? null,
            'hasta' => $filtros['hasta'] ?? null,
            'corte_reporte_at' => $filtros['corte_reporte_at'] ?? null,
            'sucursal_id' => $filtros['sucursal_id'] ?? null,
            'servicio' => $filtros['servicio'] ?? null,
            'user_id' => $filtros['user_id'] ?? null,
            'fecha_operativa' => $filtros['fecha_operativa'] ?? null,
            'franja_desde' => $filtros['franja_desde'] ?? null,
            'franja_hasta' => $filtros['franja_hasta'] ?? null,
            'alcance' => $filtros['alcance'] ?? null,
        ], static fn ($valor) => $valor !== null && $valor !== '');
    }
}
