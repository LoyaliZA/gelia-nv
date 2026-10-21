<?php

namespace App\Services\Productos\Imagenes;

use Illuminate\Validation\ValidationException;
use RuntimeException;

class GenerarMiniaturaImagenProducto
{
    public function __construct(
        private readonly AlmacenArchivoImagenProducto $archivos,
    ) {}

    public function generar(string $claveOriginal): string
    {
        [$ruta, $temporal] = $this->archivos->rutaLocal($claveOriginal);
        $recurso = null;
        $salida = null;
        try {
            $info = @getimagesize($ruta);
            if ($info === false) {
                throw new RuntimeException('No se pudo leer la imagen para la miniatura.');
            }
            $tipo = (int) ($info[2] ?? 0);
            $recurso = match ($tipo) {
                IMAGETYPE_JPEG => @imagecreatefromjpeg($ruta),
                IMAGETYPE_PNG => @imagecreatefrompng($ruta),
                IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($ruta) : false,
                default => false,
            };
            if (! $recurso) {
                throw new RuntimeException('No se pudo decodificar la imagen para la miniatura.');
            }

            $lado = (int) config('productos_imagenes.miniatura_lado', 480);
            $ancho = imagesx($recurso);
            $alto = imagesy($recurso);
            $destino = $recurso;
            if ($ancho > $lado || $alto > $lado) {
                $ratio = min($lado / $ancho, $lado / $alto);
                $nuevoAncho = max(1, (int) round($ancho * $ratio));
                $nuevoAlto = max(1, (int) round($alto * $ratio));
                $destino = imagecreatetruecolor($nuevoAncho, $nuevoAlto);
                imagealphablending($destino, false);
                imagesavealpha($destino, true);
                $transparente = imagecolorallocatealpha($destino, 0, 0, 0, 127);
                imagefilledrectangle($destino, 0, 0, $nuevoAncho, $nuevoAlto, $transparente);
                imagecopyresampled($destino, $recurso, 0, 0, 0, 0, $nuevoAncho, $nuevoAlto, $ancho, $alto);
                imagedestroy($recurso);
                $recurso = null;
            }

            if (! function_exists('imagewebp')) {
                throw new RuntimeException('WebP no está disponible para la miniatura.');
            }

            $salida = tempnam(sys_get_temp_dir(), 'pthumb');
            if ($salida === false || ! imagewebp($destino, $salida, (int) config('productos_imagenes.miniatura_calidad', 82))) {
                throw new RuntimeException('No se pudo escribir la miniatura.');
            }
            imagedestroy($destino);
            $destino = null;

            return $this->archivos->guardarMiniatura($claveOriginal, $salida);
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages([
                'miniatura' => $e->getMessage(),
            ]);
        } finally {
            if (is_resource($recurso) || $recurso instanceof \GdImage) {
                imagedestroy($recurso);
            }
            if (isset($destino) && (is_resource($destino) || $destino instanceof \GdImage) && $destino !== $recurso) {
                imagedestroy($destino);
            }
            if (is_string($salida) && is_file($salida)) {
                unlink($salida);
            }
            if ($temporal && is_file($ruta)) {
                unlink($ruta);
            }
        }
    }
}
