<?php

namespace App\Support\ControlPedidos;

final class AlmacenPdv
{
    public const CODIGO = 'PDV';

    public static function esCodigo(?string $codigo): bool
    {
        return strtoupper(trim((string) $codigo)) === self::CODIGO;
    }
}
