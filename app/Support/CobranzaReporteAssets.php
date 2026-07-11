<?php

namespace App\Support;

use Illuminate\Support\Collection;

class CobranzaReporteAssets
{
    /**
     * Logos de empresa en base64 para incrustar en PDF (DomPDF no carga URLs remotas).
     *
     * @return array{aromas: array{base64: string, alt: string}, bellaroma: array{base64: string, alt: string}}
     */
    public static function logosEmpresa(): array
    {
        return [
            'aromas' => self::cargarLogo('Images/Logos/aromas_logo_negro.png', 'Aromas Exclusivos'),
            'bellaroma' => self::cargarLogo('Images/Logos/bellaroma_logo_negro.png', 'Bellaroma'),
        ];
    }

    /**
     * Top N clientes por deuda para gráfica SVG inline.
     *
     * @return array{labels: list<string>, values: list<float>, total: float}|null
     */
    public static function datosGraficaDeuda(Collection $facturas, int $limite = 5): ?array
    {
        $agrupado = $facturas
            ->groupBy(fn ($f) => $f->cliente?->nombre ?? 'Sin cliente')
            ->map(fn ($grupo) => (float) $grupo->sum('monto'))
            ->sortDesc()
            ->take($limite);

        if ($agrupado->isEmpty()) {
            return null;
        }

        return [
            'labels' => $agrupado->keys()->map(fn ($nombre) => mb_strimwidth((string) $nombre, 0, 28, '…'))->values()->all(),
            'values' => $agrupado->values()->all(),
            'total' => (float) $agrupado->sum(),
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
