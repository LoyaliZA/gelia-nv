<?php

namespace App\Http\Controllers\Rh;

use App\Http\Controllers\Controller;
use App\Models\RhColaborador;
use App\Models\RhConfiguracion;
use App\Services\Rh\CalcularConsolidadoDeduccionesService;
use App\Services\Rh\SellarConsolidadoDeduccionesService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ConsolidadoDeduccionesController extends Controller
{
    public function index(Request $request, CalcularConsolidadoDeduccionesService $calcularService): Response
    {
        abort_unless(Auth::user()->can('rh.periodo_pago.ver') || Auth::user()->can('rh.ver'), 403);

        $config = RhConfiguracion::obtener();
        $diasPeriodo = max(1, (int) $config->dias_periodo_pago);

        $fechaFin = $request->filled('fecha_fin')
            ? Carbon::parse($request->input('fecha_fin'))
            : now();

        $fechaInicio = $request->filled('fecha_inicio')
            ? Carbon::parse($request->input('fecha_inicio'))
            : $fechaFin->copy()->subDays($diasPeriodo - 1);

        $colaboradorId = $request->input('rh_colaborador_id');

        return Inertia::render('Rh/ConsolidadoDeducciones/Index', [
            'resumen' => $calcularService->ejecutar($fechaInicio, $fechaFin, $colaboradorId ? (int) $colaboradorId : null),
            'colaboradores' => RhColaborador::where('activo', true)
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'apellido_paterno', 'apellido_materno', 'folio']),
            'configuracion' => $config,
            'filtros' => [
                'fecha_inicio' => $fechaInicio->toDateString(),
                'fecha_fin' => $fechaFin->toDateString(),
                'rh_colaborador_id' => $colaboradorId,
            ],
        ]);
    }

    public function sellarPeriodo(Request $request, SellarConsolidadoDeduccionesService $sellarService): \Illuminate\Http\RedirectResponse
    {
        abort_unless(Auth::user()->can('rh.periodo_pago.ver') || Auth::user()->can('rh.ver'), 403);

        $datos = $request->validate([
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date',
        ]);

        $fechaInicio = Carbon::parse($datos['fecha_inicio']);
        $fechaFin = Carbon::parse($datos['fecha_fin']);

        $afectados = $sellarService->ejecutar($fechaInicio, $fechaFin, Auth::user());

        return back()->with('success', "Se cerró el periodo correctamente. Se aplicaron {$afectados} deducciones.");
    }
}
