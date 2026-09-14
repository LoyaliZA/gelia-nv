<?php

namespace App\Exceptions\Tiendanube;

use RuntimeException;

class TiendanubePrecioFuenteException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $codigo = 'error',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
