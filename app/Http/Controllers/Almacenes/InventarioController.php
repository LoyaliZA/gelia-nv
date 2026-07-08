<?php

namespace App\Http\Controllers\Almacenes;

use App\Http\Controllers\Controller;
use App\Http\Requests\Almacenes\StoreInventarioRequest;
use App\Http\Requests\Almacenes\UpdateInventarioRequest;
use App\Models\Almacen;
use App\Models\Inventario;
use App\Models\Sucursal;
use App\Support\Almacenes\OrdenamientoListadoAlmacen;
use App\Services\Almacenes\IniciarImportacionAlmacenService;
use App\Services\Almacenes\RegistrarAuditoriaAlmacenService;
use App\Services\Catalogos\PlantillaImportacionCatalogoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Rap2hpoutre\FastExcel\FastExcel;

class InventarioController extends Controller
{
    public function __construct(
        private readonly RegistrarAuditoriaAlmacenService $auditoria,
    ) {}

    public function index(Request $request): Response
    {
        $query = Inventario::with([
            'producto.marca',
            'producto.categoria',
            'almacen.sucursal',
            'almacen.tipoAlmacen',
        ]);

        if ($sucursalId = $request->query('sucursal_id')) {
            $query->whereHas('almacen', fn ($q) => $q->where('sucursal_id', $sucursalId));
        }

        if ($almacenId = $request->query('almacen_id')) {
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

        return Inertia::render('Almacenes/Inventarios/Index', [
            'inventarios' => $query->paginate(50)->withQueryString(),
            'sucursales' => Sucursal::where('activo', true)->orderBy('nombre')->get(),
            'almacenes' => Almacen::with(['sucursal', 'tipoAlmacen'])->where('activo', true)->orderBy('nombre')->get(),
            'filtros' => $request->only(['sucursal_id', 'almacen_id', 'q', 'sort', 'dir']),
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

    public function importPreview(Request $request)
    {
        $request->validate([
            'archivo' => 'required|file|mimes:csv,xlsx,xls',
            'almacen_id' => 'required|exists:almacenes,id',
        ]);

        $file = $request->file('archivo');
        $path = $file->storeAs('temp', 'import_preview.' . $file->getClientOriginalExtension());

        $headers = [];
        $rows = (new FastExcel)->import(Storage::path($path));
        foreach ($rows as $row) {
            $headers = array_keys($row);
            break;
        }

        return response()->json([
            'headers' => $headers,
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
