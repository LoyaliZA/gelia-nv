<?php

namespace App\Services\Tiendanube;

use App\Models\Tiendanube\TiendanubeCategoria;
use App\Models\Tiendanube\TiendanubeProducto;
use App\Models\Tiendanube\TiendanubeProductoImagen;
use App\Models\Tiendanube\TiendanubeProductoVariante;
use App\Models\Tiendanube\TiendanubeSyncLog;
use Illuminate\Support\Facades\DB;

class TiendanubeCatalogoSyncService
{
    public function __construct(
        private TiendanubeApiClient $api
    ) {}

    public function sincronizar(TiendanubeSyncLog $log): void
    {
        $log->update(['estado' => 'en_proceso', 'mensaje_error' => null]);

        try {
            $this->sincronizarCategorias($log);
            $this->sincronizarProductos($log);

            $log->update([
                'estado' => 'completado',
                'mensaje_error' => null,
            ]);
        } catch (\Throwable $e) {
            $log->update([
                'estado' => 'error',
                'mensaje_error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function sincronizarCategorias(TiendanubeSyncLog $log): void
    {
        $page = 1;
        $total = 0;
        $idsVistos = [];

        do {
            $chunk = $this->api->getCategoriesPage($page);
            foreach ($chunk as $cat) {
                if (! is_array($cat) || ! isset($cat['id'])) {
                    continue;
                }
                $this->upsertCategoria($cat);
                $idsVistos[] = (int) $cat['id'];
                $total++;
            }
            $log->update([
                'procesados_categorias' => $total,
                'total_categorias' => max($log->total_categorias, $total),
            ]);
            $page++;
        } while (count($chunk) >= (int) config('tiendanube.per_page', 50));

        $eliminados = $this->pruneCategorias($idsVistos);

        $log->update([
            'total_categorias' => $total,
            'procesados_categorias' => $total,
            'eliminados_categorias' => $eliminados,
        ]);
    }

    private function sincronizarProductos(TiendanubeSyncLog $log): void
    {
        $page = 1;
        $total = 0;
        $idsVistos = [];

        do {
            $chunk = $this->api->getProductsPage($page);
            foreach ($chunk as $producto) {
                if (! is_array($producto) || ! isset($producto['id'])) {
                    continue;
                }
                $this->upsertProducto($producto);
                $idsVistos[] = (int) $producto['id'];
                $total++;
            }
            $log->update([
                'procesados_productos' => $total,
                'total_productos' => max($log->total_productos, $total),
            ]);
            $page++;
        } while (count($chunk) >= (int) config('tiendanube.per_page', 50));

        $eliminados = $this->pruneProductos($idsVistos);

        $log->update([
            'total_productos' => $total,
            'procesados_productos' => $total,
            'eliminados_productos' => $eliminados,
        ]);
    }

    /**
     * @param  list<int>  $idsVistos
     */
    private function pruneProductos(array $idsVistos): int
    {
        $idsVistos = array_values(array_unique(array_filter($idsVistos)));

        $query = TiendanubeProducto::query();
        if ($idsVistos !== []) {
            $query->whereNotIn('id', $idsVistos);
        }

        $ids = $query->pluck('id');
        if ($ids->isEmpty()) {
            return 0;
        }

        TiendanubeProducto::whereIn('id', $ids)->delete();

        return $ids->count();
    }

    /**
     * @param  list<int>  $idsVistos
     */
    private function pruneCategorias(array $idsVistos): int
    {
        $idsVistos = array_values(array_unique(array_filter($idsVistos)));

        $query = TiendanubeCategoria::query();
        if ($idsVistos !== []) {
            $query->whereNotIn('id', $idsVistos);
        }

        $ids = $query->pluck('id');
        if ($ids->isEmpty()) {
            return 0;
        }

        TiendanubeCategoria::whereIn('id', $ids)->delete();

        return $ids->count();
    }

    /**
     * @param  array<string, mixed>  $cat
     */
    public function upsertCategoria(array $cat): TiendanubeCategoria
    {
        return TiendanubeCategoria::updateOrCreate(
            ['id' => (int) $cat['id']],
            [
                'name' => $cat['name'] ?? null,
                'handle' => $cat['handle'] ?? null,
                'description' => $cat['description'] ?? null,
                'parent_id' => $cat['parent'] ?? $cat['parent_id'] ?? null,
                'seo_title' => $this->truncateSeo($this->localizedToString($cat['seo_title'] ?? null), 70),
                'seo_description' => $this->truncateSeo($this->localizedToString($cat['seo_description'] ?? null), 320),
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $producto
     */
    public function upsertProducto(array $producto): TiendanubeProducto
    {
        return DB::transaction(function () use ($producto) {
            $id = (int) $producto['id'];

            $model = TiendanubeProducto::updateOrCreate(
                ['id' => $id],
                [
                    'name' => $producto['name'] ?? null,
                    'description' => $producto['description'] ?? null,
                    'handle' => $producto['handle'] ?? null,
                    'brand' => $this->localizedToString($producto['brand'] ?? null),
                    'published' => (bool) ($producto['published'] ?? false),
                    'free_shipping' => (bool) ($producto['free_shipping'] ?? false),
                    'requires_shipping' => array_key_exists('requires_shipping', $producto)
                        ? (bool) $producto['requires_shipping']
                        : true,
                    'video_url' => $this->truncateSeo($this->localizedToString($producto['video_url'] ?? null), 2048),
                    'seo_title' => $this->truncateSeo($this->localizedToString($producto['seo_title'] ?? null), 70),
                    'seo_description' => $this->truncateSeo($this->localizedToString($producto['seo_description'] ?? null), 320),
                    'tags' => $this->localizedToString($producto['tags'] ?? null),
                    'attributes' => $producto['attributes'] ?? null,
                    'canonical_url' => $this->truncateSeo($this->localizedToString($producto['canonical_url'] ?? null), 2048),
                    'synced_at' => now(),
                ]
            );

            $this->syncImagenes($id, $producto['images'] ?? []);
            $this->syncVariantes($id, $producto['variants'] ?? []);
            $this->syncCategoriasProducto($id, $producto['categories'] ?? []);

            return $model;
        });
    }

    /**
     * @param  list<array<string, mixed>>  $images
     */
    private function syncImagenes(int $productoId, array $images): void
    {
        $ids = [];
        foreach ($images as $img) {
            if (! is_array($img) || ! isset($img['id'])) {
                continue;
            }
            $imgId = (int) $img['id'];
            $ids[] = $imgId;
            TiendanubeProductoImagen::updateOrCreate(
                ['id' => $imgId],
                [
                    'producto_id' => $productoId,
                    'src' => $this->truncateSeo($this->localizedToString($img['src'] ?? null), 2048),
                    'position' => (int) ($img['position'] ?? 1),
                    'alt' => $this->truncateSeo($this->localizedToString($img['alt'] ?? null), 512),
                ]
            );
        }

        $query = TiendanubeProductoImagen::where('producto_id', $productoId);
        if ($ids !== []) {
            $query->whereNotIn('id', $ids);
        }
        $query->delete();
    }

    /**
     * @param  list<array<string, mixed>>  $variants
     */
    private function syncVariantes(int $productoId, array $variants): void
    {
        $ids = [];
        foreach ($variants as $v) {
            if (! is_array($v) || ! isset($v['id'])) {
                continue;
            }
            $vid = (int) $v['id'];
            $ids[] = $vid;
            TiendanubeProductoVariante::updateOrCreate(
                ['id' => $vid],
                [
                    'producto_id' => $productoId,
                    'sku' => $this->localizedToString($v['sku'] ?? null),
                    'price' => $this->nullableDecimal($v['price'] ?? null),
                    'promotional_price' => $this->nullableDecimal($v['promotional_price'] ?? null),
                    'cost' => $this->nullableDecimal($v['cost'] ?? null),
                    'stock' => $this->normalizeStock($v['stock'] ?? null),
                    'stock_management' => (bool) ($v['stock_management'] ?? false),
                    'values' => $v['values'] ?? null,
                    'barcode' => $this->localizedToString($v['barcode'] ?? null),
                    'weight' => $this->nullableDecimal($v['weight'] ?? null),
                ]
            );
        }

        $query = TiendanubeProductoVariante::where('producto_id', $productoId);
        if ($ids !== []) {
            $query->whereNotIn('id', $ids);
        }
        $query->delete();
    }

    /**
     * @param  list<int|array<string, mixed>>  $categories
     */
    private function syncCategoriasProducto(int $productoId, array $categories): void
    {
        $ids = [];
        foreach ($categories as $cat) {
            $cid = is_array($cat) ? (int) ($cat['id'] ?? 0) : (int) $cat;
            if ($cid <= 0) {
                continue;
            }
            if (! TiendanubeCategoria::whereKey($cid)->exists()) {
                continue;
            }
            $ids[] = $cid;
        }

        $producto = TiendanubeProducto::find($productoId);
        if ($producto) {
            $producto->categorias()->sync($ids);
        }
    }

    /**
     * LocalizedString de Tiendanube: string plano o mapa de idiomas.
     */
    public function localizedToString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value) || is_numeric($value)) {
            $str = trim((string) $value);

            return $str === '' ? null : $str;
        }

        if (! is_array($value)) {
            return null;
        }

        foreach (['es', 'es_MX', 'es_AR', 'pt', 'en'] as $lang) {
            if (isset($value[$lang]) && (is_string($value[$lang]) || is_numeric($value[$lang]))) {
                $str = trim((string) $value[$lang]);
                if ($str !== '') {
                    return $str;
                }
            }
        }

        foreach ($value as $item) {
            if (is_string($item) || is_numeric($item)) {
                $str = trim((string) $item);
                if ($str !== '') {
                    return $str;
                }
            }
            if (is_array($item)) {
                $nested = $this->localizedToString($item);
                if ($nested !== null) {
                    return $nested;
                }
            }
        }

        return null;
    }

    public function truncateSeo(?string $value, int $max): ?string
    {
        if ($value === null) {
            return null;
        }

        return mb_substr($value, 0, $max);
    }

    /**
     * Stock clásico: "" = ilimitado → null.
     */
    private function normalizeStock(mixed $stock): ?int
    {
        if ($stock === null || $stock === '') {
            return null;
        }

        if (is_numeric($stock)) {
            return (int) $stock;
        }

        return null;
    }

    private function nullableDecimal(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }
}
