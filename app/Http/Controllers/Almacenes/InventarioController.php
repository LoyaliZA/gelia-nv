<?php

namespace App\Http\Controllers\Almacenes;

use App\Http\Controllers\Controller;
use App\Http\Requests\Almacenes\StoreInventarioRequest;
use App\Http\Requests\Almacenes\UpdateInventarioRequest;
use App\Models\Inventario;
use App\Services\Almacenes\AlcanceAlmacenesService;
use App\Support\Almacenes\OrdenamientoListadoAlmacen;
use App\Services\Almacenes\GuardarArchivoVistaPreviaImportacionService;
use App\Services\Almacenes\IniciarImportacionAlmacenService;
use App\Services\Almacenes\LeerEncabezadosArchivoImportacionService;
use App\Services\Almacenes\RegistrarAuditoriaAlmacenService;
use App\Services\Catalogos\PlantillaImportacionCatalogoService;
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

    public function index(Request $request, AlcanceAlmacenesService $alcance): Response
    {
        $user = $request->user();
        $query = Inventario::query()
            ->select('inventarios.*')
            ->leftJoin('producto_costos', function ($join) {
                $join->on('producto_costos.producto_id', '=', 'inventarios.producto_id')
                    ->on('producto_costos.almacen_id', '=', 'inventarios.almacen_id');
            })
            ->addSelect([
                'producto_costos.costo as costo_valor',
                'producto_costos.precio_venta as precio_venta_valor',
            ])
            ->with([
                'producto.marca',
                'producto.categoria',
                'almacen.sucursal',
                'almacen.tipoAlmacen',
            ]);

        $alcance->restringirPorAlmacenSucursalOperable($query, $user);

        if ($sucursalId = $request->query('sucursal_id')) {
            $alcance->asegurarSucursalOperable($user, (int) $sucursalId);
            $query->whereHas('almacen', fn ($q) => $q->where('sucursal_id', $sucursalId));
        }

        if ($almacenId = $request->query('almacen_id')) {
            $alcance->asegurarAlmacenOperable($user, (int) $almacenId);
            $query->where('almacen_id', $almacenId);
        }

        if ($busqueda = $request->query('q')) {
            $query->whereHas('producto', fn ($q) => $q->buscarPorTexto($busqueda));
        }

        OrdenamientoListadoAlmacen::inventarios(
            $query,
            $request->query('sort'),
            $request->query('dir'),
        );

        $sucursales = $alcance->sucursalesOperables($user);

        return Inertia::render('Almacenes/Inventarios/Index', [
            'inventarios' => $query->paginate(50)->withQueryString(),
            'sucursales' => $sucursales,
            'almacenes' => $alcance->almacenesOperables($user),
            'filtros' => $request->only(['sucursal_id', 'almacen_id', 'q', 'sort', 'dir']),
            'sin_sucursales_operables' => $sucursales->isEmpty(),
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

    public function destroy(Inventario $inventario): RedirectResponse
    {
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
