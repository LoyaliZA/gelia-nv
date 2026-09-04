<?php

namespace App\Listeners\PuntoVenta;

use App\Events\PuntoVenta\AtencionCerrada;
use App\Models\User;
use App\Services\PuntoVenta\Operacion\CompletarCierreJornadaTrasAtencionPdvService;

class CompletarCierreJornadaTrasAtencionCerrada
{
    public function __construct(
        private readonly CompletarCierreJornadaTrasAtencionPdvService $completarCierre,
    ) {}

    public function handle(AtencionCerrada $event): void
    {
        $actor = User::query()->find($event->atencion->user_id);
        if (! $actor instanceof User) {
            return;
        }

        $ahora = $event->atencion->fin_at ?? $event->evento->ocurrido_at ?? now();

        $this->completarCierre->ejecutar($actor, $event->sucursalId, $ahora);
    }
}
