<?php

namespace App\Support\Solicitudes;

use App\Events\SolicitudTagActualizada;
use App\Models\SolicitudTag;

class EmitirSolicitudTagActualizada
{
    public static function desde(SolicitudTag $solicitud, string $accion, ?int $porUsuarioId = null): void
    {
        event(new SolicitudTagActualizada(
            solicitudId: (int) $solicitud->id,
            accion: $accion,
            porUsuarioId: $porUsuarioId,
            vendedorId: $solicitud->vendedor_id !== null ? (int) $solicitud->vendedor_id : null,
            departamentoId: $solicitud->departamento_id !== null ? (int) $solicitud->departamento_id : null,
        ));
    }
}
