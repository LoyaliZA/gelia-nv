<?php

namespace App\Support;

use App\Models\Departamento;
use Illuminate\Support\Str;

/**
 * Logos de marca en public/Images/Logos — seleccionables por departamento.
 */
class DepartamentoLogoAssets
{
    public const FALLBACK_KEY = 'aromas_logo_negro';

    private const DIR = 'Images/Logos';

    /**
     * @return list<array{key: string, url: string, label: string, path: string}>
     */
    public static function disponibles(): array
    {
        $dir = public_path(self::DIR);
        if (! is_dir($dir)) {
            return [];
        }

        $out = [];
        foreach (scandir($dir) ?: [] as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (! in_array($ext, ['png', 'jpg', 'jpeg', 'webp'], true)) {
                continue;
            }
            $key = pathinfo($file, PATHINFO_FILENAME);
            $out[] = [
                'key' => $key,
                'url' => asset(self::DIR.'/'.$file),
                'label' => self::labelFromKey($key),
                'path' => $dir.DIRECTORY_SEPARATOR.$file,
            ];
        }

        usort($out, fn ($a, $b) => strcmp($a['label'], $b['label']));

        return $out;
    }

    /** @return list<string> */
    public static function keysDisponibles(): array
    {
        return array_column(self::disponibles(), 'key');
    }

    public static function pathParaKey(?string $key): ?string
    {
        $key = self::normalizarKey($key) ?? self::FALLBACK_KEY;
        $meta = self::metaPorKey($key);
        if ($meta === null && $key !== self::FALLBACK_KEY) {
            $meta = self::metaPorKey(self::FALLBACK_KEY);
        }

        return $meta['path'] ?? null;
    }

    /**
     * Par por sufijo (_negro ↔ _blanco) si el archivo existe.
     */
    public static function siblingVariante(?string $key, string $variante): ?string
    {
        $key = self::normalizarKey($key);
        if ($key === null) {
            return null;
        }

        $variante = $variante === 'blanco' ? 'blanco' : 'negro';
        $opuesto = $variante === 'blanco' ? 'negro' : 'blanco';

        if (str_ends_with($key, "_{$opuesto}")) {
            $candidato = substr($key, 0, -strlen("_{$opuesto}"))."_{$variante}";

            return self::normalizarKey($candidato);
        }

        if (str_ends_with($key, "_{$variante}")) {
            return $key;
        }

        return null;
    }

    /**
     * @return array{url_claro: string, url_oscuro: string, alt: string, departamento: ?string, key_claro: string, key_oscuro: string}|null
     */
    public static function brandingPublico(?Departamento $departamento): ?array
    {
        $keyClaro = self::normalizarKey($departamento?->logo_key_claro) ?? self::FALLBACK_KEY;
        $metaClaro = self::metaPorKey($keyClaro) ?? self::metaPorKey(self::FALLBACK_KEY);
        if ($metaClaro === null) {
            return null;
        }

        $keyOscuro = self::normalizarKey($departamento?->logo_key_oscuro)
            ?? self::siblingVariante($keyClaro, 'blanco')
            ?? $keyClaro;
        $metaOscuro = self::metaPorKey($keyOscuro) ?? $metaClaro;

        return [
            'key_claro' => $metaClaro['key'],
            'key_oscuro' => $metaOscuro['key'],
            'url_claro' => $metaClaro['url'],
            'url_oscuro' => $metaOscuro['url'],
            'alt' => $metaClaro['label'],
            'departamento' => $departamento?->nombre,
        ];
    }

    /**
     * Contrato compatible con vistas de recibo RH (siempre versión clara / negro).
     *
     * @return array{mostrar_aromas: bool, mostrar_bellaroma: bool, logos: array<int, array{key: string, base64: string, w: int, h: int, alt: string}>}
     */
    public static function encabezadoRecibo(?string $logoKey): array
    {
        $key = self::normalizarKey($logoKey) ?? self::FALLBACK_KEY;
        // Nunca blanco en papel: forzar sibling negro si llega _blanco.
        if (str_ends_with($key, '_blanco')) {
            $key = self::siblingVariante($key, 'negro') ?? self::FALLBACK_KEY;
        }
        $logo = self::logoMetaBase64($key);
        $lower = Str::lower($key);

        return [
            'mostrar_aromas' => str_contains($lower, 'aromas'),
            'mostrar_bellaroma' => str_contains($lower, 'bellaroma'),
            'logos' => [$logo],
        ];
    }

    public static function normalizarKey(?string $key): ?string
    {
        $key = trim((string) $key);
        if ($key === '') {
            return null;
        }

        $keys = self::keysDisponibles();
        if (in_array($key, $keys, true)) {
            return $key;
        }

        return null;
    }

    private static function labelFromKey(string $key): string
    {
        return Str::of($key)->replace('_', ' ')->title()->toString();
    }

    /**
     * @return array{key: string, url: string, label: string, path: string}|null
     */
    private static function metaPorKey(string $key): ?array
    {
        foreach (self::disponibles() as $item) {
            if ($item['key'] === $key) {
                return $item;
            }
        }

        return null;
    }

    /** @return array{key: string, base64: string, w: int, h: int, alt: string} */
    private static function logoMetaBase64(string $key): array
    {
        $meta = self::metaPorKey($key) ?? self::metaPorKey(self::FALLBACK_KEY);
        if ($meta === null || ! is_file($meta['path'])) {
            return [
                'key' => $key,
                'base64' => '',
                'w' => 1,
                'h' => 1,
                'alt' => self::labelFromKey($key),
            ];
        }

        [$w, $h] = getimagesize($meta['path']) ?: [1, 1];

        return [
            'key' => $meta['key'],
            'base64' => base64_encode((string) file_get_contents($meta['path'])),
            'w' => (int) $w,
            'h' => (int) $h,
            'alt' => $meta['label'],
        ];
    }
}
