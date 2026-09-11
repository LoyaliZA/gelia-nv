<?php

namespace App\Exceptions\Tiendanube;

use RuntimeException;

class TiendanubeStockPayloadException extends RuntimeException
{
    public function __construct(
        public readonly string $codigo,
        string $message,
    ) {
        parent::__construct($message);
    }
}
