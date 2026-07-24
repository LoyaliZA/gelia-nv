<?php

namespace App\Services\Tiendanube;

final class TiendanubeImageSkuParser
{
    private const ALLOWED_EXT = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    /**
     * @return array{sku: string, position: int, extension: string}|null
     */
    public static function parse(string $filename): ?array
    {
        $base = basename(str_replace('\\', '/', $filename));
        if ($base === '' || str_starts_with($base, '.')) {
            return null;
        }

        $ext = strtolower(pathinfo($base, PATHINFO_EXTENSION));
        if (! in_array($ext, self::ALLOWED_EXT, true)) {
            return null;
        }

        $name = pathinfo($base, PATHINFO_FILENAME);
        if ($name === '') {
            return null;
        }

        if (! preg_match('/^(.+?)(?:_(\d+))?$/', $name, $m)) {
            return null;
        }

        $sku = trim($m[1]);
        if ($sku === '') {
            return null;
        }

        $position = isset($m[2]) && $m[2] !== '' ? max(1, (int) $m[2]) : 1;

        return [
            'sku' => $sku,
            'position' => $position,
            'extension' => $ext,
        ];
    }

    /**
     * @return list<string>
     */
    public static function allowedExtensions(): array
    {
        return self::ALLOWED_EXT;
    }
}
