<?php

namespace App\Http\Controllers\Rh;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rh\StoreIncidenciaRequest;
use App\Http\Requests\Rh\UpdateIncidenciaRequest;
use App\Models\CatalogoTipoFalta;
use App\Models\Departamento;
use App\Models\RhColaborador;
use App\Models\RhConfiguracion;
use App\Models\RhIncidencia;
use App\Services\Rh\ActualizarIncidenciaService;
use App\Services\Rh\CalcularPenalizacionIncidenciaService;
use App\Services\Rh\CrearIncidenciaService;
use App\Services\Rh\ListarIncidenciasService;
use App\Services\Rh\MarcarIncidenciaAplicadaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class IncidenciaController extends Controller
{
    public function index(Request $request, ListarIncidenciasService $listarService): Response
    {
        abort_unless(Auth::user()->can('rh.incidencias.ver'), 403);

        $filtros = $request->only([
            'busqueda', 'rh_colaborador_id', 'catalogo_tipo_falta_id', 'departamento_id', 'area_id',
            'fecha_inicio', 'fecha_fin', 'solo_hoy', 'estado_deduccion',
        ]);

        return Inertia::render('Rh/Incidencias/Index', [
            'registros' => $listarService->ejecutar($filtros),
            'metricas' => $listarService->metricas($filtros),
            'colaboradores' => RhColaborador::where('activo', true)
                ->with(['departamento', 'area'])
                ->orderBy('nombre')
                ->get(),
            'tiposFalta' => CatalogoTipoFalta::where('activo', true)->orderBy('nombre')->get(),
            'departamentos' => Departamento::where('activo', true)->with('areas')->orderBy('nombre')->get(),
            'configuracion' => RhConfiguracion::obtener(),
            'filtros' => $filtros,
            'puedeCrear' => Auth::user()->can('rh.incidencias.crear'),
            'puedeEditar' => Auth::user()->can('rh.incidencias.editar'),
            'puedeAplicar' => Auth::user()->can('rh.incidencias.aplicar'),
        ]);
    }

    public function show(RhIncidencia $incidencia): Response
    {
        abort_unless(Auth::user()->can('rh.incidencias.ver'), 403);

        $incidencia->load(['colaborador.departamento', 'colaborador.area', 'colaborador.puesto', 'tipoFalta', 'registradoPor']);

        return Inertia::render('Rh/Incidencias/Show', [
            'registro' => $incidencia,
            'configuracion' => RhConfiguracion::obtener(),
            'puedeEditar' => Auth::user()->can('rh.incidencias.editar') && $incidencia->estado_deduccion !== 'aplicado',
            'puedeAplicar' => Auth::user()->can('rh.incidencias.aplicar') && $incidencia->estado_deduccion !== 'aplicado',
            'colaboradores' => RhColaborador::where('activo', true)->with(['departamento', 'area'])->orderBy('nombre')->get(),
            'tiposFalta' => CatalogoTipoFalta::where('activo', true)->orderBy('nombre')->get(),
        ]);
    }

    public function store(StoreIncidenciaRequest $request, CrearIncidenciaService $crearService): RedirectResponse
    {
        $registro = $crearService->ejecutar(Auth::user(), $request->validated());

        return redirect()
            ->route('rh.incidencias.show', $registro)
            ->with('success', "Incidencia {$registro->folio} registrada correctamente.");
    }

    public function update(
        UpdateIncidenciaRequest $request,
        RhIncidencia $incidencia,
        ActualizarIncidenciaService $actualizarService,
    ): RedirectResponse {
        $registro = $actualizarService->ejecutar(Auth::user(), $incidencia, $request->validated());

        return back()->with('success', "Incidencia {$registro->folio} actualizada.");
    }

    public function aplicar(
        RhIncidencia $incidencia,
        MarcarIncidenciaAplicadaService $marcarService,
    ): RedirectResponse {
        abort_unless(Auth::user()->can('rh.incidencias.aplicar'), 403);

        $registro = $marcarService->ejecutar(Auth::user(), $incidencia);

        return back()->with('success', "Incidencia {$registro->folio} marcada como aplicada en nómina.");
    }

    public function previewCalculos(Request $request, CalcularPenalizacionIncidenciaService $calcularService): JsonResponse
    {
        abort_unless(Auth::user()->can('rh.incidencias.ver'), 403);

        $datos = $request->validate([
            'rh_colaborador_id' => 'nullable|exists:rh_colaboradores,id',
            'catalogo_tipo_falta_id' => 'nullable|exists:catalogo_tipos_faltas,id',
            'fecha_deduccion_nomina' => 'nullable|date',
        ]);

        if (empty($datos['rh_colaborador_id']) || empty($datos['catalogo_tipo_falta_id'])) {
            return response()->json([
                'deduccion_salario_base' => 0,
                'deduccion_bono_puntualidad' => 0,
                'deduccion_bono_productividad' => 0,
                'total_deduccion' => 0,
                'estado_deduccion' => 'pendiente',
            ]);
        }

        $colaborador = RhColaborador::findOrFail($datos['rh_colaborador_id']);
        $tipo = CatalogoTipoFalta::findOrFail($datos['catalogo_tipo_falta_id']);

        return response()->json(
            $calcularService->ejecutar($colaborador, $tipo, $datos['fecha_deduccion_nomina'] ?? null),
        );
    }
}
