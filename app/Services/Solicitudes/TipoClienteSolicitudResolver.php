<?php

namespace App\Services\Solicitudes;

use App\Models\Cliente;

class TipoClienteSolicitudResolver
{
    /** ponytail: si ventas no elige tipo, se registra el de la ficha del cliente cuando exista. */
    public static function resolver(?int $tipoEnSolicitud, ?Cliente $cliente): ?int
    {
        if ($tipoEnSolicitud) {
            return $tipoEnSolicitud;
        }

        return $cliente?->catalogo_tipo_cliente_id;
    }

    public static function faltaTipoComercial(?int $tipoResuelto): bool
    {
        return $tipoResuelto === null || $tipoResuelto === 0;
    }
}
