<?php

namespace App\Http\Controllers\Rh;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rh\StoreHorasExtraRequest;
use App\Http\Requests\Rh\UpdateHorasExtraRequest;
use App\Models\Departamento;
use App\Models\RhColaborador;
use App\Models\RhConfiguracion;
use App\Models\RhHorasExtra;
use App\Models\User;
use App\Services\Rh\ActualizarHorasExtraService;
use App\Services\Rh\CalcularHorasExtraService;
use App\Services\Rh\CrearHorasExtraService;
use App\Services\Rh\ListarHorasExtraService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class HorasExtraController extends Controller
{
    public function index(Request $request, ListarHorasExtraService $listarService): Response
    {
        abort_unless(Auth::user()->can('rh.horas_extra.ver'), 403);

        $filtros = $request->only([
            'busqueda', 'rh_colaborador_id', 'departamento_id', 'area_id',
            'fecha_inicio', 'fecha_fin', 'solo_hoy', 'estado_pago',
        ]);

        return Inertia::render('Rh/HorasExtra/Index', [
            'registros' => $listarService->ejecutar($filtros),
            'metricas' => $listarService->metricas($filtros),
            'colaboradores' => RhColaborador::where('activo', true)
                ->with(['departamento', 'area', 'turno'])
                ->orderBy('nombre')
                ->get(),
            'departamentos' => Departamento::where('activo', true)->with('areas')->orderBy('nombre')->get(),
            'supervisores' => User::select(['id', 'name', 'email', 'apellido_paterno'])->orderBy('name')->get(),
            'configuracion' => RhConfiguracion::obtener(),
            'filtros' => $filtros,
            'puedeCrear' => Auth::user()->can('rh.horas_extra.crear'),
            'puedeEditar' => Auth::user()->can('rh.horas_extra.editar'),
        ]);
    }

    public function show(RhHorasExtra $horasExtra): Response
    {
        abort_unless(Auth::user()->can('rh.horas_extra.ver'), 403);

        $horasExtra->load(['colaborador.departamento', 'colaborador.area', 'colaborador.puesto', 'supervisor', 'registradoPor']);

        return Inertia::render('Rh/HorasExtra/Show', [
            'registro' => $horasExtra,
            'configuracion' => RhConfiguracion::obtener(),
            'puedeEditar' => Auth::user()->can('rh.horas_extra.editar') && $horasExtra->estado_pago === 'pendiente',
            'colaboradores' => RhColaborador::where('activo', true)->with(['departamento', 'area', 'turno'])->orderBy('nombre')->get(),
            'supervisores' => User::select(['id', 'name', 'email', 'apellido_paterno'])->orderBy('name')->get(),
        ]);
    }

    public function store(StoreHorasExtraRequest $request, CrearHorasExtraService $crearService): RedirectResponse
    {
        $registro = $crearService->ejecutar(Auth::user(), $request->validated());

        return redirect()
            ->route('rh.horas_extra.show', $registro)
            ->with('success', "Registro {$registro->folio} creado correctamente.");
    }

    public function update(
        UpdateHorasExtraRequest $request,
        RhHorasExtra $horasExtra,
        ActualizarHorasExtraService $actualizarService,
    ): RedirectResponse {
        $registro = $actualizarService->ejecutar(Auth::user(), $horasExtra, $request->validated());

        return back()->with('success', "Registro {$registro->folio} actualizado.");
    }

    public function previewCalculos(Request $request, CalcularHorasExtraService $calcularService): JsonResponse
    {
        abort_unless(Auth::user()->can('rh.horas_extra.ver'), 403);

        $datos = $request->validate([
            'rh_colaborador_id' => 'nullable|exists:rh_colaboradores,id',
            'fecha_turno' => 'nullable|date',
            'hora_entrada' => 'nullable|date_format:H:i',
            'hora_salida' => 'nullable|date_format:H:i',
            'salida_dia_siguiente' => 'nullable|boolean',
            'fecha_programada_pago' => 'nullable|date',
        ]);

        $config = RhConfiguracion::obtener();
        $colaborador = null;

        if (!empty($datos['rh_colaborador_id'])) {
            $colaborador = RhColaborador::with(['area', 'turno'])->find($datos['rh_colaborador_id']);
            if ($colaborador) {
                $datos['horas_normales_snapshot'] = $colaborador->horas_laboradas_oficiales;
                $datos['salario_por_hora_snapshot'] = $colaborador->salario_por_hora;
                $datos['multiplicador_snapshot'] = $config->he_multiplicador_pago;
                $datos['area_snapshot'] = $colaborador->area?->nombre;
            }
        }

        $datos['fecha_turno'] = $datos['fecha_turno'] ?? now()->toDateString();
        $datos['hora_entrada'] = $datos['hora_entrada'] ?? '08:00';
        $datos['hora_salida'] = $datos['hora_salida'] ?? '17:00';

        return response()->json($calcularService->ejecutar($datos, $config, $colaborador ?? null));
    }
}
