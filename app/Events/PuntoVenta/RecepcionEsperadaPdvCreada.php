<?php

namespace App\Events\PuntoVenta;

use App\Models\PuntoVenta\ResguardoPdv;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RecepcionEsperadaPdvCreada implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public ResguardoPdv $resguardo,
        public int $pedidoBmaId,
        public int $sucursalId,
    ) {}
}
