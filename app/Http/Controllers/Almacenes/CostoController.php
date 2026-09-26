<?php

namespace App\Http\Controllers\Almacenes;

use App\Http\Controllers\Controller;
use App\Http\Requests\Almacenes\StoreCostoRequest;
use App\Http\Requests\Almacenes\UpdateCostoRequest;
use App\Models\ProductoCosto;
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

class CostoController extends Controller
{
    public function __construct(
        private readonly RegistrarAuditoriaAlmacenService $auditoria,
    ) {}

    public function index(Request $request, AlcanceAlmacenesService $alcance): Response
    {
        $user = $request->user();
        $query = ProductoCosto::with([
            'producto.marca',
            'producto.categoria',
            'almacen.sucursal',
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

        if ($request->query('filtro') === 'costo_cero') {
            $query->where('costo', 0);
        }

        if ($request->query('filtro') === 'sin_precio') {
            $query->whereNull('precio_venta');
        }

        OrdenamientoListadoAlmacen::costos(
            $query,
            $request->query('sort'),
            $request->query('dir'),
        );

        $sucursales = $alcance->sucursalesOperables($user);

        return Inertia::render('Almacenes/Costos/Index', [
            'costos' => $query->paginate(50)->withQueryString(),
            'sucursales' => $sucursales,
            'almacenes' => $alcance->almacenesOperables($user),
            'filtros' => $request->only(['sucursal_id', 'almacen_id', 'q', 'sort', 'dir', 'filtro']),
            'sin_sucursales_operables' => $sucursales->isEmpty(),
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

    public function importPreview(
        Request $request,
        GuardarArchivoVistaPreviaImportacionService $vistaPrevia,
        LeerEncabezadosArchivoImportacionService $encabezados,
        AlcanceAlmacenesService $alcance,
    ): JsonResponse {
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
        $resultado = $iniciar->ejecutar($request, 'costos');

        return response()->json(array_merge(['success' => true], $resultado));
    }
}
