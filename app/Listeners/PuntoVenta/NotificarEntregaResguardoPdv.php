<?php

namespace App\Listeners\PuntoVenta;

use App\Events\PuntoVenta\EntregaResguardoPdvCompletada;
use App\Services\PuntoVenta\Resguardos\NotificarResguardoPdvService;

class NotificarEntregaResguardoPdv
{
    public function __construct(
        private readonly NotificarResguardoPdvService $notificaciones,
    ) {}

    public function handle(EntregaResguardoPdvCompletada $event): void
    {
        $clave = (string) ($event->evento->idempotency_key ?? '');
        if ($clave === '') {
            $clave = 'entrega:'.$event->evento->id;
        }

        $this->notificaciones->entrega(
            $event->resguardo,
            $event->sucursalId,
            $clave,
        );
    }
}
