<?php

namespace App\Listeners\PuntoVenta;

use App\Events\PuntoVenta\PausaFinalizada;
use App\Jobs\PuntoVenta\Turnos\EjecutarMatchmakerTurnosPdvJob;

class DespacharMatchmakerTrasPausaFinalizada
{
    public function handle(PausaFinalizada $event): void
    {
        EjecutarMatchmakerTurnosPdvJob::dispatch(
            $event->sucursalId,
            'pausa.finalizada',
        );
    }
}
