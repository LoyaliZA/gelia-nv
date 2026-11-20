<?php

namespace App\Services\Activos;

use App\Models\Activo;
use GdImage;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GuardarFotosRegistroActivoService
{
    private const MAX_SIDE = 1600;

    private const WEBP_QUALITY = 82;

    public function ejecutar(Activo $activo, array $archivos): void
    {
        foreach (array_values($archivos) as $indice => $archivo) {
            if (! $archivo instanceof UploadedFile || ! $archivo->isValid()) {
                throw ValidationException::withMessages(['fotos' => 'No se pudo leer una de las fotografías.']);
            }

            $imagen = $this->prepararImagen($archivo);
            $directorio = "activos/{$activo->id}";
            $ruta = $directorio.'/'.Str::uuid().'.webp';
            $disco = Storage::disk('public');
            $tamano = $this->guardarWebp($disco, $directorio, $ruta, $imagen);

            $activo->fotos()->create([
                'ruta' => $ruta,
                'nombre_original' => $archivo->getClientOriginalName(),
                'orden' => $indice + 1,
                'es_principal' => $indice === 0,
                'tamano_bytes' => $tamano,
            ]);
        }
    }

    private function prepararImagen(UploadedFile $archivo): GdImage
    {
        if (! function_exists('imagewebp')) {
            throw ValidationException::withMessages(['fotos' => 'WebP no está disponible en el servidor.']);
        }

        $origen = $archivo->getRealPath();
        $imagen = match ($archivo->getMimeType()) {
            'image/jpeg', 'image/jpg' => @imagecreatefromjpeg($origen),
            'image/png' => @imagecreatefrompng($origen),
            'image/webp' => @imagecreatefromwebp($origen),
            default => false,
        };

        if (! $imagen) {
            throw ValidationException::withMessages(['fotos' => 'No se pudo abrir una de las fotografías.']);
        }

        $ancho = imagesx($imagen);
        $alto = imagesy($imagen);
        if (max($ancho, $alto) <= self::MAX_SIDE) {
            return $imagen;
        }

        $escala = self::MAX_SIDE / max($ancho, $alto);
        $nuevoAncho = max(1, (int) round($ancho * $escala));
        $nuevoAlto = max(1, (int) round($alto * $escala));
        $redimensionada = imagecreatetruecolor($nuevoAncho, $nuevoAlto);
        imagealphablending($redimensionada, false);
        imagesavealpha($redimensionada, true);
        imagecopyresampled($redimensionada, $imagen, 0, 0, 0, 0, $nuevoAncho, $nuevoAlto, $ancho, $alto);

        return $redimensionada;
    }

    private function guardarWebp(FilesystemAdapter $disco, string $directorio, string $ruta, GdImage $imagen): int
    {
        $rutaAbsoluta = $disco->path($ruta);
        $creado = false;
        DB::afterRollBack(function () use ($disco, $ruta, &$creado): void {
            if ($creado) {
                $this->eliminarArchivo($disco, $ruta);
            }
        });

        if (! $disco->makeDirectory($directorio)) {
            throw ValidationException::withMessages(['fotos' => 'No se pudo preparar la carpeta de fotografías.']);
        }

        $destino = @fopen($rutaAbsoluta, 'xb');
        if ($destino === false) {
            throw ValidationException::withMessages(['fotos' => 'No se pudo guardar una de las fotografías.']);
        }

        $creado = true;
        try {
            $escrita = $this->escribirWebp($imagen, $destino);
        } finally {
            if (is_resource($destino)) {
                fclose($destino);
            }
        }

        clearstatcache(true, $rutaAbsoluta);
        $tamano = @filesize($rutaAbsoluta);
        if (! $escrita || ! $tamano || ! @imagecreatefromwebp($rutaAbsoluta)) {
            throw ValidationException::withMessages(['fotos' => 'No se pudo verificar una de las fotografías guardadas.']);
        }

        return $tamano;
    }

    /** @param resource $destino */
    protected function escribirWebp(GdImage $imagen, $destino): bool
    {
        return @imagewebp($imagen, $destino, self::WEBP_QUALITY);
    }

    private function eliminarArchivo(FilesystemAdapter $disco, string $ruta): void
    {
        try {
            if ($disco->delete($ruta)) {
                return;
            }
        } catch (\Throwable $error) {
            Log::error('No se pudo retirar una fotografía de un alta revertida.', [
                'ruta' => $ruta,
                'exception' => $error,
            ]);

            return;
        }

        Log::error('No se pudo retirar una fotografía de un alta revertida.', ['ruta' => $ruta]);
    }
}
