<?php

namespace App\Support;

class EtiquetaActivoAssets
{
    public const TAMANOS_HOJA = [
        'a4' => [
            'label' => 'A4',
            'ancho_mm' => 210.0,
            'alto_mm' => 297.0,
            'dompdf' => 'a4',
        ],
        'carta' => [
            'label' => 'Carta (Letter)',
            'ancho_mm' => 215.9,
            'alto_mm' => 279.4,
            'dompdf' => 'letter',
        ],
        'oficio' => [
            'label' => 'Oficio (Legal)',
            'ancho_mm' => 215.9,
            'alto_mm' => 355.6,
            'dompdf' => 'legal',
        ],
    ];

    /** @var array<string, string>|null */
    private static ?array $logosCache = null;

    public static function tamanosParaFrontend(): array
    {
        return collect(self::TAMANOS_HOJA)
            ->map(fn (array $t, string $clave) => [
                'value' => $clave,
                'label' => $t['label'],
            ])
            ->values()
            ->all();
    }

    public static function resolverTamanioHoja(?string $clave): array
    {
        $normalizado = is_string($clave) ? strtolower(trim($clave)) : 'a4';

        return self::TAMANOS_HOJA[$normalizado] ?? self::TAMANOS_HOJA['a4'];
    }

    public static function dimensionesPagina(string $tamanioHoja, string $orientacionHoja): array
    {
        $base = self::resolverTamanioHoja($tamanioHoja);
        $portrait = $orientacionHoja === 'portrait';

        return [
            'tamanio_hoja' => array_key_exists(strtolower($tamanioHoja), self::TAMANOS_HOJA) ? strtolower($tamanioHoja) : 'a4',
            'dompdf_paper' => $base['dompdf'],
            'page_ancho_mm' => $portrait ? $base['ancho_mm'] : $base['alto_mm'],
            'page_alto_mm' => $portrait ? $base['alto_mm'] : $base['ancho_mm'],
        ];
    }

    /** @return array{aromas: string, bellaroma: string} */
    public static function logosBase64(): array
    {
        if (self::$logosCache !== null) {
            return self::$logosCache;
        }

        self::$logosCache = [
            'aromas' => base64_encode(file_get_contents(public_path('Images/Logos/aromas_logo_negro.png'))),
            'bellaroma' => base64_encode(file_get_contents(public_path('Images/Logos/bellaroma_logo_negro.png'))),
        ];

        return self::$logosCache;
    }

    /**
     * Metadatos de logos para DomPDF (requiere px explícitos, no mm ni width:auto).
     *
     * @return array{aromas: array{base64: string, w: int, h: int}, bellaroma: array{base64: string, w: int, h: int}}
     */
    public static function logosParaPdf(): array
    {
        $paths = [
            'aromas' => public_path('Images/Logos/aromas_logo_negro.png'),
            'bellaroma' => public_path('Images/Logos/bellaroma_logo_negro.png'),
        ];

        $base64 = self::logosBase64();
        $result = [];

        foreach ($paths as $key => $path) {
            [$w, $h] = getimagesize($path) ?: [1, 1];
            $result[$key] = [
                'base64' => $base64[$key],
                'w' => (int) $w,
                'h' => (int) $h,
            ];
        }

        return $result;
    }
}
