<?php

namespace App\Http\Controllers\Almacenes;

use App\Http\Controllers\Controller;
use App\Models\Inventario;
use App\Models\Producto;
use App\Models\ProductoAlmacen;
use App\Models\ProductoCosto;
use App\Services\Almacenes\AlcanceAlmacenesService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CoberturaController extends Controller
{
    public function index(Request $request, AlcanceAlmacenesService $alcance): Response
    {
        $this->autorizar();
        $user = $request->user();

        $almacenId = $request->query('almacen_id');
        if ($request->query('sucursal_id') && ! $almacenId) {
            $alcance->asegurarSucursalOperable($user, (int) $request->query('sucursal_id'));
        }
        if ($almacenId) {
            $alcance->asegurarAlmacenOperable($user, (int) $almacenId);
        }
        $query = Producto::query()
            ->with(['marca', 'categoria'])
            ->when($request->query('q'), fn ($q, $busqueda) => $q->buscarPorTexto($busqueda))
            ->when($request->query('activo') === '1', fn ($q) => $q->where('activo', true))
            ->when($request->query('activo') === '0', fn ($q) => $q->where('activo', false));

        if ($almacenId) {
            $query->when($request->query('asignacion') === 'sin', function ($q) use ($almacenId) {
                $q->whereDoesntHave('asignacionesAlmacen', fn ($a) => $a->where('almacen_id', $almacenId));
            });
            $query->when($request->query('asignacion') === 'con', function ($q) use ($almacenId) {
                $q->whereHas('asignacionesAlmacen', fn ($a) => $a->where('almacen_id', $almacenId));
            });
            $query->when($request->query('costo') === 'sin', function ($q) use ($almacenId) {
                $q->whereDoesntHave('costos', fn ($c) => $c->where('almacen_id', $almacenId));
            });
        }

        $productos = $query->orderBy('descripcion')->paginate(50)->withQueryString();

        $ids = $productos->getCollection()->pluck('id')->all();
        $asignaciones = [];
        $costos = [];
        $inventarios = [];

        if ($almacenId && $ids !== []) {
            $asignaciones = ProductoAlmacen::where('almacen_id', $almacenId)->whereIn('producto_id', $ids)->get()->keyBy('producto_id');
            $costos = ProductoCosto::where('almacen_id', $almacenId)->whereIn('producto_id', $ids)->get()->keyBy('producto_id');
            $inventarios = Inventario::where('almacen_id', $almacenId)->whereIn('producto_id', $ids)->get()->keyBy('producto_id');
        }

        $filas = $productos->getCollection()->map(function (Producto $p) use ($almacenId, $asignaciones, $costos, $inventarios) {
            $asig = $asignaciones[$p->id] ?? null;
            $costo = $costos[$p->id] ?? null;
            $inv = $inventarios[$p->id] ?? null;

            return [
                'id' => $p->id,
                'sku' => $p->sku,
                'descripcion' => $p->descripcion,
                'marca' => $p->marca?->nombre,
                'categoria' => $p->categoria?->nombre,
                'activo' => $p->activo,
                'asignado' => $asig !== null,
                'ubicacion' => $asig?->ubicacion,
                'costo' => $costo?->costo,
                'precio_venta' => $costo?->precio_venta,
                'existencia' => $inv?->existencia,
                'tiene_cantidad' => $inv !== null && $inv->existencia !== null,
            ];
        });
        $productos->setCollection($filas);

        $sucursales = $alcance->sucursalesOperables($user);

        return Inertia::render('Almacenes/Cobertura/Index', [
            'filas' => $productos,
            'sucursales' => $sucursales,
            'almacenes' => $alcance->almacenesOperables($user),
            'filtros' => $request->only(['sucursal_id', 'almacen_id', 'q', 'activo', 'asignacion', 'costo']),
            'sin_sucursales_operables' => $sucursales->isEmpty(),
        ]);
    }

    public function asignar(Request $request, AlcanceAlmacenesService $alcance): RedirectResponse
    {
        $this->autorizarGestionar();

        $validated = $request->validate([
            'almacen_id' => 'required|exists:almacenes,id',
            'producto_ids' => 'required|array|min:1',
            'producto_ids.*' => 'integer|exists:productos,id',
            'ubicacion' => 'nullable|string|max:50',
        ]);

        $alcance->asegurarAlmacenOperable($request->user(), (int) $validated['almacen_id']);

        $creadas = 0;
        foreach ($validated['producto_ids'] as $productoId) {
            $row = ProductoAlmacen::firstOrCreate(
                [
                    'producto_id' => $productoId,
                    'almacen_id' => $validated['almacen_id'],
                ],
                [
                    'ubicacion' => $validated['ubicacion'] ?? null,
                    'activo_en_almacen' => true,
                    'origen_asignacion' => 'manual',
                ]
            );
            if ($row->wasRecentlyCreated) {
                $creadas++;
            }
        }

        return back()->with('success', "Asignaciones creadas: {$creadas}.");
    }

    private function autorizar(): void
    {
        $user = request()->user();
        if ($user->can('almacenes.inventarios.ver') || $user->can('catalogos.gestionar')) {
            return;
        }
        abort(403);
    }

    private function autorizarGestionar(): void
    {
        $user = request()->user();
        if ($user->can('almacenes.inventarios.gestionar') || $user->can('catalogos.gestionar')) {
            return;
        }
        abort(403);
    }
}
