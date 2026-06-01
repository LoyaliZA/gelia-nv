<?php

namespace App\Http\Controllers\Rh;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rh\StoreDeduccionRequest;
use App\Http\Requests\Rh\UpdateDeduccionRequest;
use App\Models\Departamento;
use App\Models\Producto;
use App\Models\RhColaborador;
use App\Models\RhConfiguracion;
use App\Models\RhDeduccion;
use App\Services\Rh\ActualizarDeduccionService;
use App\Services\Rh\CalcularDeduccionReglaService;
use App\Services\Rh\CrearDeduccionService;
use App\Services\Rh\FiltrarReglasIncidenciaService;
use App\Services\Rh\ListarDeduccionesService;
use App\Services\Rh\MarcarDeduccionAplicadaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DeduccionController extends Controller
{
    public function index(Request $request, ListarDeduccionesService $listarService): Response
    {
        abort_unless(Auth::user()->can('rh.deducciones.ver') || Auth::user()->can('rh.incidencias.ver'), 403);

        $filtros = $request->only([
            'busqueda', 'rh_colaborador_id', 'catalogo_regla_incidencia_id', 'departamento_id', 'area_id',
            'fecha_inicio', 'fecha_fin', 'solo_hoy', 'estado_deduccion', 'origen_deduccion',
        ]);

        $filtrarReglas = app(FiltrarReglasIncidenciaService::class);

        return Inertia::render('Rh/Deducciones/Index', [
            'registros' => $listarService->ejecutar($filtros),
            'metricas' => $listarService->metricas($filtros),
            'colaboradores' => RhColaborador::where('activo', true)
                ->with(['departamento', 'area'])
                ->orderBy('nombre')
                ->get(),
            'reglasIncidencia' => $filtrarReglas->ejecutar(Auth::user()),
            'departamentos' => Departamento::where('activo', true)->with('areas')->orderBy('nombre')->get(),
            'configuracion' => RhConfiguracion::obtener(),
            'filtros' => $filtros,
            'usuarioActual' => Auth::user()->only(['id', 'name']),
            'puedeCrear' => Auth::user()->can('rh.deducciones.crear') || Auth::user()->can('rh.incidencias.crear'),
            'puedeEditar' => Auth::user()->can('rh.deducciones.editar') || Auth::user()->can('rh.incidencias.editar'),
            'puedeAplicar' => Auth::user()->can('rh.deducciones.aplicar') || Auth::user()->can('rh.incidencias.aplicar'),
        ]);
    }

    public function show(RhDeduccion $deduccion): Response
    {
        abort_unless(Auth::user()->can('rh.deducciones.ver') || Auth::user()->can('rh.incidencias.ver'), 403);

        $deduccion->load([
            'colaborador.departamento',
            'colaborador.area',
            'colaborador.puesto',
            'reglaIncidencia',
            'registradoPor',
            'producto',
            'comisionAuditor.usuario',
            'movimientoComisionColaborador',
            'prestamoPagoFijo',
        ]);

        return Inertia::render('Rh/Deducciones/Show', [
            'registro' => $deduccion,
            'configuracion' => RhConfiguracion::obtener(),
            'puedeEditar' => (Auth::user()->can('rh.deducciones.editar') || Auth::user()->can('rh.incidencias.editar'))
                && $deduccion->estado_deduccion !== RhDeduccion::ESTADO_APLICADO,
            'puedeAplicar' => (Auth::user()->can('rh.deducciones.aplicar') || Auth::user()->can('rh.incidencias.aplicar'))
                && $deduccion->estado_deduccion !== RhDeduccion::ESTADO_APLICADO,
            'colaboradores' => RhColaborador::where('activo', true)->with(['departamento', 'area'])->orderBy('nombre')->get(),
            'reglasIncidencia' => app(FiltrarReglasIncidenciaService::class)->ejecutar(
                Auth::user(),
                $deduccion->colaborador,
                $deduccion->catalogo_regla_incidencia_id,
            ),
            'usuarioActual' => Auth::user()->only(['id', 'name']),
        ]);
    }

    public function store(StoreDeduccionRequest $request, CrearDeduccionService $crearService): RedirectResponse
    {
        $registro = $crearService->ejecutar(Auth::user(), $request->validated());

        return redirect()
            ->route('rh.deducciones.show', $registro)
            ->with('success', "Deducción {$registro->folio} registrada correctamente.");
    }

    public function update(
        UpdateDeduccionRequest $request,
        RhDeduccion $deduccion,
        ActualizarDeduccionService $actualizarService,
    ): RedirectResponse {
        $registro = $actualizarService->ejecutar(Auth::user(), $deduccion, $request->validated());

        return back()->with('success', "Deducción {$registro->folio} actualizada.");
    }

    public function aplicar(
        RhDeduccion $deduccion,
        MarcarDeduccionAplicadaService $marcarService,
    ): RedirectResponse {
        abort_unless(Auth::user()->can('rh.deducciones.aplicar') || Auth::user()->can('rh.incidencias.aplicar'), 403);

        $registro = $marcarService->ejecutar(Auth::user(), $deduccion);

        return back()->with('success', "Deducción {$registro->folio} marcada como aplicada.");
    }

    public function previewCalculos(Request $request, CalcularDeduccionReglaService $calcularService): JsonResponse
    {
        abort_unless(Auth::user()->can('rh.deducciones.ver') || Auth::user()->can('rh.incidencias.ver'), 403);

        $datos = $request->validate([
            'rh_colaborador_id' => 'nullable|exists:rh_colaboradores,id',
            'catalogo_regla_incidencia_id' => 'nullable|exists:catalogo_reglas_incidencia,id',
            'producto_id' => 'nullable|exists:productos,id',
            'factor_multiplicador' => 'nullable|numeric|min:0.01',
            'origen_deduccion' => 'nullable|in:nomina,comisiones',
            'fecha_deduccion_nomina' => 'nullable|date',
        ]);

        if (empty($datos['rh_colaborador_id']) || empty($datos['catalogo_regla_incidencia_id'])) {
            return response()->json([
                'monto_deduccion_base' => 0,
                'factor_multiplicador' => 1,
                'total_parcial' => 0,
                'monto_total_final' => 0,
                'total_deduccion' => 0,
                'estado_deduccion' => RhDeduccion::ESTADO_PENDIENTE_NOMINA,
            ]);
        }

        $colaborador = RhColaborador::with('bonos')->findOrFail($datos['rh_colaborador_id']);
        $regla = \App\Models\CatalogoReglaIncidencia::findOrFail($datos['catalogo_regla_incidencia_id']);
        $producto = !empty($datos['producto_id']) ? Producto::find($datos['producto_id']) : null;

        return response()->json(
            $calcularService->ejecutar(
                $colaborador,
                $regla,
                (float) ($datos['factor_multiplicador'] ?? 1),
                $producto,
                $datos['origen_deduccion'] ?? RhDeduccion::ORIGEN_NOMINA,
                $datos['fecha_deduccion_nomina'] ?? null,
            ),
        );
    }

    public function reglasDisponibles(Request $request, FiltrarReglasIncidenciaService $filtrarService): JsonResponse
    {
        abort_unless(Auth::user()->can('rh.deducciones.ver') || Auth::user()->can('rh.incidencias.ver'), 403);

        $datos = $request->validate([
            'rh_colaborador_id' => 'nullable|exists:rh_colaboradores,id',
            'regla_id_actual' => 'nullable|exists:catalogo_reglas_incidencia,id',
        ]);

        $colaborador = !empty($datos['rh_colaborador_id'])
            ? RhColaborador::find($datos['rh_colaborador_id'])
            : null;

        $reglas = $filtrarService->ejecutar(
            Auth::user(),
            $colaborador,
            $datos['regla_id_actual'] ?? null,
        );

        return response()->json(['reglas' => $reglas]);
    }

    public function buscarSku(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->can('rh.deducciones.ver') || Auth::user()->can('rh.incidencias.ver'), 403);

        $q = trim($request->input('q', ''));
        if ($q === '') {
            return response()->json(['productos' => []]);
        }

        $sku = Producto::normalizarSku($q);
        $productos = Producto::query()
            ->where('activo', true)
            ->where(function ($query) use ($q, $sku) {
                $query->where('sku', 'like', "%{$sku}%")
                    ->orWhere('descripcion', 'like', "%{$q}%");
            })
            ->limit(10)
            ->get(['id', 'sku', 'descripcion', 'costo', 'precio_venta']);

        return response()->json(['productos' => $productos]);
    }
}
