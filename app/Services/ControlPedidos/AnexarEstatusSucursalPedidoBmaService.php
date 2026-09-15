<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBma;
use App\Support\ControlPedidos\EstatusSucursalPedidoBma;
use App\Support\PuntoVenta\Resguardos\EstadoResguardoPdv;

class AnexarEstatusSucursalPedidoBmaService
{
    public function ejecutar(PedidoBma $pedido): PedidoBma
    {
        $codigo = $pedido->estatus_sucursal;
        $pedido->setAttribute('estatus_sucursal_etiqueta', EstatusSucursalPedidoBma::etiqueta($codigo));

        $resguardo = $pedido->relationLoaded('resguardoPdv')
            ? $pedido->resguardoPdv
            : null;

        if ($resguardo) {
            $esperada = EstadoResguardoPdv::cantidadEsperada($resguardo);
            $pedido->setAttribute('estatus_sucursal_progreso', [
                'gerente_recibidos' => EstadoResguardoPdv::cantidadRecibidaGerente($resguardo),
                'gerente_esperados' => $esperada,
                'custodia_confirmados' => EstadoResguardoPdv::cantidadEnCustodia($resguardo),
                'custodia_esperados' => $esperada,
            ]);
        }

        return $pedido;
    }
}
