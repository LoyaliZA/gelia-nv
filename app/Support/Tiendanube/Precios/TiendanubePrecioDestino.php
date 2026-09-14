<?php

namespace App\Support\Tiendanube\Precios;

enum TiendanubePrecioDestino: string
{
    case Normal = 'normal';
    case Promocional = 'promocional';
    case CostoRemoto = 'costo_remoto';

    public function admiteEliminar(): bool
    {
        return $this === self::Promocional;
    }

    public function esVenta(): bool
    {
        return $this === self::Normal || $this === self::Promocional;
    }

    public function campoSnapshot(): TiendanubePrecioCampoCondicion
    {
        return match ($this) {
            self::Normal => TiendanubePrecioCampoCondicion::PrecioNormalActual,
            self::Promocional => TiendanubePrecioCampoCondicion::PrecioPromocionalActual,
            self::CostoRemoto => TiendanubePrecioCampoCondicion::CostoRemotoActual,
        };
    }
}
