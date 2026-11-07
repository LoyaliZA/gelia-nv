<?php

namespace App\Support\ControlPedidos;

use App\Models\ControlPedidos\PedidoBma;

/** Bolsas, recepción y entrega compartidas por transferencia y crédito de Tienda. */
final class CustodiaDocumentalTienda
{
    public static function credito(?PedidoBma $pedido): bool
    {
        $json = $pedido?->solicitud_preparacion_json ?? [];

        return ($json['modalidad_entrega'] ?? null) === 'tienda'
            && ($json['cobertura_tienda']['tipo'] ?? null) === 'credito';
    }

    public static function aplica(?PedidoBma $pedido): bool
    {
        return TransferenciaT02::documental($pedido) || self::credito($pedido);
    }
}
