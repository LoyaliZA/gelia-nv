<?php

namespace App\Services\Tiendanube\Precios;

/**
 * Aritmética decimal con bcmath. Los importes se serializan como cadenas.
 * Escala interna 8; salida de precios 2 (half-up).
 */
final class TiendanubePrecioDecimal
{
    public const ESCALA_INTERNA = 8;

    public const ESCALA_SALIDA = 2;

    public const ESCALA_PARAMETRO = 4;

    public static function parseImporte(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $parsed = TiendanubePrecioImporte::parse($raw, '.');

        return $parsed['ok'] ? $parsed['valor'] : null;
    }

    /**
     * @return array{ok: bool, valor: ?string, error: ?string}
     */
    public static function parseParametro(?string $raw): array
    {
        if ($raw === null || trim($raw) === '') {
            return ['ok' => false, 'valor' => null, 'error' => 'parametro_ausente'];
        }

        $raw = trim($raw);
        if (str_starts_with($raw, '-')) {
            return ['ok' => false, 'valor' => null, 'error' => 'negativo'];
        }
        if (str_starts_with($raw, '+')) {
            $raw = substr($raw, 1);
        }
        if (preg_match('/[eE]/', $raw) === 1 || (str_contains($raw, '.') && str_contains($raw, ','))) {
            return ['ok' => false, 'valor' => null, 'error' => 'numero_ambiguo'];
        }

        $normalizado = str_replace(',', '.', $raw);
        if (preg_match('/^\d+(\.\d+)?$/', $normalizado) !== 1) {
            return ['ok' => false, 'valor' => null, 'error' => 'numero_invalido'];
        }

        $parts = explode('.', $normalizado, 2);
        $frac = $parts[1] ?? '';
        if (strlen($frac) > self::ESCALA_PARAMETRO) {
            return ['ok' => false, 'valor' => null, 'error' => 'escala'];
        }

        $entero = ltrim($parts[0], '0');
        if ($entero === '') {
            $entero = '0';
        }
        if (strlen($entero) > TiendanubePrecioImporte::ENTERO_MAX_DIGITOS) {
            return ['ok' => false, 'valor' => null, 'error' => 'rango'];
        }

        return [
            'ok' => true,
            'valor' => $entero.'.'.str_pad($frac, self::ESCALA_PARAMETRO, '0'),
            'error' => null,
        ];
    }

    public static function cmp(string $a, string $b): int
    {
        return bccomp($a, $b, self::ESCALA_INTERNA);
    }

    public static function esCero(string $valor): bool
    {
        return self::cmp($valor, '0') === 0;
    }

    public static function add(string $a, string $b): string
    {
        return bcadd($a, $b, self::ESCALA_INTERNA);
    }

    public static function sub(string $a, string $b): string
    {
        return bcsub($a, $b, self::ESCALA_INTERNA);
    }

    public static function mul(string $a, string $b): string
    {
        return bcmul($a, $b, self::ESCALA_INTERNA);
    }

    public static function div(string $a, string $b): ?string
    {
        if (self::esCero($b)) {
            return null;
        }

        return bcdiv($a, $b, self::ESCALA_INTERNA);
    }

    public static function aplicarPorcentaje(string $base, string $porcentaje, bool $aumentar): ?string
    {
        $fraccion = self::div($porcentaje, '100');
        if ($fraccion === null) {
            return null;
        }

        $factor = $aumentar
            ? self::add('1', $fraccion)
            : self::sub('1', $fraccion);

        if (! $aumentar && self::cmp($factor, '0') < 0) {
            return null;
        }

        return self::mul($base, $factor);
    }

    /**
     * Precio = costo / (1 − m/100). Exige 0 ≤ m < 100.
     */
    public static function margenObjetivo(string $costo, string $margen): ?string
    {
        if (self::cmp($margen, '0') < 0 || self::cmp($margen, '100') >= 0) {
            return null;
        }

        $denominador = self::sub('1', self::div($margen, '100') ?? '0');
        if (self::cmp($denominador, '0') <= 0) {
            return null;
        }

        return self::div($costo, $denominador);
    }

    public static function roundHalfUp(string $valor, int $escala = self::ESCALA_SALIDA): string
    {
        $negativo = self::cmp($valor, '0') < 0;
        $abs = $negativo ? self::mul($valor, '-1') : $valor;
        $half = bcdiv('5', bcpow('10', (string) ($escala + 1), 0), self::ESCALA_INTERNA);
        $ajustado = bcadd($abs, $half, self::ESCALA_INTERNA);
        $truncado = bcadd($ajustado, '0', $escala);

        if ($negativo && self::cmp($truncado, '0') !== 0) {
            return bcmul($truncado, '-1', $escala);
        }

        return $truncado;
    }

    public static function floor(string $valor): string
    {
        if (self::cmp($valor, '0') < 0) {
            $trunc = bcadd($valor, '0', 0);
            if (self::cmp($valor, $trunc) !== 0) {
                return bcsub($trunc, '1', 0);
            }

            return $trunc;
        }

        return bcadd($valor, '0', 0);
    }

    public static function ceil(string $valor): string
    {
        $floor = self::floor($valor);
        if (self::cmp($valor, $floor) === 0) {
            return $floor;
        }

        return bcadd($floor, '1', 0);
    }

    public static function serializar(string $valor): string
    {
        return self::format(self::roundHalfUp($valor, self::ESCALA_SALIDA), self::ESCALA_SALIDA);
    }

    public static function format(string $valor, int $escala = self::ESCALA_SALIDA): string
    {
        $normalizado = bcadd($valor, '0', $escala);
        if (! str_contains($normalizado, '.')) {
            return $normalizado.'.'.str_repeat('0', $escala);
        }

        [$entero, $frac] = explode('.', $normalizado, 2);

        return $entero.'.'.str_pad($frac, $escala, '0');
    }
}
