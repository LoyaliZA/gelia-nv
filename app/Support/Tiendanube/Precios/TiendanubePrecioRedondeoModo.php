<?php

namespace App\Support\Tiendanube\Precios;

enum TiendanubePrecioRedondeoModo: string
{
    case DosDecimalesHalfUp = 'dos_decimales_half_up';
    case EnteroArriba = 'entero_arriba';
    case EnteroAbajo = 'entero_abajo';
    case EnteroCercano = 'entero_cercano';
    case Terminacion90 = 'terminacion_90';
    case Terminacion99 = 'terminacion_99';

    public function esTerminacion(): bool
    {
        return $this === self::Terminacion90 || $this === self::Terminacion99;
    }

    public function centavosTerminacion(): ?string
    {
        return match ($this) {
            self::Terminacion90 => '90',
            self::Terminacion99 => '99',
            default => null,
        };
    }
}
