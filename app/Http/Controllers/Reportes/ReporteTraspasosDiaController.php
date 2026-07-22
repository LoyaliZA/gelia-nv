<?php

namespace App\Http\Controllers\Reportes;

use App\Http\Controllers\Controller;
use App\Services\Traspasos\ExportarReporteTraspasosDiaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ReporteTraspasosDiaController extends Controller
{
    public function index(Request $request, ExportarReporteTraspasosDiaService $exportar): Response
    {
        Gate::authorize('traspasos.reporte_dia');

        $fecha = $request->input('fecha', now()->addDay()->toDateString());

        $traspasos = $exportar->consulta(Auth::user(), $fecha);

        return Inertia::render('Reportes/TraspasosDia/Index', [
            'traspasos' => $traspasos,
            'fecha' => $fecha,
        ]);
    }

    public function exportar(Request $request, ExportarReporteTraspasosDiaService $exportar)
    {
        Gate::authorize('traspasos.reporte_dia');

        $fecha = $request->input('fecha', now()->addDay()->toDateString());
        $formato = $request->input('formato', 'xlsx') === 'csv' ? 'csv' : 'xlsx';

        return $exportar->exportar(Auth::user(), $fecha, $formato);
    }
}
