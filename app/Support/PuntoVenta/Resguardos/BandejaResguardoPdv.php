<?php

namespace App\Support\PuntoVenta\Resguardos;

final class BandejaResguardoPdv
{
    public const POR_RECIBIR = 'por_recibir';

    public const EN_CUSTODIA = 'en_custodia';

    public const INCIDENCIAS = 'incidencias';

    public const PASO_GERENTE = 'gerente';

    public const PASO_RECEPCIONISTA = 'recepcionista';

    /**
     * @return list<string>
     */
    public static function pasosPorRecibir(): array
    {
        return [
            self::PASO_GERENTE,
            self::PASO_RECEPCIONISTA,
        ];
    }

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
