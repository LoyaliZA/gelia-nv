<?php

namespace App\Listeners\PuntoVenta;

use App\Events\PuntoVenta\IncidenciaResguardoPdvRegistrada;
use App\Services\PuntoVenta\Resguardos\NotificarResguardoPdvService;

class NotificarIncidenciaResguardoPdv
{
    public function __construct(
        private readonly NotificarResguardoPdvService $notificaciones,
    ) {}

    public function handle(IncidenciaResguardoPdvRegistrada $event): void
    {
        $clave = (string) ($event->evento->idempotency_key ?? '');
        if ($clave === '') {
            $clave = 'incidencia:'.$event->incidencia->id;
        }

        $this->notificaciones->incidencia(
            $event->resguardo,
            $event->incidencia,
            $event->sucursalId,
            $clave,
        );
    }
}
