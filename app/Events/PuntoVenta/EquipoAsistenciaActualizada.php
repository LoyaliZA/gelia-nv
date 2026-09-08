<?php

namespace App\Events\PuntoVenta;

use App\Models\PuntoVenta\EquipoAsistenciaDiaPdv;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EquipoAsistenciaActualizada implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public EquipoAsistenciaDiaPdv $asistencia,
        public int $sucursalId,
        public int $actorId,
    ) {}
}
