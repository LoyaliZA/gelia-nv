<?php

namespace App\Listeners\PuntoVenta;

use App\Events\PuntoVenta\AtencionCerrada;
use App\Jobs\PuntoVenta\Turnos\EjecutarMatchmakerTurnosPdvJob;
use App\Models\PuntoVenta\TurnoPdvEvento;

class DespacharMatchmakerTrasAtencionCerrada
{
    public function handle(AtencionCerrada $event): void
    {
        EjecutarMatchmakerTurnosPdvJob::dispatch(
            $event->sucursalId,
            TurnoPdvEvento::TIPO_ATENCION_CERRADA,
        );
    }
}
