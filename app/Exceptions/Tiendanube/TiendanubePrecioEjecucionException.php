<?php

namespace App\Exceptions\Tiendanube;

use RuntimeException;

class TiendanubePrecioEjecucionException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $codigo = 'error',
        public readonly int $httpStatus = 422,
        public readonly array $errores = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $httpStatus, $previous);
    }
}
