<?php

namespace App\Support;

class RhManualAssets
{
    /**
     * Logos de marcas para la portada del manual RH.
     *
     * @return array{aromas: array{base64: string, alt: string}, bellaroma: array{base64: string, alt: string}}
     */
    public static function logosPortada(): array
    {
        return [
            'aromas' => self::cargarLogo('Images/Logos/aromas_logo_negro.png', 'Aromas Exclusivos'),
            'bellaroma' => self::cargarLogo('Images/Logos/bellaroma_logo_negro.png', 'Bellaroma'),
        ];
    }

    /** @return array{base64: string, alt: string} */
    private static function cargarLogo(string $rutaRelativa, string $alt): array
    {
        $path = public_path($rutaRelativa);

        if (!is_file($path)) {
            return ['base64' => '', 'alt' => $alt];
        }

        return [
            'base64' => base64_encode((string) file_get_contents($path)),
            'alt' => $alt,
        ];
    }
}
