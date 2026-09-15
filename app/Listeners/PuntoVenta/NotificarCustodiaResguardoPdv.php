<?php

namespace App\Listeners\PuntoVenta;

use App\Events\PuntoVenta\CustodiaResguardoPdvConfirmada;
use App\Services\PuntoVenta\Resguardos\NotificarResguardoPdvService;

class NotificarCustodiaResguardoPdv
{
    public function __construct(
        private readonly NotificarResguardoPdvService $notificaciones,
    ) {}

    public function handle(CustodiaResguardoPdvConfirmada $event): void
    {
        $clave = (string) ($event->evento->idempotency_key ?? '');
        if ($clave === '') {
            $clave = 'custodia:'.$event->evento->id;
        }

        $this->notificaciones->custodiaConfirmada(
            $event->resguardo,
            $event->sucursalId,
            $clave,
        );
    }
}
