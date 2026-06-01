<?php

namespace App\Http\Controllers\Rh;

use App\Http\Controllers\Controller;
use App\Models\RhColaborador;
use App\Models\RhConfiguracion;
use App\Services\Rh\CalcularConsolidadoDeduccionesService;
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
}
