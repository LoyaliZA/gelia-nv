<?php

namespace App\Support\PuntoVenta\Resguardos;

final class AntiguedadOperativaResguardoPdv
{
    public const REZAGADO = 'rezagado';

    public const PROXIMO_A_VENCER = 'proximo_a_vencer';

    public const VENCIDO = 'vencido';

    /**
     * @return list<string>
     */
    public static function valores(): array
    {
        return [
            self::REZAGADO,
            self::PROXIMO_A_VENCER,
            self::VENCIDO,
        ];
    }
}
