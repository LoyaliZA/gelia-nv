<?php

namespace App\Listeners\PuntoVenta;

use App\Events\PuntoVenta\RecepcionFisicaPdvCompletada;
use App\Services\PuntoVenta\Resguardos\NotificarResguardoPdvService;

class NotificarRecepcionFisicaResguardoPdv
{
    public function __construct(
        private readonly NotificarResguardoPdvService $notificaciones,
    ) {}

    public function handle(RecepcionFisicaPdvCompletada $event): void
    {
        $clave = (string) ($event->evento->idempotency_key ?? '');
        if ($clave === '') {
            $clave = 'recepcion_fisica:'.$event->evento->id;
        }

        $this->notificaciones->recepcionFisica(
            $event->resguardo,
            $event->sucursalId,
            $clave,
        );
    }
}
