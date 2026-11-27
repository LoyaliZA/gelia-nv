<?php

namespace App\Support\Clientes\Direcciones;

use App\Models\Cliente;
use App\Models\ClienteDireccion;
use App\Models\EnlaceDireccion;
use App\Models\SolicitudDireccion;

class ResolverAccionEnlaceDireccion
{
    public static function paraCliente(Cliente $cliente, ?string $accion): string
    {
        if (filled($accion)) {
            return $accion;
        }

        return self::segunDireccionesActivas((int) $cliente->id);
    }

    public static function desdeEnlace(EnlaceDireccion $enlace): string
    {
        if (filled($enlace->accion_permitida)) {
            return $enlace->accion_permitida;
        }

        return self::segunDireccionesActivas((int) $enlace->cliente_id);
    }

    private static function segunDireccionesActivas(int $clienteId): string
    {
        $tiene = ClienteDireccion::query()
            ->where('cliente_id', $clienteId)
            ->activas()
            ->exists();

        return $tiene
            ? SolicitudDireccion::ACCION_ADICIONAL
            : SolicitudDireccion::ACCION_PRIMERA;
    }
}
