<?php

namespace App\Support\ControlPedidos;

use App\Models\Cliente;

class FormatearDomicilioCliente
{
    public static function ejecutar(Cliente $cliente): ?string
    {
        $partes = array_filter([
            $cliente->direccion_contacto,
            $cliente->colonia_contacto,
            $cliente->municipio_contacto,
            $cliente->estado_contacto,
            $cliente->pais_contacto,
        ], fn ($v) => filled(trim((string) $v)));

        if (empty($partes)) {
            return null;
        }

        return implode(', ', $partes);
    }

    public static function codigoPostal(Cliente $cliente): ?string
    {
        $cp = trim((string) ($cliente->cp_contacto ?: $cliente->codigo_postal));

        return $cp !== '' ? $cp : null;
    }
}
