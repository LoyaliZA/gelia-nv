<?php

namespace App\Exceptions\Tiendanube;

use RuntimeException;

class TiendanubePrecioSeleccionException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $codigo = 'error',
        public readonly int $httpStatus = 422,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $httpStatus, $previous);
    }
}
