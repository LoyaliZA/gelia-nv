<?php

namespace App\Support\ControlPedidos\Nacional;

use App\Models\ControlPedidos\PedidoBma;

/** Alta nacional 2.0. Un histórico sin esta marca no se migra al leerlo ni al guardarlo. */
final class CapturaNacional
{
    public static function es(PedidoBma $pedido): bool
    {
        $json = $pedido->solicitud_preparacion_json;

        return is_array($json)
            && ($json['modalidad_entrega'] ?? null) === 'nacional'
            && ($json['captura_nacional'] ?? null) === 'compartida';
    }
}
