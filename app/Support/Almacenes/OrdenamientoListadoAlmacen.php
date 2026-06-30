<?php

namespace App\Support\Almacenes;

use App\Models\Almacen;
use App\Models\CatalogoCategoriaProducto;
use App\Models\CatalogoMarcaProducto;
use App\Models\Producto;
use Illuminate\Database\Eloquent\Builder;

class OrdenamientoListadoAlmacen
{
    public static function direccion(?string $dir): string
    {
        return strtolower($dir ?? '') === 'desc' ? 'desc' : 'asc';
    }

    public static function productos(Builder $query, ?string $sort, ?string $dir): Builder
    {
        $dir = self::direccion($dir);

        return match ($sort) {
            'folio' => $query->orderBy('folio', $dir),
            'producto' => $query->orderBy('descripcion', $dir),
            'sku' => $query->orderBy('sku', $dir),
            'marca' => $query->orderBy(
                CatalogoMarcaProducto::select('nombre')
                    ->whereColumn('catalogo_marcas_producto.id', 'productos.marca_id')
                    ->limit(1),
                $dir
            ),
            'categoria' => $query->orderBy(
                CatalogoCategoriaProducto::select('nombre')
                    ->whereColumn('catalogo_categoria_productos.id', 'productos.categoria_id')
                    ->limit(1),
                $dir
            ),
            'codigo_barras' => $query->orderBy('codigo_barras', $dir),
            'peso' => $query->orderBy('peso', $dir),
            default => $query->orderByDesc('id'),
        };
    }

    public static function costos(Builder $query, ?string $sort, ?string $dir): Builder
    {
        $dir = self::direccion($dir);

        return match ($sort) {
            'producto' => $query->orderBy(
                Producto::select('descripcion')
                    ->whereColumn('productos.id', 'producto_costos.producto_id')
                    ->limit(1),
                $dir
            ),
            'almacen' => $query->orderBy(
                Almacen::select('nombre')
                    ->whereColumn('almacenes.id', 'producto_costos.almacen_id')
                    ->limit(1),
                $dir
            ),
            'costo' => $query->orderBy('costo', $dir),
            'costo_reposicion' => $query->orderBy('costo_reposicion', $dir),
            'precio_venta' => $query->orderBy('precio_venta', $dir),
            default => $query->orderByDesc('id'),
        };
    }

    public static function inventarios(Builder $query, ?string $sort, ?string $dir): Builder
    {
        $dir = self::direccion($dir);

        return match ($sort) {
            'producto' => $query->orderBy(
                Producto::select('descripcion')
                    ->whereColumn('productos.id', 'inventarios.producto_id')
                    ->limit(1),
                $dir
            ),
            'almacen' => $query->orderBy(
                Almacen::select('nombre')
                    ->whereColumn('almacenes.id', 'inventarios.almacen_id')
                    ->limit(1),
                $dir
            ),
            'ubicacion' => $query->orderBy('ubicacion', $dir),
            'existencia' => $query->orderBy('existencia', $dir),
            'apartado' => $query->orderBy('apartado', $dir),
            'disponible' => $query->orderByRaw('(existencia - apartado) ' . $dir),
            'transito_oc' => $query->orderBy('transito_oc', $dir),
            'transito_ot' => $query->orderBy('transito_ot', $dir),
            default => $query->orderByDesc('id'),
        };
    }
}
