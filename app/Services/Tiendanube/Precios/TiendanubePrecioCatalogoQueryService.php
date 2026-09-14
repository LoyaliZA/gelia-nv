<?php

namespace App\Services\Tiendanube\Precios;

use App\Models\Tiendanube\TiendanubeCategoria;
use App\Models\Tiendanube\TiendanubePrecioFuenteVersion;
use App\Models\Tiendanube\TiendanubeProducto;
use App\Models\Tiendanube\TiendanubeProductoVariante;
use App\Models\Tiendanube\TiendanubeSyncLog;
use Illuminate\Database\Eloquent\Builder;

class TiendanubePrecioCatalogoQueryService
{
    public function __construct(
        private readonly TiendanubePrecioFuenteResolverService $fuentes,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function listar(TiendanubePrecioCatalogoFiltros $filtros, int $storeId, bool $puedeVerCosto): array
    {
        $filtros = $this->sinFiltroCostoSiAplica($filtros, $puedeVerCosto);

        $base = $this->query($filtros, $puedeVerCosto);
        $variantesTotal = (clone $base)->count();
        $productosDistintos = (int) (clone $base)->distinct()->count('producto_id');

        $paginado = $this->ordenar(clone $base, $filtros)
            ->paginate($filtros->perPage, ['*'], 'page', $filtros->page)
            ->withQueryString();

        $idsPagina = collect($paginado->items())->map(fn (TiendanubeProductoVariante $v) => (int) $v->id)->all();
        $locales = $storeId > 0 && $idsPagina !== []
            ? $this->indexarFuentesPagina($storeId, $idsPagina, $puedeVerCosto)
            : [];

        $filas = collect($paginado->items())->map(
            fn (TiendanubeProductoVariante $variante) => $this->mapearFila($variante, $locales[$variante->id] ?? null, $puedeVerCosto)
        )->values()->all();

        $catalogoTotal = TiendanubeProducto::query()->count();
        $estado = $catalogoTotal === 0
            ? 'sin_primera_carga'
            : ($variantesTotal === 0 ? 'sin_coincidencias' : 'listo');

        return [
            'data' => $filas,
            'meta' => [
                'current_page' => $paginado->currentPage(),
                'last_page' => $paginado->lastPage(),
                'per_page' => $paginado->perPage(),
                'total' => $paginado->total(),
                'from' => $paginado->firstItem(),
                'to' => $paginado->lastItem(),
            ],
            'conteos' => [
                'productos_distintos' => $productosDistintos,
                'variantes_total' => $variantesTotal,
                'productos_catalogo' => $catalogoTotal,
                'variantes_catalogo' => TiendanubeProductoVariante::query()->count(),
            ],
            'estado' => $estado,
            'filtros' => $filtros->toArray(),
        ];
    }

    /**
     * @return list<int>
     */
    public function idsUniverso(TiendanubePrecioCatalogoFiltros $filtros, bool $puedeVerCosto): array
    {
        $filtros = $this->sinFiltroCostoSiAplica($filtros, $puedeVerCosto);

        return $this->query($filtros, $puedeVerCosto)
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @param  list<int>  $varianteIds
     */
    public function contarCoincidentes(TiendanubePrecioCatalogoFiltros $filtros, array $varianteIds, bool $puedeVerCosto): int
    {
        if ($varianteIds === []) {
            return 0;
        }

        return $this->query($filtros, $puedeVerCosto)
            ->whereIn('id', $varianteIds)
            ->count();
    }

    /**
     * @return list<array{id: int, nombre: string, parent_id: int|null}>
     */
    public function categoriasFiltro(): array
    {
        return TiendanubeCategoria::query()
            ->orderBy('id')
            ->get()
            ->map(fn (TiendanubeCategoria $c) => [
                'id' => (int) $c->id,
                'nombre' => $c->nombreVisible(),
                'parent_id' => $c->parent_id !== null ? (int) $c->parent_id : null,
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function contextoSync(): array
    {
        $activo = TiendanubeSyncLog::activo();
        $ultimo = TiendanubeSyncLog::query()->orderByDesc('id')->first();
        $ultimoOk = TiendanubeSyncLog::query()->where('estado', 'completado')->orderByDesc('id')->first();
        $maxSynced = TiendanubeProducto::query()->max('synced_at');

        return [
            'proceso_activo' => $activo ? [
                'id' => $activo->id,
                'estado' => $activo->estado,
                'porcentaje' => $activo->progresoPorcentaje(),
                'fase' => $activo->fase,
            ] : null,
            'ultima_actualizacion' => $ultimoOk?->updated_at?->toIso8601String() ?? ($maxSynced ? (string) $maxSynced : null),
            'ultimo_sync' => $ultimo ? [
                'id' => $ultimo->id,
                'estado' => $ultimo->estado,
                'mensaje_error' => $ultimo->mensaje_error,
                'updated_at' => $ultimo->updated_at?->toIso8601String(),
            ] : null,
        ];
    }

    private function sinFiltroCostoSiAplica(TiendanubePrecioCatalogoFiltros $filtros, bool $puedeVerCosto): TiendanubePrecioCatalogoFiltros
    {
        if ($puedeVerCosto) {
            return $filtros;
        }

        return TiendanubePrecioCatalogoFiltros::fromArray(array_merge($filtros->toArray(), [
            'costo' => TiendanubePrecioCatalogoFiltros::COSTO_CUALQUIERA,
        ]));
    }

    /**
     * @return Builder<TiendanubeProductoVariante>
     */
    public function query(TiendanubePrecioCatalogoFiltros $filtros, bool $puedeVerCosto): Builder
    {
        $query = TiendanubeProductoVariante::query()->with([
            'producto.imagenes',
            'producto.categorias',
        ]);

        if ($filtros->q !== '') {
            $texto = $filtros->q;
            $like = '%'.$texto.'%';
            $query->where(function (Builder $inner) use ($texto, $like) {
                $inner->where('sku', 'LIKE', $like)
                    ->orWhereHas('producto', fn (Builder $p) => $p->buscarTextoCatalogo($texto, true));
            });
        }

        $categoriaIds = $this->expandirCategorias($filtros->categoriaIds, $filtros->incluirSubcategorias);
        if ($categoriaIds !== [] || $filtros->sinCategoria) {
            $query->where(function (Builder $inner) use ($categoriaIds, $filtros) {
                if ($categoriaIds !== []) {
                    $inner->whereHas('producto.categorias', fn (Builder $c) => $c->whereIn('tiendanube_categorias.id', $categoriaIds));
                }
                if ($filtros->sinCategoria) {
                    $method = $categoriaIds !== [] ? 'orWhereDoesntHave' : 'whereDoesntHave';
                    $inner->{$method}('producto.categorias');
                }
            });
        }

        if ($filtros->precioMin !== null) {
            $query->whereNotNull('price')->where('price', '>=', $filtros->precioMin);
        }
        if ($filtros->precioMax !== null) {
            $query->whereNotNull('price')->where('price', '<=', $filtros->precioMax);
        }

        if ($filtros->promocion === TiendanubePrecioCatalogoFiltros::PROMOCION_CON) {
            $query->whereNotNull('promotional_price');
        } elseif ($filtros->promocion === TiendanubePrecioCatalogoFiltros::PROMOCION_SIN) {
            $query->whereNull('promotional_price');
        }

        if ($puedeVerCosto && $filtros->costo === TiendanubePrecioCatalogoFiltros::COSTO_CON) {
            $query->whereNotNull('cost');
        } elseif ($puedeVerCosto && $filtros->costo === TiendanubePrecioCatalogoFiltros::COSTO_SIN) {
            $query->whereNull('cost');
        }

        return $query;
    }

    /**
     * @param  Builder<TiendanubeProductoVariante>  $query
     * @return Builder<TiendanubeProductoVariante>
     */
    private function ordenar(Builder $query, TiendanubePrecioCatalogoFiltros $filtros): Builder
    {
        $columna = match ($filtros->sort) {
            'sku' => 'sku',
            'precio_normal' => 'price',
            default => 'id',
        };

        return $query->orderBy($columna, $filtros->dir)->orderBy('id');
    }

    /**
     * @param  list<int>  $ids
     * @return list<int>
     */
    private function expandirCategorias(array $ids, bool $incluirSubcategorias): array
    {
        $ids = array_values(array_unique(array_filter($ids)));
        if ($ids === [] || ! $incluirSubcategorias) {
            return $ids;
        }

        $todas = TiendanubeCategoria::query()->get(['id', 'parent_id']);
        $hijos = [];
        foreach ($todas as $cat) {
            if ($cat->parent_id === null) {
                continue;
            }
            $hijos[(int) $cat->parent_id][] = (int) $cat->id;
        }

        $salida = [];
        $cola = $ids;
        while ($cola !== []) {
            $id = (int) array_shift($cola);
            if (isset($salida[$id])) {
                continue;
            }
            $salida[$id] = true;
            foreach ($hijos[$id] ?? [] as $hijo) {
                $cola[] = $hijo;
            }
        }

        return array_map('intval', array_keys($salida));
    }

    /**
     * @param  list<int>  $varianteIds
     * @return array<int, array<string, mixed>>
     */
    private function indexarFuentesPagina(int $storeId, array $varianteIds, bool $puedeVerCosto): array
    {
        $tipos = $puedeVerCosto
            ? [
                TiendanubePrecioFuenteVersion::TIPO_COSTO_LOCAL,
                TiendanubePrecioFuenteVersion::TIPO_LISTA_REFERENCIA,
            ]
            : [];

        if ($tipos === []) {
            return [];
        }

        $salida = [];
        foreach ($this->fuentes->resolver($storeId, $varianteIds, $tipos) as $fila) {
            $salida[(int) $fila['variante_id']] = $fila;
        }

        return $salida;
    }

    /**
     * @param  array<string, mixed>|null  $fuentesFila
     * @return array<string, mixed>
     */
    private function mapearFila(TiendanubeProductoVariante $variante, ?array $fuentesFila, bool $puedeVerCosto): array
    {
        $producto = $variante->producto;
        $precioNormal = TiendanubePrecioImporte::formatStored($variante->getRawOriginal('price'));
        $precioPromo = TiendanubePrecioImporte::formatStored($variante->getRawOriginal('promotional_price'));
        $costoRemoto = TiendanubePrecioImporte::formatStored($variante->getRawOriginal('cost'));

        $fila = [
            'variante_id' => (int) $variante->id,
            'producto_id' => $producto ? (int) $producto->id : (int) $variante->producto_id,
            'nombre' => $producto?->nombreVisible() ?? '',
            'sku' => $variante->sku,
            'atributos' => $this->atributos($variante),
            'miniatura' => $producto?->imagenes->sortBy('position')->first()?->src,
            'categorias' => $producto
                ? $producto->categorias->map(fn (TiendanubeCategoria $c) => [
                    'id' => (int) $c->id,
                    'nombre' => $c->nombreVisible(),
                ])->values()->all()
                : [],
            'precio_normal' => $precioNormal,
            'precio_promocional' => $precioPromo,
            'sin_promocion' => $precioPromo === null,
            'promocion_cero' => $precioPromo === '0.00',
            'moneda' => 'MXN',
        ];

        if ($puedeVerCosto) {
            $costoLocal = null;
            $listas = [];
            foreach ($fuentesFila['fuentes'] ?? [] as $fuente) {
                if (($fuente['tipo'] ?? '') === TiendanubePrecioFuenteVersion::TIPO_COSTO_LOCAL) {
                    $costoLocal = $fuente['faltante'] ? null : ($fuente['valor_decimal'] ?? null);
                }
                if (($fuente['tipo'] ?? '') === TiendanubePrecioFuenteVersion::TIPO_LISTA_REFERENCIA && empty($fuente['faltante'])) {
                    $listas[] = [
                        'lista_id' => $fuente['lista_id'] ?? null,
                        'valor_decimal' => $fuente['valor_decimal'] ?? null,
                    ];
                }
            }

            $fila['costo_remoto'] = $costoRemoto;
            $fila['sin_costo_remoto'] = $costoRemoto === null;
            $fila['costo_remoto_cero'] = $costoRemoto === '0.00';
            $fila['costo_local'] = $costoLocal;
            $fila['sin_costo_local'] = $costoLocal === null;
            $fila['listas'] = $listas;
        }

        return $fila;
    }

    /**
     * @return list<string>
     */
    private function atributos(TiendanubeProductoVariante $variante): array
    {
        $values = $variante->values;
        if (! is_array($values)) {
            return [];
        }

        $salida = [];
        foreach ($values as $valor) {
            if (is_string($valor) || is_numeric($valor)) {
                $salida[] = (string) $valor;

                continue;
            }
            if (is_array($valor)) {
                $es = $valor['es'] ?? $valor['es_MX'] ?? reset($valor);
                if ($es !== false && $es !== null) {
                    $salida[] = (string) $es;
                }
            }
        }

        return $salida;
    }
}
