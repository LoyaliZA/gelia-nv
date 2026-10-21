<?php

namespace App\Services\Productos\Imagenes;

use App\Models\ProductoImagen;
use App\Models\ProductoImagenLimpieza;
use App\Models\ProductoImagenLoteArchivo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AlmacenArchivoImagenProducto
{
    public function disco(): string
    {
        return (string) config('productos_imagenes.disk', 'local');
    }

    public function prefijo(): string
    {
        return trim((string) config('productos_imagenes.prefijo', 'productos/imagenes'), '/');
    }

    public function guardarOriginal(int $productoId, string $rutaLocal, string $extension): string
    {
        $clave = $this->prefijo().'/'.$productoId.'/'.Str::uuid().'/original.'.$extension;

        return $this->copiar($rutaLocal, $clave);
    }

    public function guardarTemporal(string $loteUuid, string $rutaLocal, string $extension): string
    {
        $clave = $this->prefijo().'/tmp/'.$loteUuid.'/'.Str::uuid().'.'.$extension;

        return $this->copiar($rutaLocal, $clave);
    }

    public function guardarMiniatura(string $claveOriginal, string $rutaLocal): string
    {
        $clave = dirname($claveOriginal).'/miniatura.webp';

        return $this->copiar($rutaLocal, $clave);
    }

    public function claveDelProducto(string $clave, int $productoId): bool
    {
        $base = $this->prefijo().'/'.$productoId.'/';

        return $clave !== ''
            && ! str_contains($clave, '..')
            && str_starts_with($clave, $base);
    }

    public function responder(string $clave): StreamedResponse
    {
        $disk = Storage::disk($this->disco());
        abort_unless($disk->exists($clave), 404);

        return $disk->response($clave, null, [
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function eliminarSiLibre(string $clave): void
    {
        if (! $this->claveBajoPrefijo($clave)) {
            return;
        }
        if ($this->estaReferenciada($clave)) {
            return;
        }
        $disk = Storage::disk($this->disco());
        if (! $disk->exists($clave)) {
            return;
        }
        if (! $disk->delete($clave)) {
            throw new RuntimeException('No se pudo borrar el archivo.');
        }
    }

    public function registrarLimpieza(string $clave): void
    {
        if (! $this->claveBajoPrefijo($clave)) {
            return;
        }
        ProductoImagenLimpieza::query()->firstOrCreate(['clave' => $clave]);
    }

    public function reintentarLimpiezas(): int
    {
        $n = 0;
        ProductoImagenLimpieza::query()->orderBy('id')->limit(200)->get()->each(function (ProductoImagenLimpieza $fila) use (&$n) {
            $fila->increment('intentos');
            if (! $this->claveBajoPrefijo($fila->clave) || $this->estaReferenciada($fila->clave)) {
                $fila->delete();

                return;
            }
            try {
                $this->eliminarSiLibre($fila->clave);
                $fila->delete();
                $n++;
            } catch (RuntimeException) {
                // El siguiente paso del comando vuelve a intentarlo.
            }
        });

        return $n;
    }

    public function borrarTemporalesDe(string $loteUuid): void
    {
        $directorio = $this->prefijo().'/tmp/'.$loteUuid;
        if (! $this->claveBajoPrefijo($directorio)) {
            return;
        }
        Storage::disk($this->disco())->deleteDirectory($directorio);
    }

    public function rutaLocal(string $clave): array
    {
        $disk = Storage::disk($this->disco());
        $driver = (string) config('filesystems.disks.'.$this->disco().'.driver');
        if ($driver === 'local') {
            return [$disk->path($clave), false];
        }

        $tmp = tempnam(sys_get_temp_dir(), 'pimg');
        if ($tmp === false) {
            throw ValidationException::withMessages([
                'archivo' => 'No se pudo preparar la imagen.',
            ]);
        }
        file_put_contents($tmp, $disk->get($clave));

        return [$tmp, true];
    }

    private function copiar(string $rutaLocal, string $clave): string
    {
        $stream = fopen($rutaLocal, 'rb');
        if ($stream === false) {
            throw ValidationException::withMessages([
                'archivo' => 'No se pudo leer el archivo.',
            ]);
        }
        try {
            Storage::disk($this->disco())->writeStream($clave, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        return $clave;
    }

    private function claveBajoPrefijo(string $clave): bool
    {
        return $clave !== ''
            && ! str_contains($clave, '..')
            && str_starts_with($clave, $this->prefijo().'/');
    }

    private function estaReferenciada(string $clave): bool
    {
        $enGaleria = ProductoImagen::query()
            ->where('clave_original', $clave)
            ->orWhere('clave_miniatura', $clave)
            ->exists();
        if ($enGaleria) {
            return true;
        }

        return ProductoImagenLoteArchivo::query()
            ->where('clave_temporal', $clave)
            ->whereNotIn('estado', ['descartado', 'expirado', 'completado'])
            ->exists();
    }
}
