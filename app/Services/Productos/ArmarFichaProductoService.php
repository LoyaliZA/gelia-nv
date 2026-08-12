<?php

namespace App\Services\Productos;

use App\Models\Producto;
use App\Models\ProductoAtributoValor;
use App\Models\ProductoContenido;
use App\Models\ProductoRelacion;
use App\Models\ProductoVentaAlmacen;
use App\Models\Inventario;
use App\Services\Productos\Extensiones\PerfumeriaExtensionService;
use Illuminate\Support\Collection;

/**
 * DTO compacto para UI y GELIA a partir de hechos en BD.
 */
class ArmarFichaProductoService
{
    public function __construct(
        private readonly PerfumeriaExtensionService $perfumeria,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function paraProducto(Producto $producto, ?int $almacenContextoId = null, int $stockMax = 5): array
    {
        $producto->loadMissing([
            'marca:id,nombre',
            'categoria:id,nombre,parent_id',
            'tipoProducto:id,nombre,codigo',
            'atributoValores.atributo:id,nombre,slug,tipo_dato,permite_multiples',
            'atributoValores.opcion:id,nombre,slug',
            'atributoValores.unidad:id,simbolo,nombre',
            'notasOlfativas.nota:id,nombre,slug',
            'notasOlfativas.fase:id,codigo,nombre,orden',
            'relaciones.relacionado:id,sku,descripcion,activo',
            'contenidos.canal:id,codigo,nombre',
        ]);

        $extensiones = [];
        $perfumeria = $this->perfumeria->serializar($producto);
        if ($perfumeria !== null) {
            $extensiones['perfumeria'] = $perfumeria;
        }

        return [
            'id' => $producto->id,
            'sku' => $producto->sku,
            'nombre' => $producto->descripcion,
            'descripcion_corta' => $producto->descripcion_corta,
            'marca' => $producto->marca?->nombre,
            'categoria' => $producto->categoria?->nombre,
            'tipo' => $producto->tipoProducto?->codigo,
            'atributos' => $this->mapAtributos($producto->atributoValores),
            'extensiones' => $extensiones,
            'relacionados' => $producto->relaciones->map(fn (ProductoRelacion $r) => [
                'id' => $r->relacionado?->id,
                'sku' => $r->relacionado?->sku,
                'nombre' => $r->relacionado?->descripcion,
                'tipo' => $r->tipo,
                'activo' => (bool) $r->relacionado?->activo,
            ])->filter(fn ($x) => $x['sku'])->values()->all(),
            'contenido' => $this->mapContenido($producto->contenidos),
            'stock' => $this->mapStock($producto->id, $almacenContextoId, $stockMax),
            'ventas' => $this->mapVentas($producto->id, $almacenContextoId),
        ];
    }

    /** @param  Collection<int, ProductoAtributoValor>  $valores */
    private function mapAtributos(Collection $valores): array
    {
        $out = [];
        foreach ($valores->groupBy('atributo_id') as $group) {
            /** @var ProductoAtributoValor $first */
            $first = $group->first();
            $attr = $first->atributo;
            if (! $attr) {
                continue;
            }
            $slug = $attr->slug;
            if ($attr->tipo_dato === 'opcion') {
                $nombres = $group->map(fn (ProductoAtributoValor $v) => $v->opcion?->nombre)->filter()->values()->all();
                $out[$slug] = $attr->permite_multiples ? $nombres : ($nombres[0] ?? null);
            } elseif ($attr->tipo_dato === 'medida') {
                $out[$slug] = [
                    'valor' => $first->valor_decimal !== null ? (float) $first->valor_decimal : null,
                    'unidad' => $first->unidad?->simbolo,
                ];
            } elseif ($attr->tipo_dato === 'entero') {
                $out[$slug] = $first->valor_entero;
            } elseif ($attr->tipo_dato === 'decimal') {
                $out[$slug] = $first->valor_decimal !== null ? (float) $first->valor_decimal : null;
            } elseif ($attr->tipo_dato === 'booleano') {
                $out[$slug] = (bool) $first->valor_booleano;
            } elseif ($attr->tipo_dato === 'fecha') {
                $out[$slug] = optional($first->valor_fecha)->toDateString();
            } else {
                $out[$slug] = $first->valor_texto;
            }
        }

        return $out;
    }

    /** @param  Collection<int, ProductoContenido>  $contenidos */
    private function mapContenido(Collection $contenidos): ?array
    {
        $preferido = $contenidos->first(fn (ProductoContenido $c) => $c->canal?->codigo === 'gelia')
            ?? $contenidos->first(fn (ProductoContenido $c) => $c->canal?->codigo === 'interno')
            ?? $contenidos->first();

        if (! $preferido) {
            return null;
        }

        return [
            'pitch_venta' => $preferido->pitch_venta,
            'descripcion_corta' => $preferido->descripcion_corta,
            'descripcion_larga' => $preferido->descripcion_larga,
            'seo_titulo' => $preferido->seo_titulo,
            'seo_descripcion' => $preferido->seo_descripcion,
            'canal' => $preferido->canal?->codigo,
        ];
    }

    private function mapStock(int $productoId, ?int $almacenContextoId, int $max): array
    {
        $rows = Inventario::query()
            ->with(['almacen:id,codigo,nombre,tipo_almacen_id', 'almacen.tipoAlmacen:id,nombre'])
            ->where('producto_id', $productoId)
            ->get();

        $sorted = $rows->sortBy(function (Inventario $inv) use ($almacenContextoId) {
            $prio = 2;
            if ($almacenContextoId && (int) $inv->almacen_id === $almacenContextoId) {
                $prio = 0;
            } elseif ((float) $inv->disponible > 0) {
                $prio = 1;
            }

            return [$prio, -1 * (float) $inv->disponible];
        })->take($max);

        return $sorted->values()->map(fn (Inventario $inv) => [
            'a' => $inv->almacen?->codigo ?: $inv->almacen?->nombre,
            'tipo' => $inv->almacen?->tipoAlmacen?->nombre,
            'e' => (float) $inv->existencia,
            'd' => (float) $inv->disponible,
            'contexto' => $almacenContextoId !== null && (int) $inv->almacen_id === $almacenContextoId,
        ])->all();
    }

    private function mapVentas(int $productoId, ?int $almacenId): array
    {
        $q = ProductoVentaAlmacen::query()
            ->with(['almacen:id,codigo,nombre'])
            ->where('producto_id', $productoId)
            ->orderByDesc('periodo')
            ->limit(3);

        if ($almacenId) {
            $q->where('almacen_id', $almacenId);
        }

        return $q->get()->map(fn (ProductoVentaAlmacen $v) => [
            'periodo' => $v->periodo,
            'a' => $v->almacen?->codigo ?: $v->almacen?->nombre,
            'monto' => (float) $v->monto_venta,
            'cant' => $v->cantidad_vendida !== null ? (float) $v->cantidad_vendida : null,
        ])->all();
    }
}
