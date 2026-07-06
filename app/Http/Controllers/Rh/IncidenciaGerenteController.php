<?php

namespace App\Http\Controllers\Rh;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rh\StoreIncidenciaGerenteRequest;
use App\Models\RhColaborador;
use App\Models\RhConfiguracion;
use App\Models\RhDeduccion;
use App\Services\Rh\CrearIncidenciaGerenteService;
use App\Services\Rh\FiltrarColaboradoresGerenteService;
use App\Services\Rh\FiltrarReglasIncidenciaService;
use App\Services\Rh\ListarDeduccionesService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class IncidenciaGerenteController extends Controller
{
    public function index(
        Request $request,
        ListarDeduccionesService $listarService,
        FiltrarColaboradoresGerenteService $filtrarColaboradores,
    ): Response {
        abort_unless(
            Auth::user()->can('rh.incidencias.gerente.ver')
            || Auth::user()->can('rh.incidencias.ver')
            || Auth::user()->can('rh.deducciones.ver'),
            403,
        );

        $colaboradoresScope = $filtrarColaboradores->ejecutar(Auth::user());
        $colaboradorIds = $colaboradoresScope->pluck('id')->all();

        $filtros = array_merge(
            $request->only(['busqueda', 'rh_colaborador_id', 'fecha_inicio', 'fecha_fin', 'solo_hoy']),
            ['rama' => 'incidencias'],
        );

        if (!Auth::user()->can('rh.deducciones.ver') && !Auth::user()->can('rh.incidencias.ver')) {
            $filtros['rh_colaborador_ids'] = $colaboradorIds;
        }

        return Inertia::render('Rh/IncidenciasGerente/Index', [
            'registros' => $listarService->ejecutar($filtros),
            'colaboradores' => $colaboradoresScope,
            'reglasIncidencia' => app(FiltrarReglasIncidenciaService::class)->ejecutar(Auth::user()),
            'filtros' => $filtros,
            'puedeCrear' => Auth::user()->can('rh.incidencias.gerente.crear')
                || Auth::user()->can('rh.incidencias.crear')
                || Auth::user()->can('rh.deducciones.crear'),
            'puedeRecibos' => Auth::user()->can('rh.recibos.ver') || Auth::user()->can('rh.recibos.generar'),
        ]);
    }

    public function create(FiltrarColaboradoresGerenteService $filtrarColaboradores): Response
    {
        abort_unless(
            Auth::user()->can('rh.incidencias.gerente.crear')
            || Auth::user()->can('rh.incidencias.crear')
            || Auth::user()->can('rh.deducciones.crear'),
            403,
        );

        return Inertia::render('Rh/IncidenciasGerente/Crear', [
            'colaboradores' => $filtrarColaboradores->ejecutar(Auth::user()),
            'reglasIncidencia' => app(FiltrarReglasIncidenciaService::class)->ejecutar(Auth::user()),
            'configuracion' => RhConfiguracion::obtener(),
        ]);
    }

    public function store(
        StoreIncidenciaGerenteRequest $request,
        CrearIncidenciaGerenteService $crearService,
    ): RedirectResponse {
        $registro = $crearService->ejecutar(Auth::user(), $request->validated());

        return redirect()
            ->route('rh.incidencias_gerente.deducciones.show', $registro)
            ->with('success', "Incidencia {$registro->folio} registrada. Puede imprimir el recibo.");
    }
}
