<?php

namespace App\Events\PuntoVenta;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class PublicidadPdvActualizada implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public int $sucursalContextoId,
        public ?int $sucursalPublicidadId,
        public ?int $publicidadId,
    ) {}

    public function esGlobal(): bool
    {
        return $this->sucursalPublicidadId === null;
    }
}
