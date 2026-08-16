<?php

namespace App\Services\Tiendanube;

use App\Models\Tiendanube\TiendanubeProducto;
use App\Models\Tiendanube\TiendanubeProductoImagen;
use App\Models\Tiendanube\TiendanubeProductoVariante;
use Illuminate\Http\UploadedFile;
use RuntimeException;

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
        $payload = $this->buildProductPayload($datos, forCreate: true);

        $variant = [
            'sku' => $datos['sku'] ?? null,
            'price' => isset($datos['price']) ? (string) $datos['price'] : null,
            'promotional_price' => isset($datos['promotional_price']) ? (string) $datos['promotional_price'] : null,
            'cost' => isset($datos['cost']) ? (string) $datos['cost'] : null,
            'values' => [],
        ];

        if (array_key_exists('stock', $datos)) {
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

        $remote = $this->api->createProduct($payload);

        return $this->sync->upsertProducto($remote);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function actualizar(int $tnProductId, array $datos): TiendanubeProducto
    {
        $productoPayload = $this->buildProductPayload($datos, forCreate: false);

        if ($productoPayload !== []) {
            $this->api->updateProduct($tnProductId, $productoPayload);
        }

        $variantFields = array_intersect_key($datos, array_flip(['sku', 'price', 'promotional_price', 'cost', 'stock']));
        if ($variantFields !== []) {
            $variantId = $this->resolverVariantId($tnProductId);
            $variantPayload = [];

            if (array_key_exists('sku', $variantFields)) {
                $variantPayload['sku'] = $variantFields['sku'];
            }
            if (array_key_exists('price', $variantFields) && $variantFields['price'] !== null && $variantFields['price'] !== '') {
                $variantPayload['price'] = (string) $variantFields['price'];
            }
            if (array_key_exists('promotional_price', $variantFields)) {
                $variantPayload['promotional_price'] = $variantFields['promotional_price'] === null || $variantFields['promotional_price'] === ''
                    ? null
                    : (string) $variantFields['promotional_price'];
            }
            if (array_key_exists('cost', $variantFields) && $variantFields['cost'] !== null && $variantFields['cost'] !== '') {
                $variantPayload['cost'] = (string) $variantFields['cost'];
            }
            if (array_key_exists('stock', $variantFields)) {
                $variantPayload['stock'] = $variantFields['stock'] === null || $variantFields['stock'] === ''
                    ? ''
                    : (int) $variantFields['stock'];
            }

            if ($variantPayload !== []) {
                $this->api->updateVariant($tnProductId, $variantId, $variantPayload);
            }
        }

        $remote = $this->api->getProduct($tnProductId);

        return $this->sync->upsertProducto($remote);
    }

    public function eliminarTodasLasImagenes(int $tnProductId): void
    {
        $imagenes = TiendanubeProductoImagen::where('producto_id', $tnProductId)->get();

        foreach ($imagenes as $imagen) {
            try {
                $this->api->deleteProductImage($tnProductId, (int) $imagen->id);
            } catch (\Throwable) {
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
        if ($reemplazar) {
            $this->eliminarTodasLasImagenes($tnProductId);
        }

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
            $payload['attachment'] = base64_encode((string) file_get_contents($opt['path']));
            $payload['filename'] = $opt['filename'];
            // Dimensiones = archivo subido (post-resize). Alertas siguen midiendo el original.
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
            $remote = $this->api->createProductImage($tnProductId, $payload);
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
        } catch (\Throwable) {
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
            } catch (\Throwable) {
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
            $payload['published'] = (bool) $datos['published'];
        }
        if (array_key_exists('free_shipping', $datos)) {
            $payload['free_shipping'] = (bool) $datos['free_shipping'];
        }
        if (array_key_exists('requires_shipping', $datos)) {
            $payload['requires_shipping'] = (bool) $datos['requires_shipping'];
        }

        // Solo enviar categories si viene la clave y no está vacía por accidente en update
        // (categories: [] borra todas en TN). En create vacío se omite.
        if (array_key_exists('categories', $datos) && is_array($datos['categories'])) {
            $ids = array_values(array_filter(array_map('intval', $datos['categories']), fn ($id) => $id > 0));
            if ($forCreate) {
                if ($ids !== []) {
                    $payload['categories'] = $ids;
                }
            } else {
                // En update: reenviar IDs seleccionados; vacío solo si replace_categories
                if ($ids !== [] || ! empty($datos['replace_categories'])) {
                    $payload['categories'] = $ids;
                }
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
}
