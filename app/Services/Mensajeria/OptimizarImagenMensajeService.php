<?php

namespace App\Services\Mensajeria;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OptimizarImagenMensajeService
{
    private const MAX_BYTES = 10 * 1024 * 1024;
    private const MAX_SIDE = 1600;
    private const WEBP_QUALITY = 82;

    public function ejecutar(UploadedFile $file, int $conversacionId): array
    {
        if ($file->getSize() > self::MAX_BYTES) {
            throw ValidationException::withMessages([
                'archivo' => 'La imagen debe pesar menos de 10 MB.',
            ]);
        }

        $loaded = $this->createImageResource($file->getRealPath(), $file->getMimeType());

        if (!$loaded) {
            throw ValidationException::withMessages([
                'archivo' => 'Formato de imagen no soportado. Use JPG, PNG o WEBP.',
            ]);
        }

        [$width, $height, $resource] = $loaded;

        if ($width > self::MAX_SIDE || $height > self::MAX_SIDE) {
            $ratio = min(self::MAX_SIDE / $width, self::MAX_SIDE / $height);
            $newW = (int) round($width * $ratio);
            $newH = (int) round($height * $ratio);
            $resized = imagecreatetruecolor($newW, $newH);
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            imagecopyresampled($resized, $resource, 0, 0, 0, 0, $newW, $newH, $width, $height);
            imagedestroy($resource);
            $resource = $resized;
        }

        $filename = Str::uuid() . '.webp';
        $directory = "mensajeria/{$conversacionId}";
        $relativePath = "{$directory}/{$filename}";
        $fullPath = Storage::disk('public')->path($relativePath);

        if (!is_dir(dirname($fullPath))) {
            mkdir(dirname($fullPath), 0755, true);
        }

        if (!function_exists('imagewebp')) {
            imagedestroy($resource);
            throw ValidationException::withMessages([
                'archivo' => 'WebP no está disponible en el servidor.',
            ]);
        }

        imagewebp($resource, $fullPath, self::WEBP_QUALITY);
        imagedestroy($resource);

        return [
            'ruta' => $relativePath,
            'tamano' => filesize($fullPath) ?: 0,
            'nombre_original' => $file->getClientOriginalName(),
            'mime' => 'image/webp',
            'thumbnail_ruta' => $relativePath,
        ];
    }

    private function createImageResource(string $path, ?string $mime): ?array
    {
        $resource = match ($mime) {
            'image/jpeg', 'image/jpg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/webp' => @imagecreatefromwebp($path),
            default => false,
        };

        if (!$resource) {
            return null;
        }

        return [imagesx($resource), imagesy($resource), $resource];
    }
}
