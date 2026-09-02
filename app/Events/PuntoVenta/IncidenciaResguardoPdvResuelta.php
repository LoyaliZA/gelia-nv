<?php

namespace App\Events\PuntoVenta;

use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvEvento;
use App\Models\PuntoVenta\ResguardoPdvIncidencia;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class IncidenciaResguardoPdvResuelta implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public ResguardoPdv $resguardo,
        public ResguardoPdvIncidencia $incidencia,
        public ResguardoPdvEvento $evento,
        public int $sucursalId,
        public int $actorId,
    ) {}
}
