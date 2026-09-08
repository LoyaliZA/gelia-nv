<?php

namespace App\Support\PuntoVenta\Resguardos;

use App\Models\ControlPedidos\PedidoBma;

final class CantidadBultosEsperadaResguardoPdv
{
    public static function desdePedido(PedidoBma $pedido): int
    {
        $pedido->loadMissing('cajas');

        $activas = $pedido->cajas
            ->filter(fn ($caja) => method_exists($caja, 'estaActiva') ? $caja->estaActiva() : true)
            ->count();

        if ($activas > 0) {
            return $activas;
        }

        $numero = (int) ($pedido->numero_cajas ?? 0);
        if ($numero > 0) {
            return $numero;
        }

        // ponytail: pedidos a sucursal sin envíos explícitos se reciben como un bulto operativo.
        if ($pedido->requiereSucursalDestino()) {
            return 1;
        }

        return 0;
    }
}
