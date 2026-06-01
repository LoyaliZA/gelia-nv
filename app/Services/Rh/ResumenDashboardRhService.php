<?php

namespace App\Services\Rh;

use App\Models\RhColaborador;
use App\Models\RhConfiguracion;
use App\Models\RhHorasExtra;
use App\Models\RhIncidencia;

class ResumenDashboardRhService
{
    public function ejecutar(): array
    {
        $config = RhConfiguracion::obtener();
        $hoy = now()->toDateString();
        $enSieteDias = now()->addDays(7)->toDateString();

        $colaboradoresActivos = RhColaborador::where('activo', true)->count();
        $registrosHoy = RhHorasExtra::whereDate('fecha_turno', $hoy)->count();
        $pendientesPago = RhHorasExtra::where('estado_pago', 'pendiente')->count();
        $montoPendiente = RhHorasExtra::where('estado_pago', 'pendiente')->sum('total_economico');
        $programadasProximas = RhHorasExtra::where('estado_pago', 'programado')
            ->whereNotNull('fecha_programada_pago')
            ->whereBetween('fecha_programada_pago', [$hoy, $enSieteDias])
            ->count();

        $ultimosRegistros = RhHorasExtra::with(['colaborador', 'supervisor'])
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        $incidenciasHoy = RhIncidencia::whereDate('fecha_ocurrencia', $hoy)->count();
        $pendientesDeduccion = RhIncidencia::whereIn('estado_deduccion', ['pendiente', 'programado'])->count();
        $montoDeduccionPendiente = RhIncidencia::whereIn('estado_deduccion', ['pendiente', 'programado'])->sum('total_deduccion');

        $ultimasIncidencias = RhIncidencia::with(['colaborador', 'tipoFalta'])
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        return [
            'configuracion' => $config,
            'metricas' => [
                'colaboradores_activos' => $colaboradoresActivos,
                'registros_hoy' => $registrosHoy,
                'pendientes_pago' => $pendientesPago,
                'monto_pendiente' => round((float) $montoPendiente, 2),
                'programadas_proximas' => $programadasProximas,
                'incidencias_hoy' => $incidenciasHoy,
                'pendientes_deduccion' => $pendientesDeduccion,
                'monto_deduccion_pendiente' => (int) $montoDeduccionPendiente,
            ],
            'ultimos_registros' => $ultimosRegistros,
            'ultimas_incidencias' => $ultimasIncidencias,
        ];
    }

    public function widget(): array
    {
        $pendientesHe = RhHorasExtra::where('estado_pago', 'pendiente')->count();
        $montoPendienteHe = RhHorasExtra::where('estado_pago', 'pendiente')->sum('total_economico');

        $pendientesInc = RhIncidencia::whereIn('estado_deduccion', ['pendiente', 'programado'])->count();
        $montoDeduccionInc = RhIncidencia::whereIn('estado_deduccion', ['pendiente', 'programado'])->sum('total_deduccion');

        $destacadosHe = RhHorasExtra::with('colaborador')
            ->where('estado_pago', 'pendiente')
            ->orderByDesc('fecha_turno')
            ->limit(3)
            ->get();

        $destacadosInc = RhIncidencia::with('colaborador')
            ->whereIn('estado_deduccion', ['pendiente', 'programado'])
            ->orderByDesc('fecha_ocurrencia')
            ->limit(3)
            ->get();

        return [
            'pendientes_he' => $pendientesHe,
            'monto_pendiente_he' => round((float) $montoPendienteHe, 2),
            'pendientes_incidencias' => $pendientesInc,
            'monto_deduccion_incidencias' => (int) $montoDeduccionInc,
            'destacados_he' => $destacadosHe,
            'destacados_incidencias' => $destacadosInc,
            // Compatibilidad con widget anterior
            'pendientes' => $pendientesHe,
            'monto_pendiente' => round((float) $montoPendienteHe, 2),
            'destacados' => $destacadosHe,
        ];
    }
}
