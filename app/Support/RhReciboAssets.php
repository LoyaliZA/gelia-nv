<?php

namespace App\Support;

use Illuminate\Support\Str;

class RhReciboAssets
{
    private const LOGO_PATHS = [
        'aromas_negro' => 'Images/Logos/aromas_logo_negro.png',
        'aromas_blanco' => 'Images/Logos/aromas_logo_blanco.png',
        'bellaroma_negro' => 'Images/Logos/bellaroma_logo_negro.png',
        'bellaroma_blanco' => 'Images/Logos/bellaroma_logo_blanco.png',
    ];

    /**
     * @return array{mostrar_aromas: bool, mostrar_bellaroma: bool, logos: array<int, array{key: string, base64: string, w: int, h: int, alt: string}>}
     */
    public static function encabezadoParaDepartamento(?string $departamentoNombre, string $variante = 'negro'): array
    {
        $depto = Str::lower(trim($departamentoNombre ?? ''));

        // Un solo logo según departamento (nunca ambos a la vez).
        $marca = match (true) {
            $depto === 'bellaroma' || str_contains($depto, 'bellaroma') => 'bellaroma',
            in_array($depto, ['aromas', 'cedis', 'ti'], true)
                || str_contains($depto, 'aromas')
                || str_contains($depto, 'cedis') => 'aromas',
            default => 'aromas',
        };

        $suffix = $variante === 'blanco' ? 'blanco' : 'negro';

        return [
            'mostrar_aromas' => $marca === 'aromas',
            'mostrar_bellaroma' => $marca === 'bellaroma',
            'logos' => [self::logoMeta($marca, $suffix)],
        ];
    }

    /** @return array{key: string, base64: string, w: int, h: int, alt: string} */
    private static function logoMeta(string $marca, string $suffix): array
    {
        $key = "{$marca}_{$suffix}";
        $path = public_path(self::LOGO_PATHS[$key] ?? self::LOGO_PATHS["{$marca}_negro"]);

        if (!is_file($path)) {
            return [
                'key' => $key,
                'base64' => '',
                'w' => 1,
                'h' => 1,
                'alt' => ucfirst($marca),
            ];
        }

        [$w, $h] = getimagesize($path) ?: [1, 1];

        return [
            'key' => $key,
            'base64' => base64_encode((string) file_get_contents($path)),
            'w' => (int) $w,
            'h' => (int) $h,
            'alt' => $marca === 'aromas' ? 'Aromas Exclusivos' : 'Bellaroma',
        ];
    }
}
