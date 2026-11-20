<?php

namespace App\Support\ControlPedidos\Nacional;

use App\Models\ControlPedidos\PedidoBma;

/** Alta nacional 2.0. Un histórico sin esta marca no se migra al leerlo ni al guardarlo. */
final class CapturaNacional
{
    public const RELACION_PIEZA_SOLICITADA = 'pieza_solicitada_nacional';

    public static function es(PedidoBma $pedido): bool
    {
        $json = $pedido->solicitud_preparacion_json;

        return is_array($json)
            && ($json['modalidad_entrega'] ?? null) === 'nacional'
            && ($json['captura_nacional'] ?? null) === 'compartida';
    }
    public static function piezasVigentes(PedidoBma $pedido): array
    {
        if (! self::es($pedido)) {
            return [];
        }

        return $pedido->solicitud_preparacion_json['piezas_nacionales_vigentes']
            ?? $pedido->solicitud_preparacion_json['piezas_nacionales']
            ?? [];
    }
}
