<?php

namespace App\Http\Controllers\Rh;

use App\Http\Controllers\Controller;
use App\Models\RhColaborador;
use App\Models\RhConfiguracion;
use App\Models\RhHorasExtra;
use App\Services\Rh\CalcularConsolidadoHorasExtraService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ConsolidadoHorasExtraController extends Controller
{
    public function index(Request $request, CalcularConsolidadoHorasExtraService $calcularService): Response
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

        return Inertia::render('Rh/ConsolidadoHorasExtra/Index', [
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
            'puedeLiquidar' => Auth::user()->can('rh.periodo_pago.sellar') || Auth::user()->can('rh.ver'),
        ]);
    }

    public function liquidar(Request $request): RedirectResponse
    {
        abort_unless(Auth::user()->can('rh.periodo_pago.sellar') || Auth::user()->can('rh.ver') || Auth::user()->hasRole('Super Admin'), 403);

        $request->validate([
            'fecha_fin' => 'required|date',
            'rh_colaborador_id' => 'nullable|integer|exists:rh_colaboradores,id',
        ]);

        $fechaFin = Carbon::parse($request->input('fecha_fin'))->toDateString();
        $colaboradorId = $request->input('rh_colaborador_id');
        $fechaPago = now()->toDateString();

        $query = RhHorasExtra::query()
            ->whereNull('fecha_programada_pago')
            ->where('fecha_turno', '<=', $fechaFin);

        if ($colaboradorId) {
            $query->where('rh_colaborador_id', $colaboradorId);
        }

        $query->update([
            'fecha_programada_pago' => $fechaPago,
            'estado_pago' => 'programado',
        ]);

        return redirect()->back()->with('success', 'Las horas extra del periodo han sido liquidadas exitosamente.');
    }
}
