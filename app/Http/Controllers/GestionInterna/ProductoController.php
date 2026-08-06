<?php

namespace App\Http\Controllers\GestionInterna;

use App\Http\Controllers\Controller;
use App\Http\Requests\GestionInterna\StoreProductoRequest;
use App\Http\Requests\GestionInterna\UpdateProductoRequest;
use App\Models\Atributo;
use App\Models\CanalComercial;
use App\Models\CatalogoCategoriaProducto;
use App\Models\CatalogoMarcaProducto;
use App\Models\FaseOlfativa;
use App\Models\NotaOlfativa;
use App\Models\Producto;
use App\Models\TipoProducto;
use App\Models\UnidadMedida;
use App\Services\Almacenes\IniciarImportacionAlmacenService;
use App\Services\Almacenes\RegistrarAuditoriaAlmacenService;
use App\Services\Catalogos\PlantillaImportacionCatalogoService;
use App\Services\Productos\ArmarFichaProductoService;
use App\Services\Productos\Extensiones\PerfumeriaExtensionService;
use App\Services\Productos\GenerarFolioProductoService;
use App\Services\Productos\GuardarAtributosProductoService;
use App\Services\Productos\GuardarContenidoProductoService;
use App\Services\Productos\GuardarRelacionesProductoService;
use App\Services\Productos\ResolverExtensionesProductoService;
use App\Support\Almacenes\OrdenamientoListadoAlmacen;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Rap2hpoutre\FastExcel\FastExcel;

class ProductoController extends Controller
{
    public function __construct(
        private readonly RegistrarAuditoriaAlmacenService $auditoria,
        private readonly GuardarAtributosProductoService $guardarAtributos,
        private readonly GuardarRelacionesProductoService $guardarRelaciones,
        private readonly GuardarContenidoProductoService $guardarContenido,
        private readonly ArmarFichaProductoService $ficha,
        private readonly ResolverExtensionesProductoService $resolverExtensiones,
        private readonly PerfumeriaExtensionService $perfumeria,
    ) {}

    public function index(Request $request): Response
    {
        $query = Producto::with(['marca', 'categoria', 'tipoProducto']);

        if ($busqueda = $request->query('q')) {
            $query->buscarPorTexto($busqueda);
        }

        OrdenamientoListadoAlmacen::productos(
            $query,
            $request->query('sort'),
            $request->query('dir'),
        );

        $perfumeriaEnUso = $this->resolverExtensiones->algunaCategoriaUsa('perfumeria');

        return Inertia::render('GestionInterna/Productos/Index', [
            'productos' => $query->paginate(50)->withQueryString(),
            'marcas' => CatalogoMarcaProducto::where('activo', true)->orderBy('nombre')->get(),
            'categorias' => CatalogoCategoriaProducto::orderBy('nombre')->get(['id', 'nombre', 'parent_id']),
            'tipos' => TipoProducto::where('estado', true)->orderBy('nombre')->get(['id', 'nombre', 'codigo']),
            'atributos' => Atributo::where('estado', true)->with(['opciones' => fn ($q) => $q->where('estado', true)->orderBy('orden')])->orderBy('nombre')->get(),
            'atributos_por_categoria' => \App\Models\CategoriaAtributo::query()
                ->orderBy('orden')
                ->get(['categoria_id', 'atributo_id'])
                ->groupBy('categoria_id')
                ->map(fn ($rows) => $rows->pluck('atributo_id')->values()->all())
                ->all(),
            'extensiones_por_categoria' => $this->resolverExtensiones->mapaCodigosPorCategoria(),
            'unidades' => UnidadMedida::where('estado', true)->orderBy('dimension')->orderBy('nombre')->get(['id', 'nombre', 'simbolo', 'dimension']),
            'fases_olfativas' => $perfumeriaEnUso
                ? FaseOlfativa::where('estado', true)->orderBy('orden')->get(['id', 'codigo', 'nombre', 'orden'])
                : [],
            'notas_olfativas' => $perfumeriaEnUso
                ? NotaOlfativa::where('estado', true)->orderBy('nombre')->limit(500)->get(['id', 'nombre', 'slug'])
                : [],
            'canales' => CanalComercial::where('estado', true)->orderBy('nombre')->get(['id', 'nombre', 'codigo']),
            'filtros' => $request->only(['q', 'sort', 'dir']),
        ]);
    }

    public function ficha(Producto $producto): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'ficha' => $this->ficha->paraProducto($producto),
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
        StoreProductoRequest $request,
        GenerarFolioProductoService $generarFolio,
    ): RedirectResponse {
        $producto = Producto::create([
            'uuid' => (string) Str::uuid(),
            'folio' => $generarFolio->ejecutar(),
            'sku' => $request->sku,
            'descripcion' => $request->descripcion,
            'descripcion_corta' => $request->descripcion_corta,
            'marca_id' => $request->marca_id,
            'categoria_id' => $request->categoria_id,
            'tipo_producto_id' => $request->tipo_producto_id,
            'codigo_barras' => $request->codigo_barras,
            'peso' => $request->peso,
            'activo' => $request->boolean('activo', true),
        ]);

        $this->persistirExtensiones($producto, $request->validated());

        $this->auditoria->productoCreado($producto->id, $producto->sku, [
            'descripcion' => $producto->descripcion,
            'folio' => $producto->folio,
        ]);

        return back()->with('success', 'Producto registrado correctamente.');
    }

    public function update(UpdateProductoRequest $request, Producto $producto): RedirectResponse
    {
        $producto->update([
            'sku' => $request->sku,
            'descripcion' => $request->descripcion,
            'descripcion_corta' => $request->descripcion_corta,
            'marca_id' => $request->marca_id,
            'categoria_id' => $request->categoria_id,
            'tipo_producto_id' => $request->tipo_producto_id,
            'codigo_barras' => $request->codigo_barras,
            'peso' => $request->peso,
            'activo' => $request->boolean('activo', true),
        ]);

        $this->persistirExtensiones($producto, $request->validated());

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
        $path = $file->storeAs('temp', 'import_productos_preview.'.$extension);

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

    /** @param  array<string, mixed>  $data */
    private function persistirExtensiones(Producto $producto, array $data): void
    {
        if (array_key_exists('atributos', $data) && is_array($data['atributos'])) {
            $this->guardarAtributos->sincronizar($producto, $data['atributos']);
        }

        $perfumeriaPayload = null;
        if (isset($data['extensiones']['perfumeria']) && is_array($data['extensiones']['perfumeria'])) {
            $perfumeriaPayload = $data['extensiones']['perfumeria'];
        }
        if ($perfumeriaPayload !== null) {
            if (! $this->resolverExtensiones->tiene($producto, 'perfumeria')) {
                throw ValidationException::withMessages([
                    'extensiones.perfumeria' => 'La extensión Perfumería no está habilitada para la categoría de este producto.',
                ]);
            }
            $this->perfumeria->guardar($producto, $perfumeriaPayload);
        }

        if (array_key_exists('relacionados', $data) && is_array($data['relacionados'])) {
            $this->guardarRelaciones->sincronizar($producto, $data['relacionados']);
        }
        if (array_key_exists('contenido', $data) && is_array($data['contenido'])) {
            $this->guardarContenido->upsertInterno($producto, $data['contenido']);
        }
    }
}
