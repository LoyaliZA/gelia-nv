<?php

namespace App\Listeners\PuntoVenta;

use App\Events\PuntoVenta\TurnoCreado;
use App\Jobs\PuntoVenta\Turnos\EjecutarMatchmakerTurnosPdvJob;
use App\Models\PuntoVenta\TurnoPdvEvento;

class DespacharMatchmakerTrasTurnoCreado
{
    public function handle(TurnoCreado $event): void
    {
        EjecutarMatchmakerTurnosPdvJob::dispatch(
            $event->sucursalId,
            TurnoPdvEvento::TIPO_ALTA,
        );
    }
}
