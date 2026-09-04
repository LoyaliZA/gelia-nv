<?php

namespace App\Events\PuntoVenta;

use App\Models\PuntoVenta\SucursalDiaOperacionPdv;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class JornadaAmpliada implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public SucursalDiaOperacionPdv $sucursalDia,
        public int $sucursalId,
        public int $actorId,
    ) {}
}
