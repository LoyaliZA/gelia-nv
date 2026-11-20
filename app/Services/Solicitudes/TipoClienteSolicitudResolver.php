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

            return self::sinNuevoEnClienteConHistorial($tipoEnSolicitud, $cliente);
        }

        $ficha = $cliente?->catalogo_tipo_cliente_id ? (int) $cliente->catalogo_tipo_cliente_id : null;
        if ($ficha && $idReactivado && $ficha === $idReactivado && $idActivo) {
            return $idActivo;
        }

        return $ficha ? self::sinNuevoEnClienteConHistorial($ficha, $cliente) : null;
    }

    /**
     * Tipo que puede escribirse en la ficha al autorizar. NUEVO nunca degrada
     * a un cliente activo que ya tiene historial comercial.
     */
    public static function paraFicha(int $tipoSolicitado, ?Cliente $cliente): int
    {
        return self::sinNuevoEnClienteConHistorial($tipoSolicitado, $cliente);
    }

    /**
     * NUEVO describe a un cliente sin historial. Un cliente activo con acumulado
     * oficial o con otro tipo en ficha ya no lo es: se registra como ACTIVO.
     */
    private static function sinNuevoEnClienteConHistorial(int $tipo, ?Cliente $cliente): int
    {
        $idNuevo = CatalogoTipoCliente::idDe('NUEVO');
        $idActivo = CatalogoTipoCliente::idDe('ACTIVO');
        if (! $cliente || ! $idNuevo || ! $idActivo || $tipo !== $idNuevo || $cliente->es_inactivo) {
            return $tipo;
        }

        $ficha = $cliente->catalogo_tipo_cliente_id ? (int) $cliente->catalogo_tipo_cliente_id : null;
        $conHistorial = bccomp((string) ($cliente->monto_venta_actual ?? '0'), '0', 2) > 0
            || ($ficha !== null && $ficha !== $idNuevo);

        return $conHistorial ? $idActivo : $tipo;
    }

    public static function faltaTipoComercial(?int $tipoResuelto): bool
    {
        return $tipoResuelto === null || $tipoResuelto === 0;
    }
}
