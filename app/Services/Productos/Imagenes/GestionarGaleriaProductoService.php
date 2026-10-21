<?php

namespace App\Services\Productos\Imagenes;

use App\Models\Producto;
use App\Models\ProductoImagen;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GestionarGaleriaProductoService
{
    public function __construct(
        private readonly ValidarArchivoImagenProducto $validador,
        private readonly AlmacenArchivoImagenProducto $archivos,
        private readonly GenerarMiniaturaImagenProducto $miniaturas,
    ) {}

    public function agregar(Producto $producto, UploadedFile $archivo, ?int $loteArchivoId = null): ProductoImagen
    {
        $meta = $this->validador->inspeccionar($archivo->getRealPath());
        $clave = $this->archivos->guardarOriginal($producto->id, $archivo->getRealPath(), $meta['extension']);

        return $this->confirmarNueva($producto->id, $clave, $meta, $loteArchivoId);
    }

    public function agregarDesdeTemporal(int $productoId, string $claveTemporal, int $loteArchivoId): ProductoImagen
    {
        [$ruta, $temporal] = $this->archivos->rutaLocal($claveTemporal);
        try {
            $meta = $this->validador->inspeccionar($ruta);
            $clave = $this->archivos->guardarOriginal($productoId, $ruta, $meta['extension']);
        } finally {
            if ($temporal && is_file($ruta)) {
                unlink($ruta);
            }
        }

        return $this->confirmarNueva($productoId, $clave, $meta, $loteArchivoId);
    }

    public function reemplazar(Producto $producto, ProductoImagen $imagen, UploadedFile $archivo): ProductoImagen
    {
        $this->pertenece($producto, $imagen);
        $meta = $this->validador->inspeccionar($archivo->getRealPath());
        $clave = $this->archivos->guardarOriginal($producto->id, $archivo->getRealPath(), $meta['extension']);
        $anteriores = array_filter([$imagen->clave_original, $imagen->clave_miniatura]);

        try {
            $actualizada = $this->bloquear($producto->id, function () use ($producto, $imagen, $clave, $meta) {
                $fila = $this->filaBloqueada($producto->id, $imagen->id);
                $this->aplicarArchivo($fila, $clave, $meta);

                return $fila->fresh() ?? $fila;
            });
        } catch (\Throwable $e) {
            $this->descartarClaveNueva($clave);
            throw $e;
        }

        $this->retirarClaves($anteriores, [$actualizada->clave_original, $actualizada->clave_miniatura]);

        return $actualizada;
    }

    public function marcarPrincipal(Producto $producto, ProductoImagen $imagen): void
    {
        $this->pertenece($producto, $imagen);
        $this->bloquear($producto->id, function () use ($producto, $imagen) {
            $this->filaBloqueada($producto->id, $imagen->id);
            $this->fijarPrincipal($producto->id, $imagen->id);
        });
    }

    /**
     * @param  list<int>  $orden
     */
    public function reordenar(Producto $producto, array $orden): void
    {
        $this->bloquear($producto->id, function () use ($producto, $orden) {
            $actuales = ProductoImagen::query()
                ->where('producto_id', $producto->id)
                ->orderBy('id')
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
            $recibidos = array_map(fn ($id) => (int) $id, $orden);
            sort($actuales);
            $copia = $recibidos;
            sort($copia);
            if ($actuales === [] || $actuales !== $copia || count($recibidos) !== count(array_unique($recibidos))) {
                throw ValidationException::withMessages([
                    'orden' => 'El orden debe incluir cada imagen del producto una sola vez.',
                ]);
            }

            ProductoImagen::query()
                ->where('producto_id', $producto->id)
                ->update(['posicion' => DB::raw('-posicion')]);

            foreach ($recibidos as $indice => $imagenId) {
                ProductoImagen::query()
                    ->where('producto_id', $producto->id)
                    ->whereKey($imagenId)
                    ->update(['posicion' => $indice + 1]);
            }
        });
    }

    public function mover(Producto $producto, ProductoImagen $imagen, string $direccion): void
    {
        $this->pertenece($producto, $imagen);
        $this->bloquear($producto->id, function () use ($producto, $imagen, $direccion) {
            $fila = $this->filaBloqueada($producto->id, $imagen->id);
            $vecino = ProductoImagen::query()
                ->where('producto_id', $producto->id)
                ->when($direccion === 'arriba', fn ($q) => $q->where('posicion', '<', $fila->posicion)->orderByDesc('posicion'))
                ->when($direccion !== 'arriba', fn ($q) => $q->where('posicion', '>', $fila->posicion)->orderBy('posicion'))
                ->first();
            if (! $vecino instanceof ProductoImagen) {
                return;
            }
            $posicionFila = $fila->posicion;
            $posicionVecino = $vecino->posicion;
            ProductoImagen::query()
                ->whereIn('id', [$fila->id, $vecino->id])
                ->update(['posicion' => DB::raw('-posicion')]);
            ProductoImagen::query()->whereKey($fila->id)->update(['posicion' => $posicionVecino]);
            ProductoImagen::query()->whereKey($vecino->id)->update(['posicion' => $posicionFila]);
        });
    }

    public function desvincular(Producto $producto, ProductoImagen $imagen): void
    {
        $this->pertenece($producto, $imagen);
        $claves = array_filter([$imagen->clave_original, $imagen->clave_miniatura]);
        $this->bloquear($producto->id, function () use ($producto, $imagen) {
            $fila = $this->filaBloqueada($producto->id, $imagen->id);
            $fila->delete();
            $this->asegurarPrincipal($producto->id);
        });
        $this->retirarClaves($claves, []);
    }

    public function reintentarMiniatura(Producto $producto, ProductoImagen $imagen): ProductoImagen
    {
        $this->pertenece($producto, $imagen);
        $anterior = $imagen->clave_miniatura;
        try {
            $clave = $this->miniaturas->generar($imagen->clave_original);
        } catch (ValidationException) {
            return $imagen;
        }

        $actualizada = $this->bloquear($producto->id, function () use ($producto, $imagen, $clave) {
            $fila = $this->filaBloqueada($producto->id, $imagen->id);
            $fila->clave_miniatura = $clave;
            $fila->estado = 'lista';
            $fila->save();

            return $fila;
        });

        if ($anterior && $anterior !== $actualizada->clave_miniatura) {
            $this->retirarClaves([$anterior], [$actualizada->clave_miniatura]);
        }

        return $actualizada;
    }

    /**
     * @param  array{extension: string, mime: string, ancho: int, alto: int, tamano: int}  $meta
     */
    private function confirmarNueva(int $productoId, string $clave, array $meta, ?int $loteArchivoId): ProductoImagen
    {
        try {
            return $this->bloquear($productoId, function () use ($productoId, $clave, $meta, $loteArchivoId) {
                $max = (int) ProductoImagen::query()->where('producto_id', $productoId)->max('posicion');
                $imagen = new ProductoImagen([
                    'producto_id' => $productoId,
                    'posicion' => $max < 1 ? 1 : $max + 1,
                    'lote_archivo_id' => $loteArchivoId,
                    'clave_original' => $clave,
                    'mime' => $meta['mime'],
                    'tamano_bytes' => $meta['tamano'],
                    'ancho' => $meta['ancho'],
                    'alto' => $meta['alto'],
                    'estado' => 'lista',
                ]);
                $this->aplicarMiniatura($imagen, $clave);
                $hayPrincipal = ProductoImagen::query()
                    ->where('producto_id', $productoId)
                    ->whereNotNull('principal_producto_id')
                    ->exists();
                $imagen->principal_producto_id = $hayPrincipal ? null : $productoId;
                $imagen->save();

                return $imagen;
            });
        } catch (UniqueConstraintViolationException $e) {
            $this->descartarClaveNueva($clave);
            if ($loteArchivoId === null) {
                throw $e;
            }
            $existente = ProductoImagen::query()->where('lote_archivo_id', $loteArchivoId)->first();
            if ($existente instanceof ProductoImagen) {
                return $existente;
            }
            throw $e;
        } catch (\Throwable $e) {
            $this->descartarClaveNueva($clave);
            throw $e;
        }
    }

    /**
     * @param  array{mime: string, ancho: int, alto: int, tamano: int}  $meta
     */
    private function aplicarArchivo(ProductoImagen $imagen, string $clave, array $meta): void
    {
        $imagen->clave_original = $clave;
        $imagen->clave_miniatura = null;
        $imagen->mime = $meta['mime'];
        $imagen->tamano_bytes = $meta['tamano'];
        $imagen->ancho = $meta['ancho'];
        $imagen->alto = $meta['alto'];
        $imagen->estado = 'lista';
        $this->aplicarMiniatura($imagen, $clave);
        $imagen->save();
    }

    private function aplicarMiniatura(ProductoImagen $imagen, string $claveOriginal): void
    {
        try {
            $imagen->clave_miniatura = $this->miniaturas->generar($claveOriginal);
            $imagen->estado = 'lista';
        } catch (ValidationException) {
            $imagen->clave_miniatura = null;
            $imagen->estado = 'miniatura_error';
        }
    }

    private function fijarPrincipal(int $productoId, int $imagenId): void
    {
        ProductoImagen::query()
            ->where('producto_id', $productoId)
            ->update(['principal_producto_id' => null]);
        ProductoImagen::query()
            ->where('producto_id', $productoId)
            ->whereKey($imagenId)
            ->update(['principal_producto_id' => $productoId]);
    }

    private function asegurarPrincipal(int $productoId): void
    {
        $imagenes = ProductoImagen::query()
            ->where('producto_id', $productoId)
            ->orderBy('posicion')
            ->orderBy('id')
            ->get();
        if ($imagenes->isEmpty()) {
            return;
        }
        $principal = $imagenes->first(fn (ProductoImagen $imagen) => $imagen->principal_producto_id !== null);
        if ($principal instanceof ProductoImagen) {
            ProductoImagen::query()
                ->where('producto_id', $productoId)
                ->whereKeyNot($principal->id)
                ->update(['principal_producto_id' => null]);

            return;
        }
        $this->fijarPrincipal($productoId, (int) $imagenes->first()->id);
    }

    private function bloquear(int $productoId, callable $callback): mixed
    {
        return DB::transaction(function () use ($productoId, $callback) {
            $producto = Producto::query()->whereKey($productoId)->lockForUpdate()->first();
            if (! $producto instanceof Producto) {
                throw ValidationException::withMessages([
                    'producto' => 'El producto no existe.',
                ]);
            }

            return $callback($producto);
        });
    }

    private function filaBloqueada(int $productoId, int $imagenId): ProductoImagen
    {
        $fila = ProductoImagen::query()
            ->where('producto_id', $productoId)
            ->whereKey($imagenId)
            ->lockForUpdate()
            ->first();
        if (! $fila instanceof ProductoImagen) {
            throw ValidationException::withMessages([
                'imagen' => 'La imagen no pertenece a este producto.',
            ]);
        }

        return $fila;
    }

    private function pertenece(Producto $producto, ProductoImagen $imagen): void
    {
        if ((int) $imagen->producto_id !== (int) $producto->id) {
            abort(404);
        }
    }

    /**
     * @param  list<string|null>  $claves
     * @param  list<string|null>  $conservar
     */
    private function retirarClaves(array $claves, array $conservar): void
    {
        $conservadas = array_filter($conservar);
        foreach (array_unique(array_filter($claves)) as $clave) {
            if (in_array($clave, $conservadas, true)) {
                continue;
            }
            try {
                $this->archivos->eliminarSiLibre($clave);
            } catch (\Throwable) {
                $this->archivos->registrarLimpieza($clave);
            }
        }
    }

    private function descartarClaveNueva(string $clave): void
    {
        $this->retirarClaves([$clave, dirname($clave).'/miniatura.webp'], []);
    }
}
