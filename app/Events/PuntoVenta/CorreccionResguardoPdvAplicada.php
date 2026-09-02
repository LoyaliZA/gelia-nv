<?php

namespace App\Events\PuntoVenta;

use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvEvento;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CorreccionResguardoPdvAplicada implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public ResguardoPdv $resguardo,
        public ResguardoPdvEvento $evento,
        public int $actorId,
        public int $sucursalId,
    ) {}
}
