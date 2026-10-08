<?php

namespace App\Support;

final class NormalizarFondoBase
{
    private const FONDO_SISTEMA = 'none';

    /** @var list<string> */
    private const HEX_FONDO_SISTEMA = [
        '#fff',
        '#ffffff',
        '#000',
        '#000000',
        '#0a0a0a',
    ];

    public static function aplicar(?string $valor): string
    {
        if ($valor === null || $valor === '' || $valor === self::FONDO_SISTEMA) {
            return self::FONDO_SISTEMA;
        }

        if (str_starts_with($valor, '#')) {
            $compact = strtolower(trim($valor));
            if (in_array($compact, self::HEX_FONDO_SISTEMA, true)) {
                return self::FONDO_SISTEMA;
            }
        }

        return $valor;
    }
}
