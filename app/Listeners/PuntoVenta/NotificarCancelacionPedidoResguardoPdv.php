<?php

namespace App\Listeners\PuntoVenta;

use App\Events\PuntoVenta\CancelacionPedidoResguardoPdvRecibida;
use App\Services\PuntoVenta\Resguardos\NotificarResguardoPdvService;
use App\Services\PuntoVenta\Resguardos\RecibirCancelacionPedidoResguardoPdvService;

class NotificarCancelacionPedidoResguardoPdv
{
    public function __construct(
        private readonly NotificarResguardoPdvService $notificaciones,
    ) {}

    public function handle(CancelacionPedidoResguardoPdvRecibida $event): void
    {
        $clave = (string) ($event->evento->idempotency_key ?? '');
        if ($clave === '') {
            $clave = RecibirCancelacionPedidoResguardoPdvService::claveIdempotencia(
                $event->pedidoBmaId,
                (int) $event->resguardo->id,
            );
        }

        $this->notificaciones->cancelacionRecibida(
            $event->resguardo,
            $event->sucursalId,
            $clave,
        );
    }
}
