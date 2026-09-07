<?php

namespace App\Support\PuntoVenta\Reportes;

final class PercentilesPdv
{
    /**
     * @param  list<int|float>  $duracionesSegundos
     * @return array{p50: int, p90: int, p95: int}|null
     */
    public static function calcular(array $duracionesSegundos, int $minimo = MetricaResguardoPdvIds::UMBRAL_PERCENTILES): ?array
    {
        $valores = array_values(array_map('floatval', $duracionesSegundos));
        $n = count($valores);

        if ($n < $minimo) {
            return null;
        }

        sort($valores, SORT_NUMERIC);

        return [
            'p50' => (int) round(self::interpolar($valores, 0.50)),
            'p90' => (int) round(self::interpolar($valores, 0.90)),
            'p95' => (int) round(self::interpolar($valores, 0.95)),
        ];
    }

    /**
     * @param  list<float>  $ordenados
     */
    private static function interpolar(array $ordenados, float $percentil): float
    {
        $n = count($ordenados);
        if ($n === 0) {
            return 0.0;
        }

        if ($n === 1) {
            return $ordenados[0];
        }

        $posicion = ($n - 1) * $percentil;
        $inferior = (int) floor($posicion);
        $superior = (int) ceil($posicion);

        if ($inferior === $superior) {
            return $ordenados[$inferior];
        }

        $fraccion = $posicion - $inferior;

        return $ordenados[$inferior] + ($fraccion * ($ordenados[$superior] - $ordenados[$inferior]));
    }
}
