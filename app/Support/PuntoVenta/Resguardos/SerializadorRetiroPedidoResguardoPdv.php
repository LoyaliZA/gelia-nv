<?php

namespace App\Support\PuntoVenta\Resguardos;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\PuntoVenta\ResguardoPdv;

final class SerializadorRetiroPedidoResguardoPdv
{
    /**
     * @return array{
     *     envia_a_otra_persona: bool,
     *     envia_otra_persona: string|null,
     *     etiqueta_retiro: string
     * }
     */
    public static function desdeResguardo(ResguardoPdv $resguardo): array
    {
        $snapshot = is_array($resguardo->snapshot_json) ? $resguardo->snapshot_json : [];
        $enviaTercero = (bool) ($snapshot['envia_a_otra_persona'] ?? false);
        $nombreTercero = isset($snapshot['envia_otra_persona'])
            ? trim((string) $snapshot['envia_otra_persona'])
            : null;

        if ($resguardo->relationLoaded('pedido') || $resguardo->pedido_bma_id) {
            $resguardo->loadMissing('pedido');
            $pedido = $resguardo->pedido;
            if ($pedido instanceof PedidoBma) {
                $enviaTercero = (bool) $pedido->envia_a_otra_persona;
                $nombreTercero = $enviaTercero
                    ? trim((string) ($pedido->envia_otra_persona ?: ''))
                    : null;
            }
        }

        if ($nombreTercero === '') {
            $nombreTercero = null;
        }

        return [
            'envia_a_otra_persona' => $enviaTercero,
            'envia_otra_persona' => $nombreTercero,
            'etiqueta_retiro' => $enviaTercero && $nombreTercero
                ? "Recoge tercero autorizado: {$nombreTercero}"
                : 'Retira el titular del pedido',
        ];
    }
}
