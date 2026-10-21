<?php

namespace App\Services\Productos;

use App\Models\Almacen;
use App\Models\Inventario;
use App\Models\Producto;
use App\Models\ProductoAlmacen;
use App\Models\ProductoCosto;
use App\Models\ProductoVentaAlmacen;
use App\Models\User;
use App\Services\Almacenes\AlcanceAlmacenesService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;

class ConsultarPresenciaProductoService
{
    public function __construct(
        private readonly AlcanceAlmacenesService $alcance,
    ) {}

    /**
     * @return array{filas: LengthAwarePaginator, incluye_costos: bool, incluye_ventas: bool}
     */
    public function consultar(User $user, Producto $producto, int $page, int $perPage): array
    {
        $incluyeCostos = $user->can('almacenes.costos.ver');
        $incluyeVentas = $user->can('reportes.ventas.ver');
        $porPagina = min(max($perPage, 1), 50);

        $operables = $this->alcance->almacenesOperables($user)->keyBy(fn (Almacen $almacen) => (int) $almacen->id);
        $ids = $operables->keys()->map(fn ($id) => (int) $id)->all();

        if ($ids === []) {
            return [
                'filas' => $this->paginar(collect(), $page, $porPagina),
                'incluye_costos' => $incluyeCostos,
                'incluye_ventas' => $incluyeVentas,
            ];
        }

        $asignaciones = ProductoAlmacen::query()
            ->where('producto_id', $producto->id)
            ->whereIn('almacen_id', $ids)
            ->get()
            ->keyBy('almacen_id');
        $inventarios = Inventario::query()
            ->where('producto_id', $producto->id)
            ->whereIn('almacen_id', $ids)
            ->get()
            ->keyBy('almacen_id');
        $costos = ProductoCosto::query()
            ->where('producto_id', $producto->id)
            ->whereIn('almacen_id', $ids)
            ->get()
            ->keyBy('almacen_id');

        $almacenIds = $asignaciones->keys()
            ->merge($inventarios->keys())
            ->merge($costos->keys())
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->sortBy(fn (int $id) => sprintf('%s-%010d', (string) ($operables->get($id)?->codigo ?? ''), $id))
            ->values();

        $pagina = $this->paginar($almacenIds, $page, $porPagina);
        $idsPagina = collect($pagina->items())->map(fn ($id) => (int) $id)->all();
        $ventas = $incluyeVentas ? $this->ventas($producto->id, $idsPagina) : collect();

        $filas = collect($idsPagina)->map(function (int $almacenId) use ($operables, $asignaciones, $inventarios, $costos, $incluyeCostos, $incluyeVentas, $ventas) {
            /** @var Almacen|null $almacen */
            $almacen = $operables->get($almacenId);
            $fila = [
                'almacen_id' => $almacenId,
                'codigo' => $almacen?->codigo,
                'nombre' => $almacen?->nombre,
                'sucursal_id' => $almacen?->sucursal_id,
                'asignado' => $asignaciones->has($almacenId),
            ];
            $fila = array_merge($fila, $this->existencia($inventarios->get($almacenId)));
            $fila['tiene_costo'] = $costos->has($almacenId);
            if ($incluyeCostos) {
                /** @var ProductoCosto|null $costo */
                $costo = $costos->get($almacenId);
                $fila['costo'] = $costo?->costo;
                $fila['costo_reposicion'] = $costo?->costo_reposicion;
                $fila['precio_venta'] = $costo?->precio_venta;
            }
            if ($incluyeVentas) {
                $fila['ventas'] = $ventas->get($almacenId, collect())->values()->all();
            }

            return $fila;
        })->values();

        $pagina->setCollection($filas);

        return [
            'filas' => $pagina,
            'incluye_costos' => $incluyeCostos,
            'incluye_ventas' => $incluyeVentas,
        ];
    }

    /**
     * @return array{tiene_inventario: bool, tiene_cantidad: bool, existencia: mixed, estado_existencia: string}
     */
    private function existencia(?Inventario $inventario): array
    {
        if (! $inventario instanceof Inventario) {
            return [
                'tiene_inventario' => false,
                'tiene_cantidad' => false,
                'existencia' => null,
                'estado_existencia' => 'sin_registro',
            ];
        }

        if ($inventario->existencia === null) {
            return [
                'tiene_inventario' => true,
                'tiene_cantidad' => false,
                'existencia' => null,
                'estado_existencia' => 'nula',
            ];
        }

        $cero = bccomp((string) $inventario->existencia, '0', 3) === 0;

        return [
            'tiene_inventario' => true,
            'tiene_cantidad' => true,
            'existencia' => $inventario->existencia,
            'estado_existencia' => $cero ? 'cero' : 'valor',
        ];
    }

    /**
     * @param  list<int>  $almacenIds
     * @return Collection<int, Collection<int, ProductoVentaAlmacen>>
     */
    private function ventas(int $productoId, array $almacenIds): Collection
    {
        if ($almacenIds === []) {
            return collect();
        }

        return ProductoVentaAlmacen::query()
            ->where('producto_id', $productoId)
            ->whereIn('almacen_id', $almacenIds)
            ->orderByDesc('periodo')
            ->get(['almacen_id', 'periodo', 'cantidad_vendida', 'monto_venta'])
            ->groupBy('almacen_id')
            ->map(fn (Collection $filas) => $filas->take(3)->map(fn (ProductoVentaAlmacen $venta) => [
                'periodo' => $venta->periodo,
                'cantidad_vendida' => $venta->cantidad_vendida,
                'monto_venta' => $venta->monto_venta,
            ]));
    }

    private function paginar(Collection $items, int $page, int $porPagina): Paginator
    {
        $pagina = max($page, 1);
        $total = $items->count();
        $corte = $items->slice(($pagina - 1) * $porPagina, $porPagina)->values();

        return new Paginator($corte, $total, $porPagina, $pagina, [
            'path' => Paginator::resolveCurrentPath(),
            'pageName' => 'presencia_page',
        ]);
    }
}
