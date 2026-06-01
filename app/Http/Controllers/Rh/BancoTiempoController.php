<?php

namespace App\Http\Controllers\Rh;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rh\StoreBancoTiempoRequest;
use App\Http\Requests\Rh\UpdateBancoTiempoRequest;
use App\Models\Departamento;
use App\Models\RhBancoTiempo;
use App\Models\RhColaborador;
use App\Services\Rh\ActualizarBancoTiempoService;
use App\Services\Rh\CrearBancoTiempoService;
use App\Services\Rh\ListarBancoTiempoService;
use App\Services\Rh\SaldarBancoTiempoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class BancoTiempoController extends Controller
{
    public function index(Request $request, ListarBancoTiempoService $listarService): Response
    {
        abort_unless(Auth::user()->can('rh.banco_tiempo.ver'), 403);

        $filtros = $request->only([
            'busqueda', 'rh_colaborador_id', 'departamento_id',
            'estado', 'fecha_inicio', 'fecha_fin',
        ]);

        return Inertia::render('Rh/BancoTiempo/Index', [
            'registros'    => $listarService->ejecutar($filtros),
            'metricas'     => $listarService->metricas($filtros),
            'colaboradores' => RhColaborador::where('activo', true)
                ->with(['departamento', 'area'])
                ->orderBy('nombre')
                ->get(),
            'departamentos' => Departamento::where('activo', true)->with('areas')->orderBy('nombre')->get(),
            'filtros'      => $filtros,
            'puedeCrear'   => Auth::user()->can('rh.banco_tiempo.crear'),
            'puedeEditar'  => Auth::user()->can('rh.banco_tiempo.editar'),
            'puedeSaldar'  => Auth::user()->can('rh.banco_tiempo.saldar'),
            'puedeEliminar' => Auth::user()->can('rh.banco_tiempo.eliminar'),
        ]);
    }

    public function store(
        StoreBancoTiempoRequest $request,
        CrearBancoTiempoService $crearService,
    ): RedirectResponse {
        $registro = $crearService->ejecutar($request->user(), $request->validated());

        return back()->with('success', "Deuda {$registro->folio} registrada en el banco de tiempo.");
    }

    public function update(
        UpdateBancoTiempoRequest $request,
        RhBancoTiempo $bancoTiempo,
        ActualizarBancoTiempoService $actualizarService,
    ): RedirectResponse {
        $actualizarService->ejecutar($bancoTiempo, $request->validated());

        return back()->with('success', "Registro {$bancoTiempo->folio} actualizado correctamente.");
    }

    public function saldar(
        Request $request,
        RhBancoTiempo $bancoTiempo,
        SaldarBancoTiempoService $saldarService,
    ): RedirectResponse {
        abort_unless(Auth::user()->can('rh.banco_tiempo.saldar'), 403);

        $datos = $request->validate([
            'fecha_devolucion' => 'nullable|date',
        ]);

        $saldarService->ejecutar($bancoTiempo, $datos['fecha_devolucion'] ?? null);

        return back()->with('success', "Registro {$bancoTiempo->folio} marcado como saldado. ¡Tiempo devuelto a la empresa!");
    }

    public function destroy(RhBancoTiempo $bancoTiempo): RedirectResponse
    {
        abort_unless(Auth::user()->can('rh.banco_tiempo.eliminar'), 403);

        if (!$bancoTiempo->estaActiva()) {
            return back()->with('error', 'No se puede eliminar un registro ya saldado. Forma parte del historial auditado.');
        }

        $folio = $bancoTiempo->folio;
        $bancoTiempo->delete();

        return back()->with('success', "Registro {$folio} eliminado del banco de tiempo.");
    }
}
