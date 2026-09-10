<?php

namespace App\Exceptions\Tiendanube;

use App\Models\Tiendanube\TiendanubeProducto;
use RuntimeException;
use Throwable;

class TiendanubeActualizacionParcialException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?TiendanubeProducto $producto,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }
}
