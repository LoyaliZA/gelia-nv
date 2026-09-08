<?php

namespace App\Services\PuntoVenta\Resguardos;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Support\PuntoVenta\Resguardos\CantidadBultosEsperadaResguardoPdv;

class SincronizarCantidadBultosEsperadaResguardoPdvService
{
    public function ejecutar(ResguardoPdv $resguardo): ResguardoPdv
    {
        if ((int) $resguardo->cantidad_bultos_esperada > 0) {
            return $resguardo;
        }

        if (! in_array($resguardo->estado, [
            ResguardoPdv::ESTADO_PENDIENTE_RECEPCION,
            ResguardoPdv::ESTADO_EN_CUSTODIA,
        ], true)) {
            return $resguardo;
        }

        $pedido = $resguardo->relationLoaded('pedido')
            ? $resguardo->pedido
            : PedidoBma::query()->with('cajas')->find($resguardo->pedido_bma_id);

        if (! $pedido instanceof PedidoBma) {
            return $resguardo;
        }

        $cantidad = CantidadBultosEsperadaResguardoPdv::desdePedido($pedido);
        if ($cantidad < 1) {
            return $resguardo;
        }

        if ((int) $resguardo->cantidad_bultos_esperada === $cantidad) {
            return $resguardo;
        }

        $resguardo->update(['cantidad_bultos_esperada' => $cantidad]);

        return $resguardo->fresh();
    }
}
