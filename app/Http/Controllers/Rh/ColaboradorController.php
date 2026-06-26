<?php

namespace App\Http\Controllers\Rh;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rh\StoreColaboradorRequest;
use App\Http\Requests\Rh\UpdateColaboradorRequest;
use App\Models\CatalogoPuesto;
use App\Models\Departamento;
use App\Models\RhColaborador;
use App\Models\RhConfiguracion;
use App\Models\RhDeduccion;
use App\Models\RhPrestamoPagoFijo;
use App\Models\User;
use App\Services\Rh\ActualizarColaboradorService;
use App\Services\Rh\CalcularSalariosColaboradorService;
use App\Services\Rh\CrearColaboradorService;
use App\Services\Rh\ListarColaboradoresService;
use App\Services\Rh\SincronizarDatosUsuarioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ColaboradorController extends Controller
{
    public function index(Request $request, ListarColaboradoresService $listarService): Response
    {
        $filtros = $request->only([
            'busqueda', 'departamento_id', 'area_id',
            'catalogo_puesto_id', 'activo', 'vinculo',
        ]);

        return Inertia::render('Rh/Colaboradores/Index', [
            'colaboradores' => $listarService->ejecutar($filtros),
            'departamentos' => Departamento::where('activo', true)->with('areas')->orderBy('nombre')->get(),
            'puestos' => CatalogoPuesto::with('bonos')->where('activo', true)->orderBy('nombre')->get(),
            'turnos' => \App\Models\CatalogoTurno::where('activo', true)->orderBy('nombre')->get(),
            'usuarios' => User::select(['id', 'name', 'email', 'apellido_paterno', 'apellido_materno'])
                ->orderBy('name')
                ->get(),
            'configuracion' => RhConfiguracion::obtener(),
            'filtros' => $filtros,
            'puedeCrear' => Auth::user()->can('rh.colaboradores.crear'),
            'puedeEditar' => Auth::user()->can('rh.colaboradores.editar'),
            'puedeVincular' => Auth::user()->can('rh.colaboradores.vincular_usuario'),
        ]);
    }

    public function show(RhColaborador $colaborador): Response
    {
        $colaborador->load(['departamento', 'area', 'puesto.bonos', 'usuario', 'registradoPor', 'bonos']);

        $deducciones = RhDeduccion::with('reglaIncidencia')
            ->where('rh_colaborador_id', $colaborador->id)
            ->orderByDesc('fecha_ocurrencia')
            ->limit(8)
            ->get();

        $prestamosActivos = RhPrestamoPagoFijo::query()
            ->where('rh_colaborador_id', $colaborador->id)
            ->whereIn('estado', [RhPrestamoPagoFijo::ESTADO_ACTIVO, RhPrestamoPagoFijo::ESTADO_PAUSADO])
            ->orderByDesc('created_at')
            ->get();

        return Inertia::render('Rh/Colaboradores/Show', [
            'colaborador' => $colaborador,
            'incidencias' => $deducciones,
            'deducciones' => $deducciones,
            'prestamosActivos' => $prestamosActivos,
            'puedeVerIncidencias' => Auth::user()->can('rh.incidencias.ver') || Auth::user()->can('rh.deducciones.ver'),
            'puedeVerPrestamos' => Auth::user()->can('rh.prestamos.ver'),
            'departamentos' => Departamento::where('activo', true)->with('areas')->orderBy('nombre')->get(),
            'puestos' => CatalogoPuesto::with('bonos')->where('activo', true)->orderBy('nombre')->get(),
            'turnos' => \App\Models\CatalogoTurno::where('activo', true)->orderBy('nombre')->get(),
            'usuarios' => User::select(['id', 'name', 'email', 'apellido_paterno', 'apellido_materno'])
                ->orderBy('name')
                ->get(),
            'configuracion' => RhConfiguracion::obtener(),
            'puedeEditar' => Auth::user()->can('rh.colaboradores.editar'),
            'puedeVincular' => Auth::user()->can('rh.colaboradores.vincular_usuario'),
        ]);
    }

    public function store(
        StoreColaboradorRequest $request,
        CrearColaboradorService $crearService,
    ): RedirectResponse {
        $colaborador = $crearService->ejecutar(Auth::user(), $request->validated());

        return redirect()
            ->route('rh.colaboradores.show', $colaborador)
            ->with('success', "Colaborador {$colaborador->folio} registrado correctamente.");
    }

    public function update(
        UpdateColaboradorRequest $request,
        RhColaborador $colaborador,
        ActualizarColaboradorService $actualizarService,
    ): RedirectResponse {
        $colaborador = $actualizarService->ejecutar(Auth::user(), $colaborador, $request->validated());

        return back()->with('success', "Perfil de {$colaborador->nombre_completo} actualizado.");
    }

    public function previewCalculos(Request $request, CalcularSalariosColaboradorService $calcularService): JsonResponse
    {
        $datos = $request->validate([
            'salario_base' => 'nullable|numeric|min:0',
            'bono_productividad' => 'nullable|numeric|min:0',
            'bono_puntualidad' => 'nullable|numeric|min:0',
            'horas_laboradas_oficiales' => 'nullable|numeric|min:0.5|max:24',
        ]);

        return response()->json($calcularService->preview($datos));
    }

    public function sincronizarUsuario(User $usuario, SincronizarDatosUsuarioService $sincronizarService): JsonResponse
    {
        abort_unless(Auth::user()->can('rh.colaboradores.vincular_usuario'), 403);

        return response()->json($sincronizarService->ejecutar($usuario));
    }
}
