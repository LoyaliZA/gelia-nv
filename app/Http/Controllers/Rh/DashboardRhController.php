<?php

namespace App\Http\Controllers\Rh;

use App\Http\Controllers\Controller;
use App\Models\Departamento;
use App\Models\RhColaborador;
use App\Services\Rh\ResumenDashboardRhService;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DashboardRhController extends Controller
{
    public function index(ResumenDashboardRhService $resumenService): Response
    {
        $resumen = $resumenService->ejecutar();

        return Inertia::render('Rh/Dashboard/Index', [
            'metricas' => $resumen['metricas'],
            'configuracion' => $resumen['configuracion'],
            'ultimosRegistros' => $resumen['ultimos_registros'],
            'ultimasDeducciones' => $resumen['ultimas_deducciones'],
            'ultimasIncidencias' => $resumen['ultimas_deducciones'],
            'puedeColaboradores' => Auth::user()->can('rh.ver'),
            'puedeHorasExtra' => Auth::user()->can('rh.horas_extra.ver'),
            'puedeIncidencias' => Auth::user()->can('rh.incidencias.ver'),
            'puedeConfigurar' => Auth::user()->can('rh.configurar'),
            'puedeCrearHe' => Auth::user()->can('rh.horas_extra.crear'),
            'puedeCrearIncidencia' => Auth::user()->can('rh.incidencias.crear'),
        ]);
    }
}
