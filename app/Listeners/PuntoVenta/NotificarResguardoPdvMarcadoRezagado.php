<?php

namespace App\Listeners\PuntoVenta;

use App\Events\PuntoVenta\ResguardoPdvMarcadoRezagado;
use App\Services\PuntoVenta\Resguardos\NotificarResguardoPdvService;

class NotificarResguardoPdvMarcadoRezagado
{
    public function __construct(
        private readonly NotificarResguardoPdvService $notificaciones,
    ) {}

    public function handle(ResguardoPdvMarcadoRezagado $event): void
    {
        $claveBase = (string) ($event->evento->idempotency_key ?? '');
        if ($claveBase === '') {
            $claveBase = 'rezagado:'.$event->evento->id;
        }

        $this->notificaciones->escalamientoRezago(
            $event->resguardo,
            $event->sucursalId,
            $claveBase.':escalamiento',
        );
    }
}
