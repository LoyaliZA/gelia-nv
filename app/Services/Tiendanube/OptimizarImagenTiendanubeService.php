<?php

namespace App\Services\Tiendanube;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class OptimizarImagenTiendanubeService
{
    public const MIN_ALERTA = 800;

    public const TARGET_SIDE = 1280;

    private const WEBP_QUALITY = 82;

    /**
     * @return array{
     *     path: string,
     *     filename: string,
     *     cleanup: bool,
     *     width: ?int,
     *     height: ?int,
     *     alerta_pequena: bool,
     *     alerta_no_cuadrada: bool,
     *     requiere_revision: bool,
     *     output_width: ?int,
     *     output_height: ?int
     * }
     */
    public function ejecutar(UploadedFile $file): array
    {
        $originalPath = $file->getRealPath();
        $originalName = $file->getClientOriginalName() ?: ('imagen.'.$file->guessExtension());
        $mime = $file->getMimeType() ?: '';

        $size = @getimagesize($originalPath);
        $origW = is_array($size) ? (int) ($size[0] ?? 0) : 0;
        $origH = is_array($size) ? (int) ($size[1] ?? 0) : 0;
        if ($origW <= 0 || $origH <= 0) {
            $origW = null;
            $origH = null;
        }

        $flags = self::flagsDesdeDimensiones($origW, $origH);

        // GIF: passthrough (sin WebP); dims/alertas ya calculados.
        if ($mime === 'image/gif' || str_ends_with(strtolower($originalName), '.gif')) {
            return array_merge([
                'path' => $originalPath,
                'filename' => $originalName,
                'cleanup' => false,
                'width' => $origW,
                'height' => $origH,
                'output_width' => $origW,
                'output_height' => $origH,
            ], $flags);
        }

        $canWebp = function_exists('imagewebp')
            && in_array($mime, ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'], true);

        if (! $canWebp || $origW === null || $origH === null) {
            return array_merge([
                'path' => $originalPath,
                'filename' => $originalName,
                'cleanup' => false,
                'width' => $origW,
                'height' => $origH,
                'output_width' => $origW,
                'output_height' => $origH,
            ], $flags);
        }

        $loaded = $this->createImageResource($originalPath, $mime);
        if (! $loaded) {
            return array_merge([
                'path' => $originalPath,
                'filename' => $originalName,
                'cleanup' => false,
                'width' => $origW,
                'height' => $origH,
                'output_width' => $origW,
                'output_height' => $origH,
            ], $flags);
        }

        [$width, $height, $resource] = $loaded;
        $minSide = min($width, $height);
        $shouldResize = $minSide >= self::MIN_ALERTA;

        if ($shouldResize) {
            if ($width === $height) {
                $newW = self::TARGET_SIDE;
                $newH = self::TARGET_SIDE;
            } else {
                $ratio = self::TARGET_SIDE / $minSide;
                $newW = (int) round($width * $ratio);
                $newH = (int) round($height * $ratio);
            }

            if ($newW !== $width || $newH !== $height) {
                $resized = imagecreatetruecolor($newW, $newH);
                imagealphablending($resized, false);
                imagesavealpha($resized, true);
                imagecopyresampled($resized, $resource, 0, 0, 0, 0, $newW, $newH, $width, $height);
                imagedestroy($resource);
                $resource = $resized;
                $width = $newW;
                $height = $newH;
            }
        }

        $tmpPath = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR
            .'tn_img_'.Str::uuid().'.webp';

        $ok = @imagewebp($resource, $tmpPath, self::WEBP_QUALITY);
        imagedestroy($resource);

        if (! $ok || ! is_file($tmpPath)) {
            return array_merge([
                'path' => $originalPath,
                'filename' => $originalName,
                'cleanup' => false,
                'width' => $origW,
                'height' => $origH,
                'output_width' => $origW,
                'output_height' => $origH,
            ], $flags);
        }

        $base = pathinfo($originalName, PATHINFO_FILENAME) ?: 'imagen';

        return array_merge([
            'path' => $tmpPath,
            'filename' => $base.'.webp',
            'cleanup' => true,
            'width' => $origW,
            'height' => $origH,
            'output_width' => $width,
            'output_height' => $height,
        ], $flags);
    }

    /**
     * @return array{alerta_pequena: bool, alerta_no_cuadrada: bool, requiere_revision: bool}
     */
    public static function flagsDesdeDimensiones(?int $width, ?int $height): array
    {
        if ($width === null || $height === null || $width <= 0 || $height <= 0) {
            return [
                'alerta_pequena' => false,
                'alerta_no_cuadrada' => false,
                'requiere_revision' => false,
            ];
        }

        $alertaPequena = min($width, $height) < self::MIN_ALERTA;
        $alertaNoCuadrada = $width !== $height;

        return [
            'alerta_pequena' => $alertaPequena,
            'alerta_no_cuadrada' => $alertaNoCuadrada,
            'requiere_revision' => $alertaPequena || $alertaNoCuadrada,
        ];
    }

    /**
     * @return array{0: int, 1: int, 2: \GdImage}|null
     */
    private function createImageResource(string $path, ?string $mime): ?array
    {
        $resource = match ($mime) {
            'image/jpeg', 'image/jpg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/webp' => @imagecreatefromwebp($path),
            default => false,
        };

        if (! $resource) {
            return null;
        }

        return [imagesx($resource), imagesy($resource), $resource];
    }
}
