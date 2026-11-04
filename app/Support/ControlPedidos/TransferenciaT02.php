<?php

namespace App\Support\ControlPedidos;

use App\Models\ControlPedidos\PedidoBma;

/** Transferencia documental ordinaria. Mismo día y crédito conservan su proceso. */
final class TransferenciaT02
{
    public static function aplica(?PedidoBma $pedido): bool
    {
        $json = $pedido?->solicitud_preparacion_json ?? [];

        return ($json['modalidad_entrega'] ?? null) === 'tienda'
            && ($json['cobertura_tienda']['tipo'] ?? null) === 'transferencia'
            && ($json['atencion_tienda'] ?? 'no') === 'no'
            && ! $pedido->prioridad_md;
    }
}
