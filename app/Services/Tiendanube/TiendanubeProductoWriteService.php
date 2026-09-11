<?php

namespace App\Services\Tiendanube;

use App\Exceptions\Tiendanube\TiendanubeApiException;
use App\Exceptions\Tiendanube\TiendanubeStockEscrituraBloqueadaException;
use App\Models\Tiendanube\TiendanubeConfiguracion;
use App\Models\Tiendanube\TiendanubeProducto;
use App\Models\Tiendanube\TiendanubeProductoImagen;
use App\Models\Tiendanube\TiendanubeProductoVariante;
use Illuminate\Http\UploadedFile;
use RuntimeException;
use Throwable;

class TiendanubeProductoWriteService
{
    public function __construct(
        private TiendanubeApiClient $api,
        private TiendanubeCatalogoSyncService $sync,
        private OptimizarImagenTiendanubeService $optimizarImagen
    ) {}

    /**
     * @param  array<string, mixed>  $datos
     */
    public function crear(array $datos): TiendanubeProducto
    {
        $this->rechazarStockPlanoSiBloqueado($datos);

        $payload = $this->buildProductPayload($datos, forCreate: true);

        $variant = [
            'sku' => $datos['sku'] ?? null,
            'price' => isset($datos['price']) && $datos['price'] !== '' && $datos['price'] !== null ? (string) $datos['price'] : null,
            'promotional_price' => isset($datos['promotional_price']) && $datos['promotional_price'] !== '' && $datos['promotional_price'] !== null
                ? (string) $datos['promotional_price']
                : null,
            'cost' => isset($datos['cost']) && $datos['cost'] !== '' && $datos['cost'] !== null ? (string) $datos['cost'] : null,
            'values' => [],
        ];

        if (array_key_exists('stock', $datos) && ! $this->usaInventarioPorUbicacion()) {
            $variant['stock'] = $datos['stock'] === null || $datos['stock'] === ''
                ? ''
                : (int) $datos['stock'];
        }

        $payload['variants'] = [array_filter(
            $variant,
            fn ($v) => $v !== null && $v !== []
        )];

        if (! empty($datos['image_urls']) && is_array($datos['image_urls'])) {
            $images = [];
            foreach (array_slice($datos['image_urls'], 0, 9) as $url) {
                $url = is_string($url) ? trim($url) : '';
                if ($url !== '') {
                    $images[] = ['src' => $url];
                }
            }
            if ($images !== []) {
                $payload['images'] = $images;
            }
        }

        try {
            $remote = $this->api->createProduct($payload);
        } catch (TiendanubeApiException $e) {
            if (! $this->esPostIncierto($e)) {
                throw $e;
            }
            $remote = $this->reconciliarCreacionProducto($datos);
            if ($remote === null) {
                throw new RuntimeException(
                    'Creación incierta (timeout o respuesta ambigua). Reconciliar antes de reintentar. No repetir POST.',
                    0,
                    $e
                );
            }
        }

        $producto = $this->sync->upsertProducto($remote);

        try {
            $this->aplicarInventarioSiCorresponde((int) $producto->id, $datos);
        } catch (Throwable $e) {
            $this->releerProducto((int) $producto->id);
            throw $e;
        }

        return $this->releerProducto((int) $producto->id) ?? $producto;
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function actualizar(int $tnProductId, array $datos): TiendanubeProducto
    {
        $this->rechazarStockPlanoSiBloqueado($datos);

        $fallo = null;

        $productoPayload = $this->buildProductPayload($datos, forCreate: false);
        if ($productoPayload !== []) {
            try {
                $this->api->updateProduct($tnProductId, $productoPayload);
            } catch (Throwable $e) {
                $fallo = $e;
            }
        }

        if ($fallo === null) {
            try {
                $this->actualizarPreciosVariante($tnProductId, $datos);
            } catch (Throwable $e) {
                $fallo = $e;
            }
        }

        if ($fallo === null) {
            try {
                $this->aplicarInventarioSiCorresponde($tnProductId, $datos);
            } catch (Throwable $e) {
                $fallo = $e;
            }
        }

        $producto = $this->releerProducto($tnProductId);

        if ($fallo !== null) {
            throw $fallo;
        }

        if ($producto === null) {
            throw new RuntimeException("No se pudo releer el producto {$tnProductId} tras la escritura.");
        }

        return $producto;
    }

    public function eliminarTodasLasImagenes(int $tnProductId): void
    {
        $imagenes = TiendanubeProductoImagen::where('producto_id', $tnProductId)->get();

        foreach ($imagenes as $imagen) {
            try {
                $this->api->deleteProductImage($tnProductId, (int) $imagen->id);
            } catch (Throwable) {
                // Continuar: la imagen puede ya no existir en TN
            }
            $imagen->delete();
        }
    }

    /**
     * @param  array{convertir_webp?: bool, modo_1280?: string}  $optImagen
     */
    public function agregarImagen(
        int $tnProductId,
        ?string $srcUrl = null,
        ?UploadedFile $file = null,
        ?int $position = null,
        bool $reemplazar = false,
        array $optImagen = []
    ): TiendanubeProductoImagen {
        $idsAnteriores = $reemplazar
            ? TiendanubeProductoImagen::where('producto_id', $tnProductId)->pluck('id')->map(fn ($id) => (int) $id)->all()
            : [];

        $payload = [];
        $meta = [
            'width' => null,
            'height' => null,
            'requiere_revision' => false,
            'alerta_pequena' => false,
            'alerta_no_cuadrada' => false,
        ];
        $cleanupPath = null;

        if ($srcUrl) {
            $payload['src'] = $srcUrl;
        } elseif ($file) {
            $opt = $this->optimizarImagen->ejecutar($file, $optImagen);
            $bin = (string) file_get_contents($opt['path']);
            $payload['attachment'] = base64_encode($bin);
            $payload['filename'] = $opt['filename'];
            $meta = [
                'width' => $opt['output_width'] ?? $opt['width'],
                'height' => $opt['output_height'] ?? $opt['height'],
                'requiere_revision' => $opt['requiere_revision'],
                'alerta_pequena' => $opt['alerta_pequena'],
                'alerta_no_cuadrada' => $opt['alerta_no_cuadrada'],
            ];
            if ($opt['cleanup']) {
                $cleanupPath = $opt['path'];
            }
        } else {
            throw new RuntimeException('Indica una URL de imagen o un archivo.');
        }

        if ($position !== null) {
            $payload['position'] = $position;
        }

        try {
            try {
                $remote = $this->api->createProductImage($tnProductId, $payload);
            } catch (TiendanubeApiException $e) {
                if (! $this->esPostIncierto($e)) {
                    throw $e;
                }
                $remote = $this->reconciliarImagenCreada($tnProductId, $payload, $idsAnteriores, $position);
                if ($remote === null) {
                    throw new RuntimeException(
                        'Carga de imagen incierta. Reconciliar antes de reintentar. No se borraron fotos anteriores.',
                        0,
                        $e
                    );
                }
            }
        } finally {
            if ($cleanupPath && is_file($cleanupPath)) {
                @unlink($cleanupPath);
            }
        }

        $imgId = (int) ($remote['id'] ?? 0);
        if ($imgId <= 0) {
            throw new RuntimeException('Tiendanube no devolvió id de imagen.');
        }

        $srcCreate = $this->sync->truncateSeo($this->sync->localizedToString($remote['src'] ?? $srcUrl), 2048);
        $src = $this->resolverSrcImagenPermanente($tnProductId, $imgId, $srcCreate);

        $saved = TiendanubeProductoImagen::updateOrCreate(
            ['id' => $imgId],
            [
                'producto_id' => $tnProductId,
                'src' => $src,
                'position' => (int) ($remote['position'] ?? $position ?? 1),
                'alt' => $this->sync->truncateSeo($this->sync->localizedToString($remote['alt'] ?? null), 512),
                'width' => $meta['width'],
                'height' => $meta['height'],
                'requiere_revision' => $meta['requiere_revision'],
                'alerta_pequena' => $meta['alerta_pequena'],
                'alerta_no_cuadrada' => $meta['alerta_no_cuadrada'],
            ]
        );

        if ($reemplazar && $idsAnteriores !== []) {
            $this->borrarImagenesAnteriores($tnProductId, $idsAnteriores, $imgId);
        }

        return $saved;
    }

    /**
     * createProductImage suele devolver CDN /tmp/ (403). El GET inmediato a veces
     * sigue en /tmp/; si no hay URL pública aún, derivamos la forma canónica del CDN.
     *
     * ponytail: heurística -1024-1024 asume el tamaño que TN publica hoy; si cambia el
     * sufijo, refrescarSrcsTemporalesDeProductos / sync de catálogo lo corrige.
     */
    private function resolverSrcImagenPermanente(int $tnProductId, int $imgId, ?string $srcCreate): ?string
    {
        if (! is_string($srcCreate) || $srcCreate === '' || ! str_contains($srcCreate, '/tmp/')) {
            return $srcCreate;
        }

        $fromApi = $this->srcDesdeProducto($tnProductId, $imgId);
        if (is_string($fromApi) && $fromApi !== '' && ! str_contains($fromApi, '/tmp/')) {
            return $fromApi;
        }

        return $this->heuristicaSrcCdnPublico($srcCreate) ?? $srcCreate;
    }

    private function srcDesdeProducto(int $tnProductId, int $imgId): ?string
    {
        try {
            $product = $this->api->getProduct($tnProductId);
        } catch (Throwable) {
            return null;
        }

        foreach ($product['images'] ?? [] as $img) {
            if (! is_array($img) || (int) ($img['id'] ?? 0) !== $imgId) {
                continue;
            }

            return $this->sync->truncateSeo($this->sync->localizedToString($img['src'] ?? null), 2048);
        }

        return null;
    }

    private function heuristicaSrcCdnPublico(string $tmpSrc): ?string
    {
        if (! str_contains($tmpSrc, '/tmp/stores/')) {
            return null;
        }

        $base = str_replace('/tmp/stores/', '/stores/', $tmpSrc);
        $withSize = preg_replace('/(\.[A-Za-z0-9]+)$/', '-1024-1024$1', $base);

        return is_string($withSize) && $withSize !== '' ? $withSize : $base;
    }

    /**
     * @param  list<int|string>  $productoIds
     */
    public function refrescarSrcsTemporalesDeProductos(array $productoIds): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $productoIds))));
        $updated = 0;

        foreach ($ids as $productoId) {
            $tmpImgs = TiendanubeProductoImagen::query()
                ->where('producto_id', $productoId)
                ->where('src', 'like', '%/tmp/%')
                ->get();
            if ($tmpImgs->isEmpty()) {
                continue;
            }

            try {
                $product = $this->api->getProduct($productoId);
            } catch (Throwable) {
                continue;
            }

            $map = [];
            foreach ($product['images'] ?? [] as $img) {
                if (! is_array($img) || ! isset($img['id'])) {
                    continue;
                }
                $src = $this->sync->truncateSeo($this->sync->localizedToString($img['src'] ?? null), 2048);
                if (is_string($src) && $src !== '' && ! str_contains($src, '/tmp/')) {
                    $map[(int) $img['id']] = $src;
                }
            }

            foreach ($tmpImgs as $local) {
                $nuevo = $map[(int) $local->id] ?? $this->heuristicaSrcCdnPublico((string) $local->src);
                if (! $nuevo || str_contains($nuevo, '/tmp/')) {
                    continue;
                }
                $local->update(['src' => $nuevo]);
                $updated++;
            }
        }

        return $updated;
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function buildProductPayload(array $datos, bool $forCreate): array
    {
        $payload = [];

        if ($forCreate || array_key_exists('name', $datos)) {
            $name = $datos['name'] ?? null;
            if (is_string($name) && trim($name) !== '') {
                $payload['name'] = $this->toLocalized($name);
            } elseif (is_array($name)) {
                $payload['name'] = $name;
            } elseif ($forCreate) {
                throw new RuntimeException('El nombre del producto es obligatorio.');
            }
        }

        if (array_key_exists('description', $datos)) {
            $desc = $datos['description'];
            $payload['description'] = is_array($desc) ? $desc : $this->toLocalized((string) ($desc ?? ''));
        }

        foreach (['brand', 'tags', 'video_url', 'seo_title', 'seo_description'] as $field) {
            if (array_key_exists($field, $datos)) {
                $payload[$field] = $datos[$field];
            }
        }

        if (array_key_exists('published', $datos)) {
            if ($this->api->configuredVersion() === '2025-03') {
                $payload['visibility'] = (bool) $datos['published'] ? 'visible' : 'hidden';
            } else {
                $payload['published'] = (bool) $datos['published'];
            }
        }
        if (array_key_exists('free_shipping', $datos)) {
            $payload['free_shipping'] = (bool) $datos['free_shipping'];
        }
        if (array_key_exists('requires_shipping', $datos)) {
            $payload['requires_shipping'] = (bool) $datos['requires_shipping'];
        }

        if (array_key_exists('categories', $datos) && is_array($datos['categories'])) {
            $ids = array_values(array_filter(array_map('intval', $datos['categories']), fn ($id) => $id > 0));
            if ($forCreate) {
                if ($ids !== []) {
                    $payload['categories'] = $ids;
                }
            } elseif ($ids !== [] || ! empty($datos['replace_categories'])) {
                $payload['categories'] = $ids;
            }
        }

        if (isset($payload['seo_title']) && is_string($payload['seo_title'])) {
            $payload['seo_title'] = $this->sync->truncateSeo($payload['seo_title'], 70);
        }
        if (isset($payload['seo_description']) && is_string($payload['seo_description'])) {
            $payload['seo_description'] = $this->sync->truncateSeo($payload['seo_description'], 320);
        }

        return $payload;
    }

    /**
     * @return array{es: string}
     */
    private function toLocalized(string $value): array
    {
        return ['es' => $value];
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function actualizarPreciosVariante(int $tnProductId, array $datos): void
    {
        $variantPayload = [];

        if (array_key_exists('sku', $datos)) {
            $variantPayload['sku'] = $datos['sku'];
        }
        if (array_key_exists('price', $datos) && $datos['price'] !== null && $datos['price'] !== '') {
            $variantPayload['price'] = (string) $datos['price'];
        }
        if (array_key_exists('promotional_price', $datos)) {
            $variantPayload['promotional_price'] = $datos['promotional_price'] === null || $datos['promotional_price'] === ''
                ? null
                : (string) $datos['promotional_price'];
        }
        if (array_key_exists('cost', $datos) && $datos['cost'] !== null && $datos['cost'] !== '') {
            $variantPayload['cost'] = (string) $datos['cost'];
        }
        if (array_key_exists('stock', $datos) && ! $this->usaInventarioPorUbicacion()) {
            $variantPayload['stock'] = $datos['stock'] === null || $datos['stock'] === ''
                ? ''
                : (int) $datos['stock'];
        }

        if ($variantPayload === []) {
            return;
        }

        $this->api->updateVariant($tnProductId, $this->resolverVariantId($tnProductId), $variantPayload);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function aplicarInventarioSiCorresponde(int $tnProductId, array $datos): void
    {
        if (! $this->usaInventarioPorUbicacion()) {
            return;
        }

        $config = TiendanubeConfiguracion::obtener();
        $variantId = $this->resolverVariantId($tnProductId);

        if (array_key_exists('stock', $datos)) {
            $locationId = isset($datos['location_id']) ? trim((string) $datos['location_id']) : '';
            if ($locationId === '') {
                throw TiendanubeStockEscrituraBloqueadaException::mig03();
            }

            $this->aplicarStock(TiendanubeStockPayload::fromArray([
                'store_id' => (int) $config->store_id,
                'product_id' => $tnProductId,
                'variant_id' => $variantId,
                'location_id' => $locationId,
                'stock' => $datos['stock'],
                'mode' => TiendanubeStockPayload::MODE_SET_LEVEL,
            ]));
        }

        if (array_key_exists('stock_management', $datos)) {
            $this->aplicarStock(TiendanubeStockPayload::fromArray([
                'store_id' => (int) $config->store_id,
                'product_id' => $tnProductId,
                'variant_id' => $variantId,
                'stock_management' => (bool) $datos['stock_management'],
                'mode' => TiendanubeStockPayload::MODE_SET_STOCK_MANAGEMENT,
            ]));
        }
    }

    public function aplicarStock(TiendanubeStockPayload $payload): void
    {
        $payload->validate();
        $this->api->updateVariant($payload->productId, $payload->variantId, $payload->toVariantApiPayload());
    }

    private function resolverVariantId(int $tnProductId): int
    {
        $local = TiendanubeProductoVariante::where('producto_id', $tnProductId)->orderBy('id')->first();
        if ($local) {
            return (int) $local->id;
        }

        $remote = $this->api->getProduct($tnProductId);
        $variants = $remote['variants'] ?? [];
        if (! is_array($variants) || $variants === [] || ! isset($variants[0]['id'])) {
            throw new RuntimeException("El producto {$tnProductId} no tiene variante virtual en Tiendanube.");
        }

        return (int) $variants[0]['id'];
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function rechazarStockPlanoSiBloqueado(array $datos): void
    {
        if (! array_key_exists('stock', $datos)) {
            return;
        }

        if (! $this->usaInventarioPorUbicacion()) {
            return;
        }

        $locationId = isset($datos['location_id']) ? trim((string) $datos['location_id']) : '';
        if ($locationId === '') {
            throw TiendanubeStockEscrituraBloqueadaException::mig03();
        }
    }

    private function usaInventarioPorUbicacion(): bool
    {
        $config = TiendanubeConfiguracion::obtener();

        return (bool) $config->multi_inventario_activo || ($config->locations_probe ?? null) === 'ok';
    }

    private function releerProducto(int $tnProductId): ?TiendanubeProducto
    {
        try {
            return $this->sync->upsertProducto($this->api->getProduct($tnProductId));
        } catch (Throwable) {
            return TiendanubeProducto::query()->find($tnProductId);
        }
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>|null
     */
    private function reconciliarCreacionProducto(array $datos): ?array
    {
        $sku = isset($datos['sku']) ? trim((string) $datos['sku']) : '';
        if ($sku === '') {
            return null;
        }

        $local = TiendanubeProductoVariante::query()->where('sku', $sku)->orderByDesc('id')->first();
        if ($local) {
            try {
                return $this->api->getProduct((int) $local->producto_id);
            } catch (Throwable) {
                return null;
            }
        }

        try {
            $lista = $this->api->findProductsBySku($sku);
        } catch (Throwable) {
            return null;
        }

        foreach ($lista as $producto) {
            if (! is_array($producto)) {
                continue;
            }
            foreach ($producto['variants'] ?? [] as $v) {
                if (is_array($v) && trim((string) ($v['sku'] ?? '')) === $sku) {
                    return $producto;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<int>  $idsAnteriores
     * @return array<string, mixed>|null
     */
    private function reconciliarImagenCreada(int $tnProductId, array $payload, array $idsAnteriores, ?int $position): ?array
    {
        try {
            $product = $this->api->getProduct($tnProductId);
        } catch (Throwable) {
            return null;
        }

        $srcPedido = isset($payload['src']) ? (string) $payload['src'] : '';
        $candidatas = [];
        foreach ($product['images'] ?? [] as $img) {
            if (! is_array($img) || ! isset($img['id'])) {
                continue;
            }
            if (in_array((int) $img['id'], $idsAnteriores, true)) {
                continue;
            }
            $candidatas[] = $img;
        }

        if ($srcPedido !== '') {
            foreach ($candidatas as $img) {
                $src = $this->sync->localizedToString($img['src'] ?? null) ?? '';
                if ($src === $srcPedido || str_contains($src, $srcPedido) || str_contains($srcPedido, $src)) {
                    return $img;
                }
            }
        }

        if ($position !== null) {
            foreach ($candidatas as $img) {
                if ((int) ($img['position'] ?? 0) === $position) {
                    return $img;
                }
            }
        }

        return count($candidatas) === 1 ? $candidatas[0] : null;
    }

    /**
     * @param  list<int>  $idsAnteriores
     */
    private function borrarImagenesAnteriores(int $tnProductId, array $idsAnteriores, int $nuevoId): void
    {
        foreach ($idsAnteriores as $oldId) {
            if ($oldId === $nuevoId) {
                continue;
            }
            try {
                $this->api->deleteProductImage($tnProductId, $oldId);
            } catch (Throwable) {
                // Continuar: la imagen puede ya no existir en TN
            }
            TiendanubeProductoImagen::query()->where('id', $oldId)->delete();
        }
    }

    private function esPostIncierto(TiendanubeApiException $e): bool
    {
        if ($e->statusCode === 0 || $e->summary === 'connection_failed') {
            return true;
        }

        return in_array($e->statusCode, [502, 503, 504], true);
    }
}
