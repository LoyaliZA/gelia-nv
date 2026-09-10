<?php

namespace App\Services\Tiendanube;

use App\Models\Tiendanube\TiendanubeProducto;
use App\Models\Tiendanube\TiendanubeProductoImagen;
use App\Models\Tiendanube\TiendanubeProductoImagenOperacion;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class TiendanubeProductoImagenOperacionService
{
    public const MAX_IMAGENES = 9;

    /**
     * Exclusión limitada a cambios de imágenes por tienda/producto.
     * Nombre de lock para futura integración TN-03: no cubre ediciones hechas en el panel de Tiendanube.
     */
    public const LOCK_PREFIX = 'tiendanube:producto-imagen:';

    public function __construct(
        private TiendanubeApiClient $api,
        private TiendanubeCatalogoSyncService $sync,
        private OptimizarImagenTiendanubeService $optimizarImagen
    ) {}

    /**
     * @param  array{convertir_webp?: bool, modo_1280?: string}  $optImagen
     */
    public function ejecutar(
        int $tnProductId,
        ?string $srcUrl = null,
        ?UploadedFile $file = null,
        ?int $position = null,
        bool $reemplazar = false,
        array $optImagen = [],
        ?string $solicitudClave = null,
        ?int $userId = null,
    ): TiendanubeProductoImagenCarga {
        $this->validarEntrada($tnProductId, $srcUrl, $file);

        $tiendaId = $this->tiendaId();
        $clave = $solicitudClave !== null && trim($solicitudClave) !== ''
            ? trim($solicitudClave)
            : (string) Str::uuid();
        $modo = $reemplazar
            ? TiendanubeProductoImagenOperacion::MODO_REEMPLAZAR_TODAS
            : TiendanubeProductoImagenOperacion::MODO_AGREGAR;

        return $this->conLock($tiendaId, $tnProductId, function () use (
            $tnProductId,
            $srcUrl,
            $file,
            $position,
            $optImagen,
            $tiendaId,
            $clave,
            $modo,
            $userId
        ) {
            $op = $this->obtenerOCrear($tiendaId, $tnProductId, $clave, $modo, $userId, $srcUrl, $position);
            $op->increment('intentos');
            $op->refresh();

            if ($op->estado === TiendanubeProductoImagenOperacion::ESTADO_COMPLETADA && $op->imagen_nueva_id) {
                return $this->cargaDesdeOperacion($op);
            }

            return $this->continuar($op, $srcUrl, $file, $optImagen);
        });
    }

    public function reconciliar(TiendanubeProductoImagenOperacion $op): TiendanubeProductoImagenCarga
    {
        return $this->conLock((string) $op->tienda_id, (int) $op->producto_id, function () use ($op) {
            $op->refresh();
            $op->increment('intentos');
            $op->refresh();

            if ($op->estado === TiendanubeProductoImagenOperacion::ESTADO_COMPLETADA && $op->imagen_nueva_id) {
                return $this->cargaDesdeOperacion($op);
            }

            if (! $op->imagen_nueva_id && $op->estado === TiendanubeProductoImagenOperacion::ESTADO_RESULTADO_INCIERTO) {
                $this->intentarIdentificarImagenNueva($op);
                $op->refresh();
            }

            if (! $op->imagen_nueva_id) {
                throw new RuntimeException(
                    'Esta operación no tiene una imagen nueva confirmada. Revísela en el catálogo remoto; no vuelva a cargar el mismo archivo a ciegas.'
                );
            }

            return $this->continuar($op, $op->src_url, null, []);
        });
    }

    /**
     * @param  array{convertir_webp?: bool, modo_1280?: string}  $optImagen
     */
    private function continuar(
        TiendanubeProductoImagenOperacion $op,
        ?string $srcUrl,
        ?UploadedFile $file,
        array $optImagen
    ): TiendanubeProductoImagenCarga {
        if ($op->ids_originales === null) {
            $this->capturarIdsOriginales($op);
            $op->refresh();
        }

        if (! $op->imagen_nueva_id) {
            $this->assertPuedeAgregar($op);
            $meta = $this->prepararPayload($op, $srcUrl, $file, $optImagen);
            $this->cargarNueva($op, $meta);
            $op->refresh();
        }

        if ($op->modo === TiendanubeProductoImagenOperacion::MODO_REEMPLAZAR_TODAS) {
            $this->retirarOriginales($op);
            $op->refresh();
        } else {
            $op->update([
                'estado' => TiendanubeProductoImagenOperacion::ESTADO_COMPLETADA,
                'error' => null,
            ]);
        }

        try {
            $this->refrescarEspejo($op->fresh());
        } catch (\Throwable) {
            // ponytail: el espejo local ya tiene la imagen nueva; un GET fallido no deshace la carga.
        }
        $this->limpiarArchivoSiTermino($op->fresh());

        if (! $op->fresh()->imagen_nueva_id) {
            throw new RuntimeException($op->fresh()->error ?: 'No se confirmó la imagen nueva.');
        }

        $op = $op->fresh();

        if ($op->estado === TiendanubeProductoImagenOperacion::ESTADO_RESULTADO_INCIERTO) {
            throw new RuntimeException(
                $op->error ?: 'No se pudo confirmar si la imagen nueva quedó en Tiendanube. Revise la operación antes de reintentar.'
            );
        }

        return $this->cargaDesdeOperacion($op);
    }

    private function validarEntrada(int $tnProductId, ?string $srcUrl, ?UploadedFile $file): void
    {
        if (! TiendanubeProducto::query()->whereKey($tnProductId)->exists()) {
            throw new RuntimeException("El producto {$tnProductId} no existe en el catálogo local.");
        }

        $url = is_string($srcUrl) ? trim($srcUrl) : '';
        if ($url === '' && $file === null) {
            throw new RuntimeException('Indica una URL de imagen o un archivo.');
        }
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function conLock(string $tiendaId, int $productoId, callable $callback): mixed
    {
        $lock = Cache::lock(self::LOCK_PREFIX.$tiendaId.':'.$productoId, 120);

        if (! $lock->get()) {
            throw new RuntimeException(
                'Ya hay un cambio de imágenes en curso para este producto. Espere e intente de nuevo.'
            );
        }

        try {
            return $callback();
        } finally {
            $lock->release();
        }
    }

    private function obtenerOCrear(
        string $tiendaId,
        int $productoId,
        string $clave,
        string $modo,
        ?int $userId,
        ?string $srcUrl,
        ?int $position,
    ): TiendanubeProductoImagenOperacion {
        $existente = TiendanubeProductoImagenOperacion::query()
            ->where('tienda_id', $tiendaId)
            ->where('solicitud_clave', $clave)
            ->first();

        if ($existente) {
            return $existente;
        }

        return TiendanubeProductoImagenOperacion::create([
            'tienda_id' => $tiendaId,
            'producto_id' => $productoId,
            'solicitud_clave' => $clave,
            'modo' => $modo,
            'estado' => TiendanubeProductoImagenOperacion::ESTADO_PREPARADA,
            'user_id' => $userId,
            'src_url' => $srcUrl,
            'position' => $position,
            'intentos' => 0,
        ]);
    }

    private function capturarIdsOriginales(TiendanubeProductoImagenOperacion $op): void
    {
        $remote = $this->api->getProduct((int) $op->producto_id);
        $ids = $this->idsImagenesDeProducto($remote);

        $op->update([
            'ids_originales' => $ids,
            'estado' => TiendanubeProductoImagenOperacion::ESTADO_PREPARADA,
        ]);
    }

    private function assertPuedeAgregar(TiendanubeProductoImagenOperacion $op): void
    {
        $originales = array_values(array_map('intval', $op->ids_originales ?? []));
        if ($op->modo === TiendanubeProductoImagenOperacion::MODO_AGREGAR
            && count($originales) >= self::MAX_IMAGENES
        ) {
            $op->update([
                'estado' => TiendanubeProductoImagenOperacion::ESTADO_FALLIDA,
                'error' => 'El producto ya tiene '.self::MAX_IMAGENES.' imágenes. No se cargó ni se eliminó ninguna.',
            ]);
            throw new RuntimeException($op->error);
        }
    }

    /**
     * @param  array{convertir_webp?: bool, modo_1280?: string}  $optImagen
     * @return array{payload: array<string, mixed>, meta: array<string, mixed>}
     */
    private function prepararPayload(
        TiendanubeProductoImagenOperacion $op,
        ?string $srcUrl,
        ?UploadedFile $file,
        array $optImagen
    ): array {
        $meta = [
            'width' => null,
            'height' => null,
            'requiere_revision' => false,
            'alerta_pequena' => false,
            'alerta_no_cuadrada' => false,
        ];

        if ($op->archivo_path && Storage::disk('local')->exists($op->archivo_path)) {
            $abs = Storage::disk('local')->path($op->archivo_path);
            $payload = [
                'attachment' => base64_encode((string) file_get_contents($abs)),
                'filename' => $op->filename ?: basename($op->archivo_path),
            ];
            if ($op->position !== null) {
                $payload['position'] = (int) $op->position;
            }

            return ['payload' => $payload, 'meta' => $meta];
        }

        $url = is_string($srcUrl) && trim($srcUrl) !== '' ? trim($srcUrl) : (string) $op->src_url;
        if ($url !== '' && $file === null && ! $op->archivo_path) {
            $op->update([
                'src_url' => $url,
                'archivo_hash' => hash('sha256', $url),
            ]);
            $payload = ['src' => $url];
            if ($op->position !== null) {
                $payload['position'] = (int) $op->position;
            }

            return ['payload' => $payload, 'meta' => $meta];
        }

        if (! $file) {
            $op->update([
                'estado' => TiendanubeProductoImagenOperacion::ESTADO_FALLIDA,
                'error' => 'No hay archivo ni URL para reanudar esta operación.',
            ]);
            throw new RuntimeException($op->error);
        }

        try {
            $opt = $this->optimizarImagen->ejecutar($file, $optImagen);
        } catch (\Throwable $e) {
            $op->update([
                'estado' => TiendanubeProductoImagenOperacion::ESTADO_FALLIDA,
                'error' => 'Falló la preparación de la imagen: '.$e->getMessage(),
            ]);
            throw new RuntimeException($op->error, 0, $e);
        }

        $path = $opt['path'] ?? null;
        if (! is_string($path) || $path === '' || ! is_file($path)) {
            $op->update([
                'estado' => TiendanubeProductoImagenOperacion::ESTADO_FALLIDA,
                'error' => 'Falló la preparación de la imagen: archivo de salida no disponible.',
            ]);
            throw new RuntimeException($op->error);
        }

        $contents = (string) file_get_contents($path);
        if ($contents === '') {
            if (! empty($opt['cleanup']) && is_file($path)) {
                @unlink($path);
            }
            $op->update([
                'estado' => TiendanubeProductoImagenOperacion::ESTADO_FALLIDA,
                'error' => 'Falló la preparación de la imagen: el archivo quedó vacío.',
            ]);
            throw new RuntimeException($op->error);
        }

        $filename = $opt['filename'] ?: ($file->getClientOriginalName() ?: 'imagen.bin');
        $rel = 'tiendanube/imagen-operaciones/'.$op->id.'/'.$filename;
        Storage::disk('local')->put($rel, $contents);

        if (! empty($opt['cleanup']) && is_file($path) && realpath($path) !== realpath(Storage::disk('local')->path($rel))) {
            @unlink($path);
        }

        $op->update([
            'archivo_path' => $rel,
            'filename' => $filename,
            'archivo_hash' => hash('sha256', $contents),
        ]);

        $payload = [
            'attachment' => base64_encode($contents),
            'filename' => $filename,
        ];
        if ($op->position !== null) {
            $payload['position'] = (int) $op->position;
        }

        $meta = [
            'width' => $opt['output_width'] ?? $opt['width'],
            'height' => $opt['output_height'] ?? $opt['height'],
            'requiere_revision' => (bool) ($opt['requiere_revision'] ?? false),
            'alerta_pequena' => (bool) ($opt['alerta_pequena'] ?? false),
            'alerta_no_cuadrada' => (bool) ($opt['alerta_no_cuadrada'] ?? false),
        ];

        return ['payload' => $payload, 'meta' => $meta];
    }

    /**
     * @param  array{payload: array<string, mixed>, meta: array<string, mixed>}  $preparado
     */
    private function cargarNueva(TiendanubeProductoImagenOperacion $op, array $preparado): void
    {
        $op->update(['estado' => TiendanubeProductoImagenOperacion::ESTADO_CARGANDO, 'error' => null]);

        try {
            $remote = $this->api->createProductImage((int) $op->producto_id, $preparado['payload']);
        } catch (ConnectionException $e) {
            $op->update([
                'estado' => TiendanubeProductoImagenOperacion::ESTADO_RESULTADO_INCIERTO,
                'error' => 'La carga venció o se interrumpió. Consultando el catálogo remoto; no se repetirá el POST automáticamente. '.$e->getMessage(),
            ]);
            $this->intentarIdentificarImagenNueva($op);
            $op->refresh();
            if (! $op->imagen_nueva_id) {
                throw new RuntimeException($op->error);
            }

            return;
        } catch (\Throwable $e) {
            $mensaje = $e->getMessage();
            if ($this->esErrorLimiteImagenes($mensaje)) {
                $mensaje = 'Tiendanube rechazó la carga por límite de imágenes. No se eliminaron las anteriores. '.$mensaje;
            }
            $op->update([
                'estado' => TiendanubeProductoImagenOperacion::ESTADO_FALLIDA,
                'error' => $mensaje,
            ]);
            throw new RuntimeException($mensaje, 0, $e);
        }

        $imgId = (int) ($remote['id'] ?? 0);
        $productoRemotoId = (int) ($remote['product_id'] ?? $op->producto_id);
        if ($imgId <= 0 || $productoRemotoId !== (int) $op->producto_id) {
            $op->update([
                'estado' => TiendanubeProductoImagenOperacion::ESTADO_RESULTADO_INCIERTO,
                'error' => 'Tiendanube no devolvió un id de imagen inequívoco para este producto.',
            ]);
            throw new RuntimeException($op->error);
        }

        $srcCreate = $this->sync->truncateSeo($this->sync->localizedToString($remote['src'] ?? $op->src_url), 2048);
        $src = $this->resolverSrcImagenPermanente((int) $op->producto_id, $imgId, $srcCreate);
        $meta = $preparado['meta'];

        TiendanubeProductoImagen::updateOrCreate(
            ['id' => $imgId],
            [
                'producto_id' => (int) $op->producto_id,
                'src' => $src,
                'position' => (int) ($remote['position'] ?? $op->position ?? 1),
                'alt' => $this->sync->truncateSeo($this->sync->localizedToString($remote['alt'] ?? null), 512),
                'width' => $meta['width'],
                'height' => $meta['height'],
                'requiere_revision' => $meta['requiere_revision'],
                'alerta_pequena' => $meta['alerta_pequena'],
                'alerta_no_cuadrada' => $meta['alerta_no_cuadrada'],
            ]
        );

        $op->update([
            'imagen_nueva_id' => $imgId,
            'estado' => TiendanubeProductoImagenOperacion::ESTADO_CARGADA,
            'error' => null,
        ]);
    }

    private function intentarIdentificarImagenNueva(TiendanubeProductoImagenOperacion $op): void
    {
        try {
            $remote = $this->api->getProduct((int) $op->producto_id);
        } catch (\Throwable) {
            return;
        }

        $actuales = $this->idsImagenesDeProducto($remote);
        $originales = array_values(array_map('intval', $op->ids_originales ?? []));
        $nuevos = array_values(array_diff($actuales, $originales));

        if (count($nuevos) !== 1) {
            return;
        }

        $imgId = $nuevos[0];
        $op->update([
            'imagen_nueva_id' => $imgId,
            'estado' => TiendanubeProductoImagenOperacion::ESTADO_CARGADA,
            'error' => null,
        ]);
    }

    private function retirarOriginales(TiendanubeProductoImagenOperacion $op): void
    {
        $nuevaId = (int) $op->imagen_nueva_id;
        $originales = array_values(array_filter(
            array_map('intval', $op->ids_originales ?? []),
            fn (int $id) => $id > 0 && $id !== $nuevaId
        ));

        $op->update(['estado' => TiendanubeProductoImagenOperacion::ESTADO_RETIRANDO_ANTERIORES]);

        $eliminaciones = is_array($op->eliminaciones) ? $op->eliminaciones : [];
        $huboError = false;

        foreach ($originales as $imageId) {
            $prev = $eliminaciones[(string) $imageId]['resultado'] ?? null;
            if (in_array($prev, ['ok', 'no_encontrado'], true)) {
                continue;
            }

            try {
                $resultado = $this->api->deleteProductImageResult((int) $op->producto_id, $imageId);
                $eliminaciones[(string) $imageId] = ['resultado' => $resultado];
                TiendanubeProductoImagen::query()
                    ->where('producto_id', (int) $op->producto_id)
                    ->whereKey($imageId)
                    ->delete();
            } catch (\Throwable $e) {
                $eliminaciones[(string) $imageId] = [
                    'resultado' => 'error',
                    'mensaje' => $e->getMessage(),
                ];
                $huboError = true;
            }
        }

        $pendientes = false;
        foreach ($originales as $imageId) {
            $res = $eliminaciones[(string) $imageId]['resultado'] ?? 'error';
            if (! in_array($res, ['ok', 'no_encontrado'], true)) {
                $pendientes = true;
            }
        }

        $op->update([
            'eliminaciones' => $eliminaciones,
            'estado' => $pendientes || $huboError
                ? TiendanubeProductoImagenOperacion::ESTADO_PENDIENTE_RECONCILIACION
                : TiendanubeProductoImagenOperacion::ESTADO_COMPLETADA,
            'error' => $pendientes
                ? 'No se pudieron retirar todas las imágenes anteriores.'
                : null,
        ]);
    }

    private function refrescarEspejo(TiendanubeProductoImagenOperacion $op): void
    {
        $remote = $this->api->getProduct((int) $op->producto_id);
        if ((int) ($remote['id'] ?? 0) !== (int) $op->producto_id) {
            return;
        }
        $ids = $this->idsImagenesDeProducto($remote);
        if ($op->imagen_nueva_id && ! in_array((int) $op->imagen_nueva_id, $ids, true)) {
            return;
        }
        foreach ($remote['images'] ?? [] as $img) {
            if (! is_array($img) || (int) ($img['id'] ?? 0) !== (int) $op->imagen_nueva_id) {
                continue;
            }
            $src = $this->sync->localizedToString($img['src'] ?? null);
            if (is_string($src) && str_contains($src, '/tmp/')) {
                return;
            }
        }
        $this->sync->upsertProducto($remote);
    }

    private function limpiarArchivoSiTermino(?TiendanubeProductoImagenOperacion $op): void
    {
        if (! $op || $op->estado !== TiendanubeProductoImagenOperacion::ESTADO_COMPLETADA) {
            return;
        }
        if ($op->archivo_path && Storage::disk('local')->exists($op->archivo_path)) {
            Storage::disk('local')->delete($op->archivo_path);
        }
        $op->update(['archivo_path' => null]);
    }

    private function cargaDesdeOperacion(TiendanubeProductoImagenOperacion $op): TiendanubeProductoImagenCarga
    {
        $imagen = TiendanubeProductoImagen::query()->find((int) $op->imagen_nueva_id);
        if (! $imagen) {
            throw new RuntimeException('La imagen nueva no está en el espejo local. Reintente la reconciliación.');
        }

        return new TiendanubeProductoImagenCarga($imagen, $op);
    }

    /**
     * @return list<int>
     */
    private function idsImagenesDeProducto(array $producto): array
    {
        $ids = [];
        foreach ($producto['images'] ?? [] as $img) {
            if (! is_array($img) || ! isset($img['id'])) {
                continue;
            }
            $id = (int) $img['id'];
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    private function esErrorLimiteImagenes(string $mensaje): bool
    {
        $n = mb_strtolower($mensaje);

        return str_contains($n, 'limit')
            || str_contains($n, 'máximo')
            || str_contains($n, 'maximo')
            || str_contains($n, 'too many')
            || str_contains($n, '9 image');
    }

    private function tiendaId(): string
    {
        $id = $this->api->config()->store_id ?: config('tiendanube.store_id');

        return $id !== null && $id !== '' ? (string) $id : '0';
    }

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
}
