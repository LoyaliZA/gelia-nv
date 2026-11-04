<?php

namespace App\Support\ControlPedidos;

use App\Models\ControlPedidos\PedidoBma;

/** T-01 documental: cobro y remisión en PDV, seguimiento del conjunto en GELIA. */
final class ApartadoT01
{
    public static function aplica(?PedidoBma $pedido): bool
    {
        $json = $pedido?->solicitud_preparacion_json ?? [];
        return ($json['modalidad_entrega'] ?? null) === 'tienda'
            && ($json['area_revision'] ?? null) === 'preparacion_tienda'
            && in_array($json['cobertura_tienda']['tipo'] ?? '', ['', 'apartado'], true)
            && in_array($json['codigo_modalidad'] ?? '', ['', 'RECOGE_TIENDA'], true);
    }
}
