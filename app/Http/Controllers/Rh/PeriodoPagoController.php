<?php

namespace App\Http\Controllers\Rh;

use App\Http\Controllers\Controller;
use App\Models\RhColaborador;
use App\Models\RhConfiguracion;
use App\Services\Rh\CalcularPeriodoPagoService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class PeriodoPagoController extends Controller
{
    public function index(Request $request, CalcularPeriodoPagoService $calcularService): Response
    {
        abort_unless(Auth::user()->can('rh.periodo_pago.ver') || Auth::user()->can('rh.ver'), 403);

        $config = RhConfiguracion::obtener();
        $diasPeriodo = $request->filled('dias_periodo')
            ? max(1, (int) $request->input('dias_periodo'))
            : max(1, (int) $config->dias_periodo_pago);

        $fechaInicioGlobal = $config->periodo_actual_inicio ? Carbon::parse($config->periodo_actual_inicio) : now()->startOfMonth();
        $fechaFinGlobal = $config->periodo_actual_fin ? Carbon::parse($config->periodo_actual_fin) : now()->endOfMonth();

        $fechaInicio = $request->filled('fecha_inicio')
            ? Carbon::parse($request->input('fecha_inicio'))
            : $fechaInicioGlobal;

        $fechaFin = $request->filled('fecha_fin')
            ? Carbon::parse($request->input('fecha_fin'))
            : $fechaFinGlobal;

        $colaboradorId = $request->input('rh_colaborador_id');

        return Inertia::render('Rh/PeriodoPago/Index', [
            'resumen' => $calcularService->ejecutar($fechaInicio, $fechaFin, $colaboradorId ? (int) $colaboradorId : null),
            'comisionesAuditor' => $calcularService->comisionesAuditor($fechaInicio, $fechaFin),
            'colaboradores' => RhColaborador::where('activo', true)->orderBy('nombre')->get(['id', 'nombre', 'apellido_paterno', 'apellido_materno', 'folio']),
            'configuracion' => $config,
            'filtros' => [
                'fecha_inicio' => $fechaInicio->toDateString(),
                'fecha_fin' => $fechaFin->toDateString(),
                'rh_colaborador_id' => $colaboradorId,
            ],
            'puedeGenerarCuotas' => Auth::user()->can('rh.prestamos.generar'),
            'puedeRecibos' => Auth::user()->can('rh.recibos.ver') || Auth::user()->can('rh.recibos.generar'),
            'puedeGenerarRecibos' => Auth::user()->can('rh.recibos.ver') || Auth::user()->can('rh.recibos.generar'),
        ]);
    }
}
