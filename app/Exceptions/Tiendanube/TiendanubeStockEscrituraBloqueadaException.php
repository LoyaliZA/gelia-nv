<?php

namespace App\Exceptions\Tiendanube;

use RuntimeException;

class TiendanubeStockEscrituraBloqueadaException extends RuntimeException
{
    public static function mig03(): self
    {
        return new self('No se envía variant.stock plano. Indica location_id para el nivel de inventario.');
    }
}
