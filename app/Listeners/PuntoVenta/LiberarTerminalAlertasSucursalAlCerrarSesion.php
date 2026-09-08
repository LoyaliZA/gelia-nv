<?php

namespace App\Listeners\PuntoVenta;

use App\Services\PuntoVenta\Alertas\LiberarTerminalAlertasSucursalPdvService;
use Illuminate\Auth\Events\Logout;

class LiberarTerminalAlertasSucursalAlCerrarSesion
{
    public function __construct(
        private readonly LiberarTerminalAlertasSucursalPdvService $liberar,
    ) {}

    public function handle(Logout $event): void
    {
        $user = $event->user;
        if ($user === null) {
            return;
        }

        $this->liberar->liberarPorCierreSesion($user, now());
    }
}
