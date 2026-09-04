<?php

namespace App\Events\PuntoVenta;

use App\Models\PuntoVenta\OperacionPdvEvento;
use App\Models\PuntoVenta\SucursalDiaOperacionPdv;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class JornadaCierreHorario implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public SucursalDiaOperacionPdv $sucursalDia,
        public OperacionPdvEvento $evento,
        public int $sucursalId,
    ) {}
}
