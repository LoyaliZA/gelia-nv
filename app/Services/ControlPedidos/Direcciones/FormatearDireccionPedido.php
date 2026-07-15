<?php

namespace App\Services\ControlPedidos\Direcciones;

use App\Models\ControlPedidos\PedidoBmaDireccion;
use App\Support\Clientes\FormatearDireccionEstructurada;

class FormatearDireccionPedido
{
    public static function desdeSnapshot(PedidoBmaDireccion $snapshot): ?string
    {
        $estructurado = FormatearDireccionEstructurada::ejecutar($snapshot->toArray());

        if ($estructurado) {
            return $estructurado;
        }

        $legacy = trim((string) $snapshot->domicilio_legacy);

        return $legacy !== '' ? $legacy : null;
    }
}
