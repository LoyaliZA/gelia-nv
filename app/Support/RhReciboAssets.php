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

        $mostrarAromas = in_array($depto, ['aromas', 'cedis', 'ti'], true);
        $mostrarBellaroma = in_array($depto, ['bellaroma', 'ti'], true);

        if (!$mostrarAromas && !$mostrarBellaroma) {
            $mostrarAromas = true;
        }

        $suffix = $variante === 'blanco' ? 'blanco' : 'negro';
        $logos = [];

        if ($mostrarAromas) {
            $logos[] = self::logoMeta('aromas', $suffix);
        }
        if ($mostrarBellaroma) {
            $logos[] = self::logoMeta('bellaroma', $suffix);
        }

        return [
            'mostrar_aromas' => $mostrarAromas,
            'mostrar_bellaroma' => $mostrarBellaroma,
            'logos' => $logos,
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
