<?php

namespace App\Events\PuntoVenta;

use App\Models\PuntoVenta\JornadaPdv;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class JornadaCerrada implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public JornadaPdv $jornada,
        public int $sucursalId,
        public int $actorId,
        public string $alcance,
    ) {}
}
