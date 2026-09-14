<?php

namespace App\Services\Tiendanube\Precios;

final class TiendanubePrecioImporte
{
    public const ESCALA = 2;

    public const ENTERO_MAX_DIGITOS = 10;

    /**
     * @return array{ok: bool, valor: ?string, error: ?string}
     */
    public static function parse(string $raw, string $separadorDecimal = '.'): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return ['ok' => true, 'valor' => null, 'error' => null];
        }

        if (str_starts_with($raw, '-') || str_starts_with($raw, '+')) {
            if (str_starts_with($raw, '-')) {
                return ['ok' => false, 'valor' => null, 'error' => 'negativo'];
            }
            $raw = substr($raw, 1);
        }

        if (preg_match('/[eE]/', $raw) === 1) {
            return ['ok' => false, 'valor' => null, 'error' => 'numero_ambiguo'];
        }

        $sep = $separadorDecimal === ',' ? ',' : '.';
        $otro = $sep === ',' ? '.' : ',';
        if (str_contains($raw, '.') && str_contains($raw, ',')) {
            return ['ok' => false, 'valor' => null, 'error' => 'numero_ambiguo'];
        }
        if (str_contains($raw, $otro)) {
            return ['ok' => false, 'valor' => null, 'error' => 'numero_ambiguo'];
        }

        $quoted = preg_quote($sep, '/');
        if (preg_match('/^\d{1,3}('.$quoted.')\d{3}$/', $raw) === 1) {
            return ['ok' => false, 'valor' => null, 'error' => 'numero_ambiguo'];
        }

        $normalizado = str_replace($sep, '.', $raw);
        if (preg_match('/^\d+(\.\d+)?$/', $normalizado) !== 1) {
            return ['ok' => false, 'valor' => null, 'error' => 'numero_invalido'];
        }

        $parts = explode('.', $normalizado, 2);
        $frac = $parts[1] ?? '';
        if (strlen($frac) > self::ESCALA) {
            return ['ok' => false, 'valor' => null, 'error' => 'escala'];
        }

        $entero = ltrim($parts[0], '0');
        if ($entero === '') {
            $entero = '0';
        }
        if (strlen($entero) > self::ENTERO_MAX_DIGITOS) {
            return ['ok' => false, 'valor' => null, 'error' => 'rango'];
        }

        return [
            'ok' => true,
            'valor' => $entero.'.'.str_pad($frac, self::ESCALA, '0'),
            'error' => null,
        ];
    }

    public static function formatStored(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $raw = trim((string) $value);
        $parsed = self::parse(str_replace(',', '.', $raw), '.');

        return $parsed['ok'] ? $parsed['valor'] : null;
    }
}
