<?php

namespace App\Listeners\PuntoVenta;

use App\Events\PuntoVenta\ResguardoPdvProximoAVencer;
use App\Services\PuntoVenta\Resguardos\NotificarResguardoPdvService;

class NotificarResguardoPdvProximoAVencer
{
    public function __construct(
        private readonly NotificarResguardoPdvService $notificaciones,
    ) {}

    public function handle(ResguardoPdvProximoAVencer $event): void
    {
        $this->notificaciones->proximoAVencer(
            $event->resguardo,
            $event->sucursalId,
            $event->idempotencyKey,
        );
    }
}
