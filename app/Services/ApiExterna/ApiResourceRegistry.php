<?php

namespace App\Services\ApiExterna;

use App\Contracts\ApiExterna\ApiResourceHandler;
use InvalidArgumentException;

class ApiResourceRegistry
{
    /** @var array<string, ApiResourceHandler> */
    protected array $handlers = [];

    public function register(ApiResourceHandler $handler): void
    {
        $this->handlers[$handler->slug()] = $handler;
    }

    public function get(string $slug): ApiResourceHandler
    {
        if (!isset($this->handlers[$slug])) {
            throw new InvalidArgumentException("Recurso API no registrado: {$slug}");
        }

        return $this->handlers[$slug];
    }

    /** @return array<string, ApiResourceHandler> */
    public function all(): array
    {
        return $this->handlers;
    }
}
