<?php

namespace App\Http\Controllers\Almacenes;

use App\Http\Controllers\Controller;
use App\Http\Requests\Almacenes\StoreCostoRequest;
use App\Http\Requests\Almacenes\UpdateCostoRequest;
use App\Models\Almacen;
use App\Models\ProductoCosto;
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

class CostoController extends Controller
{
    public function __construct(
        private readonly RegistrarAuditoriaAlmacenService $auditoria,
    ) {}

    public function index(Request $request): Response
    {
        $query = ProductoCosto::with([
            'producto.marca',
            'producto.categoria',
            'almacen.sucursal',
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

        OrdenamientoListadoAlmacen::costos(
            $query,
            $request->query('sort'),
            $request->query('dir'),
        );

        return Inertia::render('Almacenes/Costos/Index', [
            'costos' => $query->paginate(50)->withQueryString(),
            'sucursales' => Sucursal::where('activo', true)->orderBy('nombre')->get(),
            'almacenes' => Almacen::with('sucursal')->where('activo', true)->orderBy('nombre')->get(),
            'filtros' => $request->only(['sucursal_id', 'almacen_id', 'q', 'sort', 'dir']),
        ]);
    }

    public function store(StoreCostoRequest $request): RedirectResponse
    {
        $costo = ProductoCosto::create($request->validated());
        $costo->load('producto');

        $this->auditoria->costoModificado($costo->id, $costo->producto?->sku ?? (string) $costo->producto_id, [
            'accion' => 'creado',
            'almacen_id' => $costo->almacen_id,
        ]);

        return back()->with('success', 'Costo registrado correctamente.');
    }

    public function update(UpdateCostoRequest $request, ProductoCosto $costo): RedirectResponse
    {
        $costo->update($request->validated());
        $costo->load('producto');

        $this->auditoria->costoModificado($costo->id, $costo->producto?->sku ?? (string) $costo->producto_id, [
            'accion' => 'actualizado',
            'almacen_id' => $costo->almacen_id,
        ]);

        return back()->with('success', 'Costo actualizado correctamente.');
    }

    public function destroy(ProductoCosto $costo): RedirectResponse
    {
        $costo->load('producto');
        $referencia = $costo->producto?->sku ?? (string) $costo->producto_id;
        $id = $costo->id;

        $costo->delete();

        $this->auditoria->costoEliminado($id, $referencia);

        return back()->with('success', 'Costo eliminado correctamente.');
    }

    public function descargarPlantillaImportacion(PlantillaImportacionCatalogoService $plantillaService)
    {
        return $plantillaService->descargar('costos');
    }

    public function importPreview(Request $request): JsonResponse
    {
        $request->validate([
            'archivo' => 'required|file|mimes:csv,xlsx,xls',
            'almacen_id' => 'required|exists:almacenes,id',
        ]);

        $file = $request->file('archivo');
        $extension = $file->getClientOriginalExtension();
        $path = $file->storeAs('temp', 'import_costos_preview.' . $extension);

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
        $resultado = $iniciar->ejecutar($request, 'costos');

        return response()->json(array_merge(['success' => true], $resultado));
    }
}
