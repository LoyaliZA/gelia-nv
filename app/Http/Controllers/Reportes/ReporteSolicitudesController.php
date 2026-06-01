<?php

namespace App\Http\Controllers\Reportes;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Solicitudes\ExportarReporteSolicitudesService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ReporteSolicitudesController extends Controller
{
    public function index(Request $request, ExportarReporteSolicitudesService $exportService): Response
    {
        Gate::authorize('solicitudes.exportar');

        $filtros = $request->all();
        $total = $exportService->contar(Auth::user(), $filtros);

        $vendedores = User::whereHas('roles', function ($q) {
            $q->whereIn('name', ['colaborador', 'Administrador', 'Super Admin']);
        })->orderBy('name')->get(['id', 'name']);

        return Inertia::render('Reportes/Solicitudes/Index', [
            'filtros' => $filtros,
            'total' => $total,
            'vendedores' => $vendedores,
        ]);
    }

    public function exportar(Request $request, ExportarReporteSolicitudesService $exportService)
    {
        Gate::authorize('solicitudes.exportar');

        $formato = strtolower($request->query('format', 'xlsx'));
        $filtros = $request->except(['format']);

        return match ($formato) {
            'pdf' => $exportService->descargarPdf(Auth::user(), $filtros),
            'csv' => $exportService->descargarCsv(Auth::user(), $filtros),
            default => $exportService->descargarExcel(Auth::user(), $filtros),
        };
    }
}
