<?php

namespace App\Services\Solicitudes;

use App\Models\CatalogoTipoCliente;
use App\Models\Cliente;

class TipoClienteSolicitudResolver
{
    public static function resolver(?int $tipoEnSolicitud, ?Cliente $cliente): ?int
    {
        $idReactivado = CatalogoTipoCliente::idDe('REACTIVADO');
        $idActivo = CatalogoTipoCliente::idDe('ACTIVO');

        if ($cliente?->es_inactivo && $idReactivado) {
            return $idReactivado;
        }

        if ($tipoEnSolicitud) {
            if ($idReactivado && $tipoEnSolicitud === $idReactivado && $idActivo) {
                return $idActivo;
            }

            return $tipoEnSolicitud;
        }

        $ficha = $cliente?->catalogo_tipo_cliente_id ? (int) $cliente->catalogo_tipo_cliente_id : null;
        if ($ficha && $idReactivado && $ficha === $idReactivado && $idActivo) {
            return $idActivo;
        }

        return $ficha;
    }

    public static function faltaTipoComercial(?int $tipoResuelto): bool
    {
        return $tipoResuelto === null || $tipoResuelto === 0;
    }
}
