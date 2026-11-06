<?php

namespace App\Http\Controllers\Almacenes;

use App\Http\Controllers\Controller;
use App\Http\Requests\Almacenes\StoreCostoRequest;
use App\Http\Requests\Almacenes\UpdateCostoRequest;
use App\Models\ProductoCosto;
use App\Services\Almacenes\GuardarArchivoVistaPreviaImportacionService;
use App\Services\Almacenes\IniciarImportacionAlmacenService;
use App\Services\Almacenes\LeerEncabezadosArchivoImportacionService;
use App\Services\Almacenes\RegistrarAuditoriaAlmacenService;
use App\Services\Catalogos\PlantillaImportacionCatalogoService;
use App\Services\Productos\CalcularPrecioPublicoService;
use App\Support\Almacenes\OrdenamientoListadoAlmacen;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CostoController extends Controller
{
    public function __construct(
        private readonly RegistrarAuditoriaAlmacenService $auditoria,
        private readonly CalcularPrecioPublicoService $precios,
    ) {}

    public function index(Request $request): Response
    {
        $query = ProductoCosto::with([
            'producto.marca',
            'producto.categoria',
        ]);

        if ($busqueda = $request->query('q')) {
            $query->whereHas('producto', fn ($q) => $q->buscarPorTexto($busqueda));
        }

        if ($request->query('filtro') === 'costo_cero') {
            $query->where('costo', 0);
        }

        OrdenamientoListadoAlmacen::costos(
            $query,
            $request->query('sort'),
            $request->query('dir'),
        );

        return Inertia::render('Almacenes/Costos/Index', [
            'costos' => $query->paginate(50)->withQueryString(),
            'filtros' => (object) $request->only(['q', 'sort', 'dir', 'filtro']),
        ]);
    }

    public function store(StoreCostoRequest $request): RedirectResponse
    {
        $costo = ProductoCosto::create($request->validated());
        $costo->load('producto');
        if ($costo->producto) {
            $this->precios->aplicar($costo->producto);
        }

        $this->auditoria->costoModificado($costo->id, $costo->producto?->sku ?? (string) $costo->producto_id, [
            'accion' => 'creado',
        ]);

        return back()->with('success', 'Costo registrado correctamente.');
    }

    public function update(UpdateCostoRequest $request, ProductoCosto $costo): RedirectResponse
    {
        $costo->update($request->validated());
        $costo->load('producto');
        if ($costo->producto) {
            $this->precios->aplicar($costo->producto);
        }

        $this->auditoria->costoModificado($costo->id, $costo->producto?->sku ?? (string) $costo->producto_id, [
            'accion' => 'actualizado',
        ]);

        return back()->with('success', 'Costo actualizado correctamente.');
    }

    public function destroy(ProductoCosto $costo): RedirectResponse
    {
        $costo->load('producto');
        $producto = $costo->producto;
        $referencia = $producto?->sku ?? (string) $costo->producto_id;
        $id = $costo->id;

        $costo->delete();
        if ($producto) {
            $this->precios->aplicar($producto);
        }

        $this->auditoria->costoEliminado($id, $referencia);

        return back()->with('success', 'Costo eliminado correctamente.');
    }

    public function descargarPlantillaImportacion(PlantillaImportacionCatalogoService $plantillaService)
    {
        return $plantillaService->descargar('costos');
    }

    public function importPreview(
        Request $request,
        GuardarArchivoVistaPreviaImportacionService $vistaPrevia,
        LeerEncabezadosArchivoImportacionService $encabezados,
    ): JsonResponse {
        $request->validate([
            'archivo' => 'required|file|mimes:csv,xlsx,xls',
        ]);

        $path = $vistaPrevia->guardar((int) $request->user()->id, $request->file('archivo'));

        return response()->json([
            'headers' => $encabezados->ejecutar($path),
            'file_path' => $path,
        ]);
    }

    public function importIniciar(Request $request, IniciarImportacionAlmacenService $iniciar): JsonResponse
    {
        $resultado = $iniciar->ejecutar($request, 'costos');

        return response()->json(array_merge(['success' => true], $resultado));
    }
}
