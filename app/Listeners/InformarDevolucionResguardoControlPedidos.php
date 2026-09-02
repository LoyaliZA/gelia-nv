<?php

namespace App\Listeners;

use App\Events\PuntoVenta\DevolucionResguardoPdvConfirmada;
use App\Services\ControlPedidos\InformarDevolucionResguardoPdvService;

class InformarDevolucionResguardoControlPedidos
{
    public function __construct(
        private readonly InformarDevolucionResguardoPdvService $integracion,
    ) {}

    public function handle(DevolucionResguardoPdvConfirmada $event): void
    {
        $this->integracion->ejecutar(
            $event->resguardo,
            $event->evento,
            $event->actorId
        );
    }
}
