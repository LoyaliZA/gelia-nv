<?php

namespace App\Support\Tiendanube\Precios;

enum TiendanubePrecioCampoCondicion: string
{
    case CostoLocal = 'costo_local';
    case ListaReferencia = 'lista_referencia';
    case PrecioNormalActual = 'precio_normal_actual';
    case PrecioPromocionalActual = 'precio_promocional_actual';
    case CostoRemotoActual = 'costo_remoto_actual';

    public function esCosto(): bool
    {
        return $this === self::CostoLocal || $this === self::CostoRemotoActual;
    }
}
