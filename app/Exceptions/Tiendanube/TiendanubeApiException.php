<?php

namespace App\Exceptions\Tiendanube;

use RuntimeException;
use Throwable;

class TiendanubeApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $statusCode,
        public readonly string $resource,
        public readonly string $method,
        public readonly string $summary,
        public readonly ?string $responseBody = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode, $previous);
    }
}
