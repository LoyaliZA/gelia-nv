<?php

namespace App\Services\Rh;

use App\Models\RhColaborador;
use App\Models\RhConfiguracion;
use App\Models\RhDeduccion;
use App\Models\RhHorasExtra;

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
        $montoPendiente = RhHorasExtra::where('estado_pago', 'pendiente')->sum('monto_horas_extra');
        $programadasProximas = RhHorasExtra::where('estado_pago', 'programado')
            ->whereNotNull('fecha_programada_pago')
            ->whereBetween('fecha_programada_pago', [$hoy, $enSieteDias])
            ->count();

        $ultimosRegistros = RhHorasExtra::with(['colaborador', 'supervisor'])
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        $deduccionesHoy = RhDeduccion::whereDate('fecha_ocurrencia', $hoy)->count();
        $pendientesDeduccion = RhDeduccion::whereIn('estado_deduccion', [
            RhDeduccion::ESTADO_PENDIENTE_NOMINA,
            RhDeduccion::ESTADO_PENDIENTE_COMISION,
        ])->count();
        $montoDeduccionPendiente = RhDeduccion::whereIn('estado_deduccion', [
            RhDeduccion::ESTADO_PENDIENTE_NOMINA,
            RhDeduccion::ESTADO_PENDIENTE_COMISION,
        ])->sum('total_deduccion');

        $ultimasDeducciones = RhDeduccion::with(['colaborador', 'reglaIncidencia'])
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
                'deducciones_hoy' => $deduccionesHoy,
                'incidencias_hoy' => $deduccionesHoy,
                'pendientes_deduccion' => $pendientesDeduccion,
                'monto_deduccion_pendiente' => (int) $montoDeduccionPendiente,
            ],
            'ultimos_registros' => $ultimosRegistros,
            'ultimas_deducciones' => $ultimasDeducciones,
            'ultimas_incidencias' => $ultimasDeducciones,
        ];
    }

    public function widget(): array
    {
        $pendientesHe = RhHorasExtra::where('estado_pago', 'pendiente')->count();
        $montoPendienteHe = RhHorasExtra::where('estado_pago', 'pendiente')->sum('monto_horas_extra');

        $pendientesDed = RhDeduccion::whereIn('estado_deduccion', [
            RhDeduccion::ESTADO_PENDIENTE_NOMINA,
            RhDeduccion::ESTADO_PENDIENTE_COMISION,
        ])->count();
        $montoDeduccion = RhDeduccion::whereIn('estado_deduccion', [
            RhDeduccion::ESTADO_PENDIENTE_NOMINA,
            RhDeduccion::ESTADO_PENDIENTE_COMISION,
        ])->sum('total_deduccion');

        $destacadosHe = RhHorasExtra::with('colaborador')
            ->where('estado_pago', 'pendiente')
            ->orderByDesc('fecha_turno')
            ->limit(3)
            ->get();

        $destacadosDed = RhDeduccion::with('colaborador')
            ->whereIn('estado_deduccion', [
                RhDeduccion::ESTADO_PENDIENTE_NOMINA,
                RhDeduccion::ESTADO_PENDIENTE_COMISION,
            ])
            ->orderByDesc('fecha_ocurrencia')
            ->limit(3)
            ->get();

        return [
            'pendientes_he' => $pendientesHe,
            'monto_pendiente_he' => round((float) $montoPendienteHe, 2),
            'pendientes_deducciones' => $pendientesDed,
            'pendientes_incidencias' => $pendientesDed,
            'monto_deduccion_deducciones' => (int) $montoDeduccion,
            'monto_deduccion_incidencias' => (int) $montoDeduccion,
            'destacados_he' => $destacadosHe,
            'destacados_deducciones' => $destacadosDed,
            'destacados_incidencias' => $destacadosDed,
            'pendientes' => $pendientesHe,
            'monto_pendiente' => round((float) $montoPendienteHe, 2),
            'destacados' => $destacadosHe,
        ];
    }
}
