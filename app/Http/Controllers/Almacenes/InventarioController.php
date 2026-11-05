<?php

namespace App\Http\Controllers\Almacenes;

use App\Http\Controllers\Controller;
use App\Http\Requests\Almacenes\StoreInventarioRequest;
use App\Http\Requests\Almacenes\UpdateInventarioRequest;
use App\Models\Inventario;
use App\Services\Almacenes\AlcanceAlmacenesService;
use App\Services\Almacenes\ListarInventariosService;
use App\Services\Almacenes\GuardarArchivoVistaPreviaImportacionService;
use App\Services\Almacenes\IniciarImportacionAlmacenService;
use App\Services\Almacenes\LeerEncabezadosArchivoImportacionService;
use App\Services\Almacenes\RegistrarAuditoriaAlmacenService;
use App\Services\Catalogos\PlantillaImportacionCatalogoService;
use App\Support\Almacenes\OperacionesImportacionAlmacen;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InventarioController extends Controller
{
    public function __construct(
        private readonly RegistrarAuditoriaAlmacenService $auditoria,
    ) {}

    public function index(Request $request, AlcanceAlmacenesService $alcance, ListarInventariosService $listar): Response
    {
        $user = $request->user();
        $sucursales = $alcance->sucursalesOperables($user);

        return Inertia::render('Almacenes/Inventarios/Index', [
            'inventarios' => $listar->ejecutar($user, $request->query(), 50),
            'sucursales' => $sucursales,
            'almacenes' => $alcance->almacenesOperables($user),
            'filtros' => (object) $request->only(['sucursal_id', 'almacen_id', 'q', 'sort', 'dir']),
            'sin_sucursales_operables' => $sucursales->isEmpty(),
            'puede_carga_completa' => OperacionesImportacionAlmacen::usuarioPuedeCaso($user, 'existencias'),
        ]);
    }

    public function store(StoreInventarioRequest $request): RedirectResponse
    {
        $inventario = Inventario::create($request->validated());

        $this->auditoria->inventarioModificado($inventario->id, "SKU {$inventario->producto_id}", [
            'accion' => 'creado',
            'almacen_id' => $inventario->almacen_id,
            'existencia' => $inventario->existencia,
        ]);

        return back()->with('success', 'Registro de inventario creado.');
    }

    public function update(UpdateInventarioRequest $request, Inventario $inventario): RedirectResponse
    {
        $inventario->update($request->validated());

        $this->auditoria->inventarioModificado($inventario->id, "inventario #{$inventario->id}", [
            'accion' => 'actualizado',
            'existencia' => $inventario->existencia,
        ]);

        return back()->with('success', 'Inventario actualizado.');
    }

    public function destroy(Request $request, Inventario $inventario, AlcanceAlmacenesService $alcance): RedirectResponse
    {
        $alcance->asegurarAlmacenOperable($request->user(), (int) $inventario->almacen_id);

        $id = $inventario->id;
        $inventario->delete();

        $this->auditoria->inventarioEliminado($id, "inventario #{$id}");

        return back()->with('success', 'Registro de inventario eliminado.');
    }

    public function importPreview(
        Request $request,
        GuardarArchivoVistaPreviaImportacionService $vistaPrevia,
        LeerEncabezadosArchivoImportacionService $encabezados,
        AlcanceAlmacenesService $alcance,
    ) {
        $request->validate([
            'archivo' => 'required|file|mimes:csv,xlsx,xls',
            'almacen_id' => 'required|exists:almacenes,id',
        ]);
        $alcance->asegurarAlmacenOperable($request->user(), (int) $request->almacen_id);

        $path = $vistaPrevia->guardar((int) $request->user()->id, $request->file('archivo'));

        return response()->json([
            'headers' => $encabezados->ejecutar($path),
            'file_path' => $path,
            'almacen_id' => $request->almacen_id,
        ]);
    }

    public function importIniciar(Request $request, IniciarImportacionAlmacenService $iniciar): JsonResponse
    {
        $resultado = $iniciar->ejecutar($request, 'inventarios');

        return response()->json(array_merge(['success' => true], $resultado));
    }

    public function descargarPlantillaImportacion(PlantillaImportacionCatalogoService $plantillaService)
    {
        return $plantillaService->descargar('inventarios');
    }
}
