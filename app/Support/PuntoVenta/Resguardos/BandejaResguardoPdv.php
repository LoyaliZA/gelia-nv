<?php

namespace App\Support\PuntoVenta\Resguardos;

final class BandejaResguardoPdv
{
    public const POR_RECIBIR = 'por_recibir';

    public const EN_CUSTODIA = 'en_custodia';

    public const INCIDENCIAS = 'incidencias';

    /**
     * @return list<string>
     */
    public static function valores(): array
    {
        return [
            self::POR_RECIBIR,
            self::EN_CUSTODIA,
            self::INCIDENCIAS,
        ];
    }
}
