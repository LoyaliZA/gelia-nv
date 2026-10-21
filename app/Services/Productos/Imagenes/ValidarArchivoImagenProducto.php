<?php

namespace App\Services\Productos\Imagenes;

use Illuminate\Validation\ValidationException;

class ValidarArchivoImagenProducto
{
    /**
     * @return array{extension: string, mime: string, ancho: int, alto: int, tamano: int}
     */
    public function inspeccionar(string $ruta): array
    {
        $tamano = is_file($ruta) ? filesize($ruta) : false;
        if ($tamano === false || $tamano < 1 || $tamano > (int) config('productos_imagenes.max_bytes')) {
            throw ValidationException::withMessages([
                'archivo' => 'La imagen supera el tamaño permitido o está vacía.',
            ]);
        }

        $info = @getimagesize($ruta);
        if ($info === false) {
            throw ValidationException::withMessages([
                'archivo' => 'El archivo no es una imagen JPEG, PNG o WebP válida.',
            ]);
        }

        $ancho = (int) ($info[0] ?? 0);
        $alto = (int) ($info[1] ?? 0);
        if ($this->superaLimitePixeles($ancho, $alto)) {
            throw ValidationException::withMessages([
                'archivo' => 'La imagen supera la resolución permitida.',
            ]);
        }

        $tipo = (int) ($info[2] ?? 0);
        $extension = match ($tipo) {
            IMAGETYPE_JPEG => 'jpg',
            IMAGETYPE_PNG => 'png',
            IMAGETYPE_WEBP => 'webp',
            default => null,
        };
        if ($extension === null) {
            throw ValidationException::withMessages([
                'archivo' => 'El archivo no es una imagen JPEG, PNG o WebP válida.',
            ]);
        }

        $recurso = $this->decodificar($ruta, $tipo);
        if ($recurso === null) {
            throw ValidationException::withMessages([
                'archivo' => 'No se pudo leer el contenido de la imagen.',
            ]);
        }
        imagedestroy($recurso);

        return [
            'extension' => $extension,
            'mime' => (string) ($info['mime'] ?? 'application/octet-stream'),
            'ancho' => $ancho,
            'alto' => $alto,
            'tamano' => (int) $tamano,
        ];
    }

    public function superaLimitePixeles(int $ancho, int $alto): bool
    {
        if ($ancho < 1 || $alto < 1) {
            return true;
        }

        $pixeles = $ancho * $alto;
        if ($pixeles > (int) config('productos_imagenes.max_pixeles', 8_000_000)) {
            return true;
        }

        $trabajo = $pixeles * 4 * 3;
        $tope = $this->mitadMemoriaBytes();

        return $tope !== null && $trabajo > $tope;
    }

    private function mitadMemoriaBytes(): ?int
    {
        $limite = ini_get('memory_limit');
        if (! is_string($limite) || $limite === '' || $limite === '-1') {
            return null;
        }

        $unidades = ['G' => 1024 ** 3, 'M' => 1024 ** 2, 'K' => 1024];
        $sufijo = strtoupper(substr($limite, -1));
        if (isset($unidades[$sufijo])) {
            return (int) floor(((float) substr($limite, 0, -1)) * $unidades[$sufijo] / 2);
        }
        if (! is_numeric($limite)) {
            return null;
        }

        return (int) floor(((float) $limite) / 2);
    }

    private function decodificar(string $ruta, int $tipo): mixed
    {
        $recurso = match ($tipo) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($ruta),
            IMAGETYPE_PNG => @imagecreatefrompng($ruta),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($ruta) : false,
            default => false,
        };

        return $recurso ?: null;
    }
}
