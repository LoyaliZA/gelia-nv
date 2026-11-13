<?php

namespace App\Listeners;

use App\Events\PuntoVenta\EntregaResguardoPdvCompletada;
use App\Services\ControlPedidos\InformarEntregaResguardoPdvService;
use Illuminate\Contracts\Queue\ShouldQueue;

class InformarEntregaResguardoControlPedidos implements ShouldQueue
{
    public function __construct(
        private readonly InformarEntregaResguardoPdvService $integracion,
    ) {}

    public function handle(EntregaResguardoPdvCompletada $event): void
    {
        $this->integracion->ejecutar(
            $event->resguardo,
            $event->entrega,
            $event->actorId
        );
    }
}
