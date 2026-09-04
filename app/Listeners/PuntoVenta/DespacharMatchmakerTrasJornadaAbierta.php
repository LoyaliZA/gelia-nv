<?php

namespace App\Listeners\PuntoVenta;

use App\Events\PuntoVenta\JornadaAbierta;
use App\Jobs\PuntoVenta\Turnos\EjecutarMatchmakerTurnosPdvJob;

class DespacharMatchmakerTrasJornadaAbierta
{
    public function handle(JornadaAbierta $event): void
    {
        EjecutarMatchmakerTurnosPdvJob::dispatch(
            $event->sucursalId,
            'jornada.abierta',
        );
    }
}
