<?php

namespace App\Events\PuntoVenta;

use App\Models\PuntoVenta\IntervaloOperativoPdv;
use App\Models\PuntoVenta\JornadaPdv;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PausaFinalizada implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public JornadaPdv $jornada,
        public IntervaloOperativoPdv $intervalo,
        public int $sucursalId,
        public int $actorId,
    ) {}
}
