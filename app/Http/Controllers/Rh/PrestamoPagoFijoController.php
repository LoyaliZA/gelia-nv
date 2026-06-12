<?php

namespace App\Http\Controllers\Rh;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rh\StorePrestamoPagoFijoRequest;
use App\Http\Requests\Rh\UpdatePrestamoPagoFijoRequest;
use App\Models\Departamento;
use App\Models\RhColaborador;
use App\Models\RhConfiguracion;
use App\Models\RhPrestamoPagoFijo;
use App\Services\Rh\ActualizarPrestamoPagoFijoService;
use App\Services\Rh\CrearPrestamoPagoFijoService;
use App\Services\Rh\DetenerPrestamoPagoFijoService;
use App\Services\Rh\GenerarCuotasPrestamoService;
use App\Services\Rh\ListarPrestamosPagoFijoService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class PrestamoPagoFijoController extends Controller
{
    public function index(Request $request, ListarPrestamosPagoFijoService $listarService): Response
    {
        abort_unless(Auth::user()->can('rh.prestamos.ver'), 403);

        $filtros = $request->only([
            'busqueda', 'rh_colaborador_id', 'departamento_id', 'area_id', 'modalidad', 'estado',
        ]);

        return Inertia::render('Rh/PrestamosPagosFijos/Index', [
            'registros' => $listarService->ejecutar($filtros),
            'metricas' => $listarService->metricas($filtros),
            'colaboradores' => RhColaborador::where('activo', true)
                ->with(['departamento', 'area'])
                ->orderBy('nombre')
                ->get(),
            'departamentos' => Departamento::where('activo', true)->with('areas')->orderBy('nombre')->get(),
            'configuracion' => RhConfiguracion::obtener(),
            'filtros' => $filtros,
            'puedeCrear' => Auth::user()->can('rh.prestamos.crear'),
            'puedeEditar' => Auth::user()->can('rh.prestamos.editar'),
            'puedeDetener' => Auth::user()->can('rh.prestamos.detener'),
            'puedeGenerar' => Auth::user()->can('rh.prestamos.generar'),
        ]);
    }

    public function show(RhPrestamoPagoFijo $prestamo): Response
    {
        abort_unless(Auth::user()->can('rh.prestamos.ver'), 403);

        $prestamo->load([
            'colaborador.departamento',
            'colaborador.area',
            'registradoPor',
            'deducciones' => fn ($q) => $q->orderByDesc('fecha_ocurrencia')->orderByDesc('id'),
        ]);

        return Inertia::render('Rh/PrestamosPagosFijos/Show', [
            'registro' => $prestamo,
            'colaboradores' => RhColaborador::where('activo', true)
                ->with(['departamento', 'area'])
                ->orderBy('nombre')
                ->get(),
            'configuracion' => RhConfiguracion::obtener(),
            'puedeEditar' => Auth::user()->can('rh.prestamos.editar'),
            'puedeDetener' => Auth::user()->can('rh.prestamos.detener'),
        ]);
    }

    public function store(
        StorePrestamoPagoFijoRequest $request,
        CrearPrestamoPagoFijoService $crearService,
    ): RedirectResponse {
        $prestamo = $crearService->ejecutar(Auth::user(), $request->validated());

        return back()->with('success', "Convenio {$prestamo->folio} registrado correctamente.");
    }

    public function update(
        UpdatePrestamoPagoFijoRequest $request,
        RhPrestamoPagoFijo $prestamo,
        ActualizarPrestamoPagoFijoService $actualizarService,
    ): RedirectResponse {
        $actualizarService->ejecutar($prestamo, $request->validated());

        return back()->with('success', 'Convenio actualizado correctamente.');
    }

    public function pausar(
        RhPrestamoPagoFijo $prestamo,
        DetenerPrestamoPagoFijoService $detenerService,
    ): RedirectResponse {
        abort_unless(Auth::user()->can('rh.prestamos.detener'), 403);

        $detenerService->pausar($prestamo);

        return back()->with('success', 'Convenio pausado correctamente.');
    }

    public function reanudar(
        RhPrestamoPagoFijo $prestamo,
        DetenerPrestamoPagoFijoService $detenerService,
    ): RedirectResponse {
        abort_unless(Auth::user()->can('rh.prestamos.detener'), 403);

        $detenerService->reanudar($prestamo);

        return back()->with('success', 'Convenio reanudado correctamente.');
    }

    public function cancelar(
        RhPrestamoPagoFijo $prestamo,
        DetenerPrestamoPagoFijoService $detenerService,
    ): RedirectResponse {
        abort_unless(Auth::user()->can('rh.prestamos.detener'), 403);

        $detenerService->cancelar($prestamo);

        return back()->with('success', 'Convenio cancelado correctamente.');
    }

    public function generarCuotas(Request $request, GenerarCuotasPrestamoService $generarService): RedirectResponse
    {
        abort_unless(Auth::user()->can('rh.prestamos.generar'), 403);

        $config = RhConfiguracion::obtener();
        $diasPeriodo = max(1, (int) $config->dias_periodo_pago);

        $fechaInicioGlobal = $config->periodo_actual_inicio ? Carbon::parse($config->periodo_actual_inicio) : now()->startOfMonth();
        $fechaFinGlobal = $config->periodo_actual_fin ? Carbon::parse($config->periodo_actual_fin) : now()->endOfMonth();

        $fechaFin = $request->filled('fecha_fin')
            ? Carbon::parse($request->input('fecha_fin'))
            : $fechaFinGlobal;

        $fechaInicio = $request->filled('fecha_inicio')
            ? Carbon::parse($request->input('fecha_inicio'))
            : $fechaInicioGlobal;

        $resultado = $generarService->ejecutar($fechaInicio, $fechaFin, Auth::user());

        $mensaje = "Se generaron {$resultado['generadas']} cuota(s). Omitidas: {$resultado['omitidas']}.";

        if (!empty($resultado['errores'])) {
            $mensaje .= ' Errores: ' . count($resultado['errores']) . '.';
        }

        return back()->with('success', $mensaje);
    }
}
