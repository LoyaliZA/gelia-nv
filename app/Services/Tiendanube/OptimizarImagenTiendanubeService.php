<?php

namespace App\Services\Tiendanube;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class OptimizarImagenTiendanubeService
{
    public const MIN_ALERTA = 800;

    public const TARGET_SIDE = 1280;

    public const MODO_NONE = 'none';

    public const MODO_FIT = 'fit';

    public const MODO_SQUARE = 'square';

    private const WEBP_QUALITY = 82;

    private const JPEG_QUALITY = 90;

    /**
     * @param  array{convertir_webp?: bool, modo_1280?: string}  $opts
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
    public function ejecutar(UploadedFile $file, array $opts = []): array
    {
        $opciones = self::normalizarOpciones($opts);
        $convertirWebp = $opciones['convertir_webp'];
        $modo1280 = $opciones['modo_1280'];

        $originalPath = $file->getRealPath();
        $originalName = $file->getClientOriginalName() ?: ('imagen.'.$file->guessExtension());
        $mime = $this->normalizarMime($file->getMimeType() ?: '');

        $size = @getimagesize($originalPath);
        $origW = is_array($size) ? (int) ($size[0] ?? 0) : 0;
        $origH = is_array($size) ? (int) ($size[1] ?? 0) : 0;
        if ($origW <= 0 || $origH <= 0) {
            $origW = null;
            $origH = null;
        }

        $flags = self::flagsDesdeDimensiones($origW, $origH);
        $passthrough = array_merge([
            'path' => $originalPath,
            'filename' => $originalName,
            'cleanup' => false,
            'width' => $origW,
            'height' => $origH,
            'output_width' => $origW,
            'output_height' => $origH,
        ], $flags);

        // GIF: passthrough (sin WebP ni 1280); dims/alertas ya calculados.
        if ($mime === 'image/gif' || str_ends_with(strtolower($originalName), '.gif')) {
            return $passthrough;
        }

        $needsResize = $modo1280 === self::MODO_FIT || $modo1280 === self::MODO_SQUARE;
        $needsWebp = $convertirWebp && $mime !== 'image/webp' && function_exists('imagewebp');

        if (! $needsResize && ! $needsWebp) {
            return $passthrough;
        }

        if ($origW === null || $origH === null) {
            return $passthrough;
        }

        $loaded = $this->createImageResource($originalPath, $mime);
        if (! $loaded) {
            return $passthrough;
        }

        [$width, $height, $resource] = $loaded;

        if ($needsResize) {
            $resized = $modo1280 === self::MODO_SQUARE
                ? $this->aplicarSquare($resource, $width, $height)
                : $this->aplicarFit($resource, $width, $height);

            if ($resized) {
                [$resource, $width, $height] = $resized;
            }
        }

        $outMime = $needsWebp ? 'image/webp' : $mime;
        $ext = match ($outMime) {
            'image/webp' => 'webp',
            'image/png' => 'png',
            default => 'jpg',
        };

        // Ya es el destino y no cambió tamaño: no re-encodificar.
        if ($outMime === $mime && $width === $origW && $height === $origH) {
            imagedestroy($resource);

            return $passthrough;
        }

        $tmpPath = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR
            .'tn_img_'.Str::uuid().'.'.$ext;

        $ok = $this->guardarResource($resource, $tmpPath, $outMime);
        imagedestroy($resource);

        if (! $ok || ! is_file($tmpPath)) {
            return $passthrough;
        }

        $base = pathinfo($originalName, PATHINFO_FILENAME) ?: 'imagen';

        return array_merge([
            'path' => $tmpPath,
            'filename' => $base.'.'.$ext,
            'cleanup' => true,
            'width' => $origW,
            'height' => $origH,
            'output_width' => $width,
            'output_height' => $height,
        ], $flags);
    }

    /**
     * @param  array{convertir_webp?: bool|int|string, modo_1280?: string}  $opts
     * @return array{convertir_webp: bool, modo_1280: string}
     */
    public static function normalizarOpciones(array $opts = []): array
    {
        $modo = strtolower((string) ($opts['modo_1280'] ?? self::MODO_NONE));
        if (! in_array($modo, [self::MODO_NONE, self::MODO_FIT, self::MODO_SQUARE], true)) {
            $modo = self::MODO_NONE;
        }

        return [
            'convertir_webp' => array_key_exists('convertir_webp', $opts)
                ? filter_var($opts['convertir_webp'], FILTER_VALIDATE_BOOLEAN)
                : true,
            'modo_1280' => $modo,
        ];
    }

    /**
     * @return array{convertir_webp: bool, modo_1280: string}
     */
    public static function opcionesDesdeRequest(Request $request): array
    {
        return self::normalizarOpciones([
            'convertir_webp' => $request->boolean('convertir_webp', true),
            'modo_1280' => (string) $request->input('modo_1280', self::MODO_NONE),
        ]);
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
     * @return array{0: \GdImage, 1: int, 2: int}|null
     */
    private function aplicarSquare(\GdImage $resource, int $width, int $height): ?array
    {
        $side = min($width, $height);
        $srcX = (int) round(($width - $side) / 2);
        $srcY = (int) round(($height - $side) / 2);
        $out = self::TARGET_SIDE;

        if ($side === $out && $width === $out && $height === $out) {
            return null;
        }

        $dest = $this->lienzo($out, $out);
        imagecopyresampled($dest, $resource, 0, 0, $srcX, $srcY, $out, $out, $side, $side);
        imagedestroy($resource);

        return [$dest, $out, $out];
    }

    /**
     * @return array{0: \GdImage, 1: int, 2: int}|null
     */
    private function aplicarFit(\GdImage $resource, int $width, int $height): ?array
    {
        $ratio = min(self::TARGET_SIDE / $width, self::TARGET_SIDE / $height, 1);
        $newW = (int) max(1, round($width * $ratio));
        $newH = (int) max(1, round($height * $ratio));

        if ($newW === $width && $newH === $height) {
            return null;
        }

        $dest = $this->lienzo($newW, $newH);
        imagecopyresampled($dest, $resource, 0, 0, 0, 0, $newW, $newH, $width, $height);
        imagedestroy($resource);

        return [$dest, $newW, $newH];
    }

    private function lienzo(int $w, int $h): \GdImage
    {
        $dest = imagecreatetruecolor($w, $h);
        imagealphablending($dest, false);
        imagesavealpha($dest, true);

        return $dest;
    }

    private function guardarResource(\GdImage $resource, string $tmpPath, string $outMime): bool
    {
        if ($outMime === 'image/jpeg') {
            $w = imagesx($resource);
            $h = imagesy($resource);
            $flat = imagecreatetruecolor($w, $h);
            $white = imagecolorallocate($flat, 255, 255, 255);
            imagefilledrectangle($flat, 0, 0, $w, $h, $white);
            imagealphablending($flat, true);
            imagecopy($flat, $resource, 0, 0, 0, 0, $w, $h);
            $ok = @imagejpeg($flat, $tmpPath, self::JPEG_QUALITY);
            imagedestroy($flat);

            return (bool) $ok;
        }

        if ($outMime === 'image/png') {
            imagealphablending($resource, false);
            imagesavealpha($resource, true);

            return (bool) @imagepng($resource, $tmpPath, 6);
        }

        if ($outMime === 'image/webp' && function_exists('imagewebp')) {
            return (bool) @imagewebp($resource, $tmpPath, self::WEBP_QUALITY);
        }

        return false;
    }

    /**
     * @return array{0: int, 1: int, 2: \GdImage}|null
     */
    private function createImageResource(string $path, ?string $mime): ?array
    {
        $resource = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            default => false,
        };

        if (! $resource) {
            return null;
        }

        return [imagesx($resource), imagesy($resource), $resource];
    }

    private function normalizarMime(string $mime): string
    {
        return $mime === 'image/jpg' ? 'image/jpeg' : $mime;
    }
}
