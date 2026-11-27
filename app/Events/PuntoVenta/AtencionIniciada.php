<?php

namespace App\Events\PuntoVenta;

use App\Models\PuntoVenta\TurnoPdv;
use App\Models\PuntoVenta\TurnoPdvAtencion;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AtencionIniciada implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public TurnoPdv $turno,
        public TurnoPdvAtencion $atencion,
        public int $sucursalId,
    ) {}
}
