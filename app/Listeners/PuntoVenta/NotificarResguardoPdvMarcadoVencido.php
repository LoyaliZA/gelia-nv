<?php

namespace App\Listeners\PuntoVenta;

use App\Events\PuntoVenta\ResguardoPdvMarcadoVencido;
use App\Services\PuntoVenta\Resguardos\NotificarResguardoPdvService;

class NotificarResguardoPdvMarcadoVencido
{
    public function __construct(
        private readonly NotificarResguardoPdvService $notificaciones,
    ) {}

    public function handle(ResguardoPdvMarcadoVencido $event): void
    {
        $claveBase = (string) ($event->evento->idempotency_key ?? '');
        if ($claveBase === '') {
            $claveBase = 'vencido:'.$event->evento->id;
        }

        $this->notificaciones->vencido(
            $event->resguardo,
            $event->sucursalId,
            $claveBase,
        );

        $this->notificaciones->escalamientoVencido(
            $event->resguardo,
            $event->sucursalId,
            $claveBase.':escalamiento',
        );
    }
}
