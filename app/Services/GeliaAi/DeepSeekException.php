<?php

namespace App\Services\GeliaAi;

use RuntimeException;

/**
 * Falla del proveedor con mensaje apto para mostrar a la persona usuaria.
 */
class DeepSeekException extends RuntimeException
{
    public function __construct(string $message, public readonly int $httpStatus = 502, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
