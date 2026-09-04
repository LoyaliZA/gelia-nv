<?php

namespace App\Events\PuntoVenta;

use App\Models\PuntoVenta\TurnoPdv;
use App\Models\PuntoVenta\TurnoPdvEvento;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TurnoCreado implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public TurnoPdv $turno,
        public TurnoPdvEvento $evento,
        public int $sucursalId,
    ) {}
}
