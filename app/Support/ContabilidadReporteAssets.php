<?php

namespace App\Support;

use Illuminate\Support\Collection;

class ContabilidadReporteAssets
{
    private const PALETA = ['#4f46e5', '#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#06b6d4', '#8b5cf6', '#14b8a6'];

    /**
     * Ingresos (venta bruta) agrupados por plataforma de pago para gráficas en PDF.
     *
     * @return array{
     *     labels: list<string>,
     *     values: list<float>,
     *     total: float,
     *     porcentajes: list<float>,
     *     colores: list<string>
     * }|null
     */
    public static function datosGraficaIngresosPlataforma(
        Collection $pedidos,
        ?int $limite = null,
        string $orden = 'desc'
    ): ?array {
        $agrupado = $pedidos
            ->groupBy(fn ($pedido) => $pedido->plataformaPago?->nombre ?? 'Sin plataforma')
            ->map(fn ($grupo) => round((float) $grupo->sum('venta_total'), 2))
            ->filter(fn ($monto) => $monto > 0);

        if ($agrupado->isEmpty()) {
            return null;
        }

        $totalGeneral = (float) $agrupado->sum();
        $ordenado = $orden === 'asc' ? $agrupado->sort() : $agrupado->sortDesc();

        if ($limite !== null) {
            $ordenado = $ordenado->take($limite);
        }

        $values = $ordenado->values()->all();

        return [
            'labels' => $ordenado->keys()
                ->map(fn ($nombre) => mb_strimwidth((string) $nombre, 0, 28, '…'))
                ->values()
                ->all(),
            'values' => $values,
            'total' => $totalGeneral,
            'porcentajes' => array_map(
                fn (float $valor) => $totalGeneral > 0 ? round(($valor / $totalGeneral) * 100, 1) : 0.0,
                $values
            ),
            'colores' => array_map(
                fn (int $i) => self::PALETA[$i % count(self::PALETA)],
                array_keys($values)
            ),
        ];
    }

    /**
     * Genera una gráfica de dona en PNG (base64) compatible con DomPDF.
     *
     * @param  array{labels: list<string>, values: list<float>, total: float, colores?: list<string>}  $grafica
     * @return array{base64: string, segmentos: list<array{label: string, valor: float, pct: float, color: string}>}|null
     */
    public static function generarDonaPlataformasPng(array $grafica, int $tamano = 180): ?array
    {
        if (empty($grafica['values']) || ($grafica['total'] ?? 0) <= 0) {
            return null;
        }

        $img = imagecreatetruecolor($tamano, $tamano);
        if ($img === false) {
            return null;
        }

        $blanco = imagecolorallocate($img, 255, 255, 255);
        imagefill($img, 0, 0, $blanco);

        $centro = (int) round($tamano / 2);
        $diametroExterior = $tamano - 6;
        $diametroInterior = (int) round($diametroExterior * 0.58);
        $anguloInicio = -90.0;
        $segmentos = [];

        foreach ($grafica['values'] as $i => $valor) {
            $valor = (float) $valor;
            $total = (float) $grafica['total'];
            $pct = $total > 0 ? round(($valor / $total) * 100, 1) : 0.0;
            $angulo = ($valor / $total) * 360;
            $colorHex = $grafica['colores'][$i] ?? self::PALETA[$i % count(self::PALETA)];
            [$r, $g, $b] = self::hexARgb($colorHex);
            $color = imagecolorallocate($img, $r, $g, $b);

            if ($angulo > 0) {
                imagefilledarc(
                    $img,
                    $centro,
                    $centro,
                    $diametroExterior,
                    $diametroExterior,
                    (int) round($anguloInicio),
                    (int) round($anguloInicio + $angulo),
                    $color,
                    IMG_ARC_PIE
                );
            }

            $segmentos[] = [
                'label' => $grafica['labels'][$i] ?? '',
                'valor' => $valor,
                'pct' => $pct,
                'color' => $colorHex,
            ];

            $anguloInicio += $angulo;
        }

        imagefilledellipse($img, $centro, $centro, $diametroInterior, $diametroInterior, $blanco);

        ob_start();
        imagepng($img);
        $png = ob_get_clean();
        imagedestroy($img);

        if ($png === false || $png === '') {
            return null;
        }

        return [
            'base64' => base64_encode($png),
            'segmentos' => $segmentos,
        ];
    }

    /** @return array{0: int, 1: int, 2: int} */
    private static function hexARgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }
}
