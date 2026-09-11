<?php

namespace App\Services\Tiendanube;

use App\Exceptions\Tiendanube\TiendanubeApiException;
use App\Models\Tiendanube\TiendanubeCategoria;
use App\Models\Tiendanube\TiendanubeConfiguracion;
use App\Models\Tiendanube\TiendanubeProducto;
use App\Models\Tiendanube\TiendanubeProductoImagen;
use App\Models\Tiendanube\TiendanubeProductoVariante;
use App\Models\Tiendanube\TiendanubeSyncLog;
use App\Models\Tiendanube\TiendanubeUbicacion;
use App\Models\Tiendanube\TiendanubeVarianteNivel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class TiendanubeCatalogoSyncService
{
    public function __construct(
        private TiendanubeApiClient $api
    ) {}

    public function sincronizar(TiendanubeSyncLog $log): void
    {
        $log->update(['estado' => 'en_proceso', 'mensaje_error' => null]);

        try {
            $ubicaciones = $this->sincronizarUbicaciones();
            $categorias = $this->sincronizarCategorias($log);
            $productos = $this->sincronizarProductos($log);
            $this->marcarMultiInventarioSiAplica();

            $parcial = ! $categorias['completa'] || ! $productos['completa'];
            $motivos = array_values(array_filter([
                $ubicaciones['motivo'] ?? null,
                $categorias['motivo'] ?? null,
                $productos['motivo'] ?? null,
            ]));

            $log->update([
                'estado' => $parcial ? 'parcial' : 'completado',
                'mensaje_error' => $parcial
                    ? 'Alcance parcial: '.implode(' | ', $motivos)
                    : null,
            ]);
        } catch (\Throwable $e) {
            $log->update([
                'estado' => 'error',
                'mensaje_error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * @return array{completa: bool, motivo: ?string}
     */
    private function sincronizarCategorias(TiendanubeSyncLog $log): array
    {
        $total = 0;
        $idsVistos = [];

        $pagina = $this->recorrerColeccion('/categories', function (array $cat) use ($log, &$total, &$idsVistos): void {
            if (! isset($cat['id'])) {
                return;
            }
            $this->upsertCategoria($cat);
            $idsVistos[] = (int) $cat['id'];
            $total++;
            $log->update([
                'procesados_categorias' => $total,
                'total_categorias' => max($log->total_categorias, $total),
            ]);
        });

        if ($pagina['paginas'] === 0 && ! $pagina['completa'] && $pagina['error'] !== null) {
            throw $pagina['error'];
        }

        $eliminados = $this->pruneCategorias($idsVistos, $pagina['completa']);

        $log->update([
            'total_categorias' => $total,
            'procesados_categorias' => $total,
            'eliminados_categorias' => $eliminados,
        ]);

        return [
            'completa' => $pagina['completa'],
            'motivo' => $pagina['completa'] ? null : $this->motivoParcial('categorías', $pagina),
        ];
    }

    /**
     * @return array{completa: bool, motivo: ?string}
     */
    private function sincronizarProductos(TiendanubeSyncLog $log): array
    {
        $total = 0;
        $idsVistos = [];

        $pagina = $this->recorrerColeccion('/products', function (array $producto) use ($log, &$total, &$idsVistos): void {
            if (! isset($producto['id'])) {
                return;
            }
            $this->upsertProducto($producto);
            $idsVistos[] = (int) $producto['id'];
            $total++;
            $log->update([
                'procesados_productos' => $total,
                'total_productos' => max($log->total_productos, $total),
            ]);
        });

        if ($pagina['paginas'] === 0 && ! $pagina['completa'] && $pagina['error'] !== null) {
            throw $pagina['error'];
        }

        $eliminados = $this->pruneProductos($idsVistos, $pagina['completa']);

        $log->update([
            'total_productos' => $total,
            'procesados_productos' => $total,
            'eliminados_productos' => $eliminados,
        ]);

        return [
            'completa' => $pagina['completa'],
            'motivo' => $pagina['completa'] ? null : $this->motivoParcial('productos', $pagina),
        ];
    }

    /**
     * Location no tumba el catálogo: 403/404/error se anotan y se sigue.
     *
     * @return array{completa: bool, motivo: ?string}
     */
    private function sincronizarUbicaciones(): array
    {
        try {
            $lista = $this->api->getLocations();
        } catch (TiendanubeApiException $e) {
            return [
                'completa' => true,
                'motivo' => null,
            ];
        }

        if (! is_array($lista)) {
            return ['completa' => true, 'motivo' => null];
        }

        $storeId = (int) (TiendanubeConfiguracion::obtener()->store_id ?? 0);
        $idsVistos = [];
        $now = now();

        foreach ($lista as $loc) {
            if (! is_array($loc) || ! isset($loc['id'])) {
                continue;
            }
            $id = (string) $loc['id'];
            $idsVistos[] = $id;
            $remoteUpdated = $loc['updated_at'] ?? null;

            TiendanubeUbicacion::updateOrCreate(
                ['id' => $id],
                [
                    'store_id' => $storeId,
                    'name' => $loc['name'] ?? null,
                    'is_default' => (bool) ($loc['is_default'] ?? false),
                    'priority' => isset($loc['priority']) ? (int) $loc['priority'] : null,
                    'tags' => $loc['tags'] ?? null,
                    'activa' => true,
                    'remote_updated_at' => is_string($remoteUpdated) ? $remoteUpdated : null,
                    'synced_at' => $now,
                ]
            );
        }

        if ($idsVistos !== []) {
            TiendanubeUbicacion::query()
                ->where('store_id', $storeId)
                ->whereNotIn('id', $idsVistos)
                ->update(['activa' => false]);
        }

        return ['completa' => true, 'motivo' => null];
    }

    private function marcarMultiInventarioSiAplica(): void
    {
        $config = TiendanubeConfiguracion::obtener();
        $tieneNiveles = TiendanubeVarianteNivel::query()->exists();
        $ubicacionesActivas = TiendanubeUbicacion::query()->where('activa', true)->count();
        if ($tieneNiveles || $ubicacionesActivas > 1) {
            $config->multi_inventario_activo = true;
            $config->save();
        }
    }

    /**
     * @param  callable(array<string, mixed>): void  $onItem
     * @return array{completa: bool, paginas: int, error: ?Throwable}
     */
    private function recorrerColeccion(string $path, callable $onItem): array
    {
        $paginas = 0;
        try {
            foreach ($this->api->paginatePath($path) as $chunk) {
                $paginas++;
                foreach ($chunk as $item) {
                    if (is_array($item)) {
                        $onItem($item);
                    }
                }
            }

            return ['completa' => true, 'paginas' => $paginas, 'error' => null];
        } catch (Throwable $e) {
            return ['completa' => false, 'paginas' => $paginas, 'error' => $e];
        }
    }

    /**
     * @param  array{completa: bool, paginas: int, error: ?Throwable}  $pagina
     */
    private function motivoParcial(string $coleccion, array $pagina): string
    {
        $detalle = $pagina['error']?->getMessage() ?? 'paginación incompleta';

        return "{$coleccion} (páginas vistas: {$pagina['paginas']}): {$detalle}";
    }

    /**
     * @param  list<int>  $idsVistos
     */
    private function pruneProductos(array $idsVistos, bool $paginacionCompleta): int
    {
        if (! $this->debeDepurarColeccion($idsVistos, $paginacionCompleta)) {
            return 0;
        }

        $ids = TiendanubeProducto::query()->whereNotIn('id', $idsVistos)->pluck('id');
        if ($ids->isEmpty()) {
            return 0;
        }

        TiendanubeProducto::whereIn('id', $ids)->delete();

        return $ids->count();
    }

    /**
     * @param  list<int>  $idsVistos
     */
    private function pruneCategorias(array $idsVistos, bool $paginacionCompleta): int
    {
        if (! $this->debeDepurarColeccion($idsVistos, $paginacionCompleta)) {
            return 0;
        }

        $ids = TiendanubeCategoria::query()->whereNotIn('id', $idsVistos)->pluck('id');
        if ($ids->isEmpty()) {
            return 0;
        }

        TiendanubeCategoria::whereIn('id', $ids)->delete();

        return $ids->count();
    }

    /**
     * @param  list<int>  $idsVistos
     */
    private function debeDepurarColeccion(array $idsVistos, bool $paginacionCompleta): bool
    {
        if (! $paginacionCompleta || ! (bool) config('tiendanube.sync_prune_enabled', false)) {
            return false;
        }

        $idsVistos = array_values(array_unique(array_filter($idsVistos)));

        return $idsVistos !== [];
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
                    'published' => $this->resolvePublished($producto),
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

            if (array_key_exists('images', $producto) && is_array($producto['images'])) {
                $this->syncImagenes($id, $producto['images']);
            }
            if (array_key_exists('variants', $producto) && is_array($producto['variants'])) {
                $this->syncVariantes($id, $producto['variants']);
            }
            if (array_key_exists('categories', $producto) && is_array($producto['categories'])) {
                $this->syncCategoriasProducto($id, $producto['categories']);
            }

            return $model;
        });
    }

    /**
     * @param  array<string, mixed>  $producto
     */
    public function resolvePublished(array $producto): bool
    {
        if (array_key_exists('visibility', $producto) && is_string($producto['visibility'])) {
            $visibility = strtolower($producto['visibility']);
            if ($visibility === 'hidden') {
                return false;
            }
            if (in_array($visibility, ['visible', 'unlisted'], true)) {
                return true;
            }
        }

        return (bool) ($producto['published'] ?? false);
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

            if (array_key_exists('inventory_levels', $v) && is_array($v['inventory_levels'])) {
                $this->syncNivelesInventario($vid, $v['inventory_levels']);
            }
        }

        $query = TiendanubeProductoVariante::where('producto_id', $productoId);
        if ($ids !== []) {
            $query->whereNotIn('id', $ids);
        }
        $query->delete();
    }

    /**
     * @param  list<array<string, mixed>>  $levels
     */
    private function syncNivelesInventario(int $varianteId, array $levels): void
    {
        $ids = [];
        $now = now();
        foreach ($levels as $level) {
            if (! is_array($level)) {
                continue;
            }
            $locationId = isset($level['location_id']) ? trim((string) $level['location_id']) : '';
            if ($locationId === '') {
                continue;
            }
            if (! TiendanubeUbicacion::query()->whereKey($locationId)->exists()) {
                Log::warning('Tiendanube: inventory_level con ubicación desconocida', [
                    'variante_id' => $varianteId,
                    'location_id' => $locationId,
                ]);

                continue;
            }
            $ids[] = $locationId;
            TiendanubeVarianteNivel::updateOrCreate(
                ['variante_id' => $varianteId, 'ubicacion_id' => $locationId],
                [
                    'stock' => $this->normalizeStock($level['stock'] ?? null),
                    'synced_at' => $now,
                ]
            );
        }

        $query = TiendanubeVarianteNivel::where('variante_id', $varianteId);
        if ($ids !== []) {
            $query->whereNotIn('ubicacion_id', $ids);
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
     * Stock clásico: "" = ilimitado → null. No convertir ausencia a 0 (MIG-03).
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
