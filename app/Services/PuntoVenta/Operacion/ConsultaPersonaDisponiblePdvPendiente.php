<?php

namespace App\Services\PuntoVenta\Operacion;

use App\Contracts\PuntoVenta\ConsultaPersonaDisponiblePdv;
use App\Models\User;

/**
 * Stub hasta la tarea 5B (disponibilidad operativa).
 */
class ConsultaPersonaDisponiblePdvPendiente implements ConsultaPersonaDisponiblePdv
{
    public function primeraDisponible(int $sucursalId, string $servicio): ?User
    {
        // ponytail: sin reglas ad hoc; 5B proveerá jornada, pausa y atención abierta.
        return null;
    }
}
