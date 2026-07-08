<?php

namespace App\Http\Controllers\Almacenes;

use App\Http\Controllers\Controller;
use App\Http\Requests\Almacenes\StoreProductoAlmacenRequest;
use App\Http\Requests\Almacenes\UpdateProductoAlmacenRequest;
use App\Models\CatalogoCategoriaProducto;
use App\Models\CatalogoMarcaProducto;
use App\Models\Producto;
use App\Support\Almacenes\OrdenamientoListadoAlmacen;
use App\Services\Almacenes\IniciarImportacionAlmacenService;
use App\Services\Almacenes\RegistrarAuditoriaAlmacenService;
use App\Services\Catalogos\PlantillaImportacionCatalogoService;
use App\Services\Productos\GenerarFolioProductoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Rap2hpoutre\FastExcel\FastExcel;

class ProductoController extends Controller
{
    public function __construct(
        private readonly RegistrarAuditoriaAlmacenService $auditoria,
    ) {}

    public function index(Request $request): Response
    {
        $query = Producto::with(['marca', 'categoria']);

        if ($busqueda = $request->query('q')) {
            $query->buscarPorTexto($busqueda);
        }

        OrdenamientoListadoAlmacen::productos(
            $query,
            $request->query('sort'),
            $request->query('dir'),
        );

        return Inertia::render('Almacenes/Productos/Index', [
            'productos' => $query->paginate(50)->withQueryString(),
            'marcas' => CatalogoMarcaProducto::where('activo', true)->orderBy('nombre')->get(),
            'categorias' => CatalogoCategoriaProducto::orderBy('nombre')->get(),
            'filtros' => $request->only(['q', 'sort', 'dir']),
        ]);
    }

    public function buscar(Request $request): JsonResponse
    {
        $perPage = min(50, max(10, (int) $request->input('per_page', 25)));

        $query = Producto::query()
            ->where('activo', true)
            ->orderBy('descripcion');

        if ($busqueda = trim((string) $request->input('q', ''))) {
            $query->buscarPorTexto($busqueda);
        }

        return response()->json(
            $query->paginate($perPage, ['id', 'sku', 'descripcion', 'folio', 'codigo_barras'])
        );
    }

    public function store(
        StoreProductoAlmacenRequest $request,
        GenerarFolioProductoService $generarFolio,
    ): RedirectResponse {
        $producto = Producto::create([
            'uuid' => (string) Str::uuid(),
            'folio' => $generarFolio->ejecutar(),
            'sku' => $request->sku,
            'descripcion' => $request->descripcion,
            'marca_id' => $request->marca_id,
            'categoria_id' => $request->categoria_id,
            'codigo_barras' => $request->codigo_barras,
            'peso' => $request->peso,
            'activo' => $request->boolean('activo', true),
        ]);

        $this->auditoria->productoCreado($producto->id, $producto->sku, [
            'descripcion' => $producto->descripcion,
            'folio' => $producto->folio,
        ]);

        return back()->with('success', 'Producto registrado correctamente.');
    }

    public function update(UpdateProductoAlmacenRequest $request, Producto $producto): RedirectResponse
    {
        $producto->update([
            'sku' => $request->sku,
            'descripcion' => $request->descripcion,
            'marca_id' => $request->marca_id,
            'categoria_id' => $request->categoria_id,
            'codigo_barras' => $request->codigo_barras,
            'peso' => $request->peso,
            'activo' => $request->boolean('activo', true),
        ]);

        $this->auditoria->productoActualizado($producto->id, $producto->sku, [
            'descripcion' => $producto->descripcion,
        ]);

        return back()->with('success', 'Producto actualizado correctamente.');
    }

    public function destroy(Producto $producto): RedirectResponse
    {
        $sku = $producto->sku;
        $id = $producto->id;
        $producto->delete();

        $this->auditoria->productoEliminado($id, $sku);

        return back()->with('success', 'Producto eliminado correctamente.');
    }

    public function descargarPlantillaImportacion(PlantillaImportacionCatalogoService $plantillaService)
    {
        return $plantillaService->descargar('productos');
    }

    public function importPreview(Request $request)
    {
        $request->validate([
            'archivo' => 'required|file|mimes:csv,xlsx,xls',
        ]);

        $file = $request->file('archivo');
        $extension = $file->getClientOriginalExtension();
        $path = $file->storeAs('temp', 'import_productos_preview.' . $extension);

        $headers = [];
        $rows = (new FastExcel)->import(Storage::path($path));
        foreach ($rows as $row) {
            $headers = array_keys($row);
            break;
        }

        return response()->json([
            'headers' => $headers,
            'file_path' => $path,
        ]);
    }

    public function importIniciar(Request $request, IniciarImportacionAlmacenService $iniciar): JsonResponse
    {
        $resultado = $iniciar->ejecutar($request, 'productos');

        return response()->json(array_merge(['success' => true], $resultado));
    }
}
