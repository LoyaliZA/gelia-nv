<?php

namespace App\Http\Controllers\Rh;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rh\StoreSalidaPersonalRequest;
use App\Http\Requests\Rh\UpdateSalidaPersonalRequest;
use App\Models\Departamento;
use App\Models\RhColaborador;
use App\Models\RhConfiguracion;
use App\Models\RhSalidaPersonal;
use App\Services\Rh\ActualizarSalidaPersonalService;
use App\Services\Rh\CalcularSalidaPersonalService;
use App\Services\Rh\CrearSalidaPersonalService;
use App\Services\Rh\ListarSalidasPersonalesService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class SalidaPersonalController extends Controller
{
    public function index(Request $request, ListarSalidasPersonalesService $listarService): Response
    {
        abort_unless(Auth::user()->can('rh.salidas_personales.ver'), 403);

        $filtros = $request->only([
            'busqueda', 'rh_colaborador_id', 'departamento_id', 'area_id',
            'fecha_inicio', 'fecha_fin', 'estado_cobro',
        ]);

        return Inertia::render('Rh/SalidasPersonales/Index', [
            'registros' => $listarService->ejecutar($filtros),
            'metricas' => $listarService->metricas($filtros),
            'colaboradores' => RhColaborador::where('activo', true)
                ->with(['departamento', 'area'])
                ->orderBy('nombre')
                ->get(),
            'departamentos' => Departamento::where('activo', true)->with('areas')->orderBy('nombre')->get(),
            'configuracion' => RhConfiguracion::obtener(),
            'filtros' => $filtros,
            'puedeCrear' => Auth::user()->can('rh.salidas_personales.crear'),
            'puedeEditar' => Auth::user()->can('rh.salidas_personales.editar'),
            'puedeEliminar' => Auth::user()->can('rh.salidas_personales.eliminar'),
            'puedeSellar' => Auth::user()->can('rh.salidas_personales.sellar'),
        ]);
    }

    public function show(RhSalidaPersonal $salidaPersonal): Response
    {
        abort_unless(Auth::user()->can('rh.salidas_personales.ver'), 403);

        $salidaPersonal->load(['colaborador.departamento', 'colaborador.area', 'registradoPor']);

        return Inertia::render('Rh/SalidasPersonales/Show', [
            'registro' => $salidaPersonal,
            'puedeEditar' => Auth::user()->can('rh.salidas_personales.editar') && $salidaPersonal->fecha_deduccion_nomina === null,
            'colaboradores' => RhColaborador::where('activo', true)->with(['departamento', 'area'])->orderBy('nombre')->get(),
        ]);
    }

    public function store(StoreSalidaPersonalRequest $request, CrearSalidaPersonalService $crearService): RedirectResponse
    {
        $registro = $crearService->ejecutar($request->user(), $request->validated());

        return redirect()
            ->route('rh.salidas_personales.show', $registro)
            ->with('success', "Salida {$registro->folio} registrada correctamente.");
    }

    public function update(
        UpdateSalidaPersonalRequest $request,
        RhSalidaPersonal $salidaPersonal,
        ActualizarSalidaPersonalService $actualizarService,
    ): RedirectResponse {
        $registro = $actualizarService->ejecutar($request->user(), $salidaPersonal, $request->validated());

        return back()->with('success', "Salida {$registro->folio} actualizada correctamente.");
    }

    public function destroy(RhSalidaPersonal $salidaPersonal): RedirectResponse
    {
        abort_unless(Auth::user()->can('rh.salidas_personales.eliminar'), 403);

        if ($salidaPersonal->fecha_deduccion_nomina !== null) {
            return back()->with('error', 'No se puede eliminar una salida ya cobrada en nómina.');
        }

        $salidaPersonal->delete();

        return redirect()
            ->route('rh.salidas_personales.index')
            ->with('success', 'Registro de salida personal eliminado.');
    }

    public function previewCalculos(Request $request, CalcularSalidaPersonalService $calcularService): JsonResponse
    {
        abort_unless(Auth::user()->can('rh.salidas_personales.ver'), 403);

        $datos = $request->validate([
            'rh_colaborador_id' => 'required|exists:rh_colaboradores,id',
            'fecha_evento' => 'nullable|date',
            'hora_salida' => 'required',
            'hora_regreso' => 'nullable',
        ]);

        $colaborador = RhColaborador::find($datos['rh_colaborador_id']);

        return response()->json($calcularService->ejecutar($datos, $colaborador));
    }

    public function sellarPeriodo(Request $request): RedirectResponse
    {
        abort_unless(Auth::user()->can('rh.salidas_personales.sellar'), 403);

        $datos = $request->validate([
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date',
            'fecha_pago' => 'nullable|date',
        ]);

        $fechaPago = $datos['fecha_pago'] ?? now()->toDateString();

        $afectados = RhSalidaPersonal::query()
            ->whereNull('fecha_deduccion_nomina')
            ->whereBetween('fecha_evento', [$datos['fecha_inicio'], $datos['fecha_fin']])
            ->update([
                'fecha_deduccion_nomina' => $fechaPago,
            ]);

        return back()->with('success', "Se cobraron y sellaron {$afectados} registro(s) de salida personal con fecha de pago {$fechaPago}.");
    }
}
