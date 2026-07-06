<?php

namespace App\Http\Controllers\Rh;

use App\Http\Controllers\Controller;
use App\Models\RhColaborador;
use App\Models\RhConfiguracion;
use App\Models\RhReciboNomina;
use App\Services\Rh\CalcularPeriodoPagoService;
use App\Services\Rh\CerrarPeriodoPagoService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
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

        $periodoCerrado = $config->periodo_cerrado_en
            && $config->periodo_cerrado_inicio?->toDateString() === $fechaInicio->toDateString()
            && $config->periodo_cerrado_fin?->toDateString() === $fechaFin->toDateString();

        $resumen = $calcularService->ejecutar($fechaInicio, $fechaFin, $colaboradorId ? (int) $colaboradorId : null);

        $colaboradorIds = collect($resumen['filas'] ?? [])->pluck('colaborador.id')->filter()->all();
        $firmados = RhReciboNomina::query()
            ->whereIn('rh_colaborador_id', $colaboradorIds)
            ->where('fecha_inicio', $fechaInicio->toDateString())
            ->where('fecha_fin', $fechaFin->toDateString())
            ->whereNotNull('firma_colaborador_path')
            ->pluck('rh_colaborador_id')
            ->flip();

        $resumen['filas'] = collect($resumen['filas'] ?? [])->map(function (array $fila) use ($firmados) {
            $id = $fila['colaborador']['id'] ?? null;
            $fila['recibo_nomina_firmado'] = $id !== null && isset($firmados[$id]);

            return $fila;
        })->values()->all();

        return Inertia::render('Rh/PeriodoPago/Index', [
            'resumen' => $resumen,
            'comisionesAuditor' => $calcularService->comisionesAuditor($fechaInicio, $fechaFin),
            'colaboradores' => RhColaborador::where('activo', true)->orderBy('nombre')->get(['id', 'nombre', 'apellido_paterno', 'apellido_materno', 'folio']),
            'configuracion' => $config,
            'filtros' => [
                'fecha_inicio' => $fechaInicio->toDateString(),
                'fecha_fin' => $fechaFin->toDateString(),
                'dias_periodo' => $diasPeriodo,
                'rh_colaborador_id' => $colaboradorId,
            ],
            'puedeGenerarCuotas' => Auth::user()->can('rh.prestamos.generar'),
            'puedeSellarSalidas' => Auth::user()->can('rh.salidas_personales.sellar'),
            'puedeRecibos' => Auth::user()->can('rh.recibos.ver') || Auth::user()->can('rh.recibos.generar'),
            'puedeGenerarRecibos' => Auth::user()->can('rh.recibos.ver') || Auth::user()->can('rh.recibos.generar'),
            'puedeCerrarPeriodo' => Auth::user()->can('rh.periodo_pago.sellar') || Auth::user()->can('rh.ver'),
            'periodoCerrado' => $periodoCerrado,
            'periodoCerradoEn' => $periodoCerrado ? $config->periodo_cerrado_en?->toIso8601String() : null,
        ]);
    }

    public function cerrar(Request $request, CerrarPeriodoPagoService $cerrarService): RedirectResponse
    {
        abort_unless(Auth::user()->can('rh.periodo_pago.sellar') || Auth::user()->can('rh.ver'), 403);

        $datos = $request->validate([
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
            'fecha_pago' => 'required|date|before_or_equal:fecha_fin',
            'forzar' => 'nullable|boolean',
        ]);

        $fechaInicio = Carbon::parse($datos['fecha_inicio']);
        $fechaFin = Carbon::parse($datos['fecha_fin']);
        $fechaPago = Carbon::parse($datos['fecha_pago']);

        $resultado = $cerrarService->ejecutar(
            $fechaInicio,
            $fechaFin,
            $fechaPago,
            Auth::user(),
            (bool) ($datos['forzar'] ?? false),
        );

        return back()->with('success', sprintf(
            'Periodo cerrado. Deducciones: %d · Salidas: %d · Horas extra: %d.',
            $resultado['deducciones'],
            $resultado['salidas'],
            $resultado['horas_extra'],
        ));
    }
}
