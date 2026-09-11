<?php

namespace App\Services\Tiendanube;

use App\Exceptions\Tiendanube\TiendanubeActualizacionParcialException;
use App\Exceptions\Tiendanube\TiendanubeApiException;
use App\Exceptions\Tiendanube\TiendanubeApiNotFoundException;
use App\Exceptions\Tiendanube\TiendanubeStockEscrituraBloqueadaException;
use App\Models\Tiendanube\TiendanubeConfiguracion;
use App\Models\Tiendanube\TiendanubeProducto;
use App\Models\Tiendanube\TiendanubeProductoImagen;
use App\Models\Tiendanube\TiendanubeProductoVariante;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\UploadedFile;
use RuntimeException;
use Throwable;

class TiendanubeProductoWriteService
{
    public function __construct(
        private TiendanubeApiClient $api,
        private TiendanubeCatalogoSyncService $sync,
        private TiendanubeProductoImagenOperacionService $imagenOperaciones,
        private TiendanubeOperacionTiendaService $operaciones
    ) {}

    /**
     * @param  array<string, mixed>  $datos
     */
    public function crear(array $datos): TiendanubeProducto
    {
        $this->assertEscrituraAdmisible();
        $this->rechazarStockPlanoSiBloqueado($datos);
        $payload = $this->buildProductPayload($datos, forCreate: true);

        $variant = [
            'sku' => $datos['sku'] ?? null,
            'price' => isset($datos['price']) ? (string) $datos['price'] : null,
            'promotional_price' => isset($datos['promotional_price']) ? (string) $datos['promotional_price'] : null,
            'cost' => isset($datos['cost']) ? (string) $datos['cost'] : null,
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
            $this->refrescarProductoSiEsPosible((int) $producto->id);
            throw $e;
        }

        return $this->refrescarProductoSiEsPosible((int) $producto->id) ?? $producto;
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function actualizar(int $tnProductId, array $datos): TiendanubeProducto
    {
        $this->assertEscrituraAdmisible();
        $this->rechazarStockPlanoSiBloqueado($datos);
        $productoPayload = $this->buildProductPayload($datos, forCreate: false);
        $variantPayload = $this->buildVariantPayload($datos);
        $variantId = null;
        if ($variantPayload !== []) {
            $variantId = $this->resolverVariantIdParaActualizacion(
                $tnProductId,
                $this->variantIdDesdeDatos($datos)
            );
        }

        $productoActualizado = false;
        try {
            if ($productoPayload !== []) {
                $this->api->updateProduct($tnProductId, $productoPayload);
                $productoActualizado = true;
            }
            if ($variantPayload !== [] && $variantId !== null) {
                $this->api->updateVariant($tnProductId, $variantId, $variantPayload);
            }
        } catch (Throwable $e) {
            $espejo = $this->refrescarProductoSiEsPosible($tnProductId);
            $mensaje = $this->mensajeFalloVariante($e, $variantId, $productoActualizado);

            if ($productoActualizado && $variantPayload !== []) {
                throw new TiendanubeActualizacionParcialException($mensaje, $espejo, $e);
            }

            throw $espejo && $this->esHttp404($e)
                ? new RuntimeException($mensaje, 0, $e)
                : $e;
        }

        try {
            $this->aplicarInventarioSiCorresponde($tnProductId, $datos);
        } catch (Throwable $e) {
            $this->refrescarProductoSiEsPosible($tnProductId);
            throw $e;
        }

        $remote = $this->api->getProduct($tnProductId);

        return $this->sync->upsertProducto($remote);
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
        array $optImagen = [],
        ?string $solicitudClave = null,
        ?int $userId = null,
    ): TiendanubeProductoImagenCarga {
        $this->assertEscrituraAdmisible();

        return $this->imagenOperaciones->ejecutar(
            $tnProductId,
            $srcUrl,
            $file,
            $position,
            $reemplazar,
            $optImagen,
            $solicitudClave,
            $userId
        );
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

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function buildVariantPayload(array $datos): array
    {
        $tieneCampos = false;
        foreach (['sku', 'price', 'promotional_price', 'cost', 'stock', 'stock_unlimited'] as $campo) {
            if (array_key_exists($campo, $datos)) {
                $tieneCampos = true;
                break;
            }
        }
        if (! $tieneCampos) {
            return [];
        }

        $payload = [];

        if (array_key_exists('sku', $datos)) {
            $payload['sku'] = $datos['sku'];
        }
        if (array_key_exists('price', $datos) && $datos['price'] !== null && $datos['price'] !== '') {
            $payload['price'] = (string) $datos['price'];
        }
        if (array_key_exists('promotional_price', $datos)) {
            $payload['promotional_price'] = $datos['promotional_price'] === null || $datos['promotional_price'] === ''
                ? null
                : (string) $datos['promotional_price'];
        }
        if (array_key_exists('cost', $datos) && $datos['cost'] !== null && $datos['cost'] !== '') {
            $payload['cost'] = (string) $datos['cost'];
        }

        // ponytail: con multi-inventario el stock plano va por location (MIG-03); si no, ilimitado = "".
        if (! $this->usaInventarioPorUbicacion()) {
            if (! empty($datos['stock_unlimited'])) {
                $payload['stock'] = '';
            } elseif (array_key_exists('stock', $datos) && $datos['stock'] !== null && $datos['stock'] !== '') {
                $payload['stock'] = (int) $datos['stock'];
            }
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function variantIdDesdeDatos(array $datos): ?int
    {
        if (! array_key_exists('variant_id', $datos) || $datos['variant_id'] === null || $datos['variant_id'] === '') {
            return null;
        }

        return (int) $datos['variant_id'];
    }

    private function resolverVariantIdParaActualizacion(int $tnProductId, ?int $variantId): int
    {
        if ($variantId !== null && $variantId > 0) {
            $pertenece = TiendanubeProductoVariante::query()
                ->where('producto_id', $tnProductId)
                ->where('id', $variantId)
                ->exists();
            if (! $pertenece) {
                throw new RuntimeException('La variante no pertenece a este producto.');
            }

            return $variantId;
        }

        $locales = TiendanubeProductoVariante::query()
            ->where('producto_id', $tnProductId)
            ->orderBy('id')
            ->get();

        if ($locales->count() === 1) {
            return (int) $locales->first()->id;
        }

        if ($locales->count() > 1) {
            throw new RuntimeException('Selecciona una variante para guardar cambios de SKU, precio, promoción, costo o stock.');
        }

        $remote = $this->api->getProduct($tnProductId);
        $variants = $remote['variants'] ?? [];
        if (! is_array($variants) || $variants === []) {
            throw new RuntimeException("El producto {$tnProductId} no tiene variante virtual en Tiendanube.");
        }

        $ids = [];
        foreach ($variants as $v) {
            if (is_array($v) && isset($v['id'])) {
                $ids[] = (int) $v['id'];
            }
        }

        if (count($ids) === 1) {
            return $ids[0];
        }

        throw new RuntimeException('El espejo local no tiene variantes. Sincroniza el catálogo y selecciona una variante.');
    }

    private function refrescarProductoSiEsPosible(int $tnProductId): ?TiendanubeProducto
    {
        try {
            $remote = $this->api->getProduct($tnProductId);

            return $this->sync->upsertProducto($remote);
        } catch (Throwable) {
            return TiendanubeProducto::query()->find($tnProductId);
        }
    }

    private function esHttp404(Throwable $e): bool
    {
        if ($e instanceof TiendanubeApiNotFoundException) {
            return true;
        }
        if ($e instanceof RequestException && $e->response?->status() === 404) {
            return true;
        }

        return str_contains($e->getMessage(), 'HTTP 404')
            || str_contains($e->getMessage(), 'status code 404');
    }

    private function mensajeFalloVariante(Throwable $e, ?int $variantId, bool $productoActualizado): string
    {
        if ($this->esHttp404($e)) {
            return 'La variante'.($variantId ? " {$variantId}" : '').' ya no existe en Tiendanube. Se actualizó el listado; selecciona otra variante.';
        }

        if ($productoActualizado) {
            return 'El producto se actualizó, pero la variante no: '.$e->getMessage();
        }

        return $e->getMessage();
    }

    private function assertEscrituraAdmisible(): void
    {
        $storeId = TiendanubeConfiguracion::obtener()->store_id;

        $this->operaciones->assertAdmisible(
            TiendanubeOperacionTiendaService::TIPO_PRODUCTO_WRITE,
            $storeId ? (int) $storeId : null
        );
    }

    private function aplicarInventarioSiCorresponde(int $tnProductId, array $datos): void
    {
        if (! $this->usaInventarioPorUbicacion()) {
            return;
        }

        $config = TiendanubeConfiguracion::obtener();
        $variantId = $this->resolverVariantIdParaActualizacion(
            $tnProductId,
            $this->variantIdDesdeDatos($datos)
        );

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

    private function esPostIncierto(TiendanubeApiException $e): bool
    {
        if ($e->statusCode === 0 || $e->summary === 'connection_failed') {
            return true;
        }

        return in_array($e->statusCode, [502, 503, 504], true);
    }
}
