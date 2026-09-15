<?php

namespace App\Services\PuntoVenta\Resguardos;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Support\ControlPedidos\EstatusSucursalPedidoBma;
use App\Support\PuntoVenta\Resguardos\EstadoResguardoPdv;

class SincronizarEstatusSucursalPedidoBmaService
{
    public function desdeResguardo(ResguardoPdv $resguardo): void
    {
        $pedidoId = (int) ($resguardo->pedido_bma_id ?? 0);
        if ($pedidoId < 1) {
            return;
        }

        $pedido = PedidoBma::query()->find($pedidoId);
        if (! $pedido instanceof PedidoBma || ! $pedido->requiereSucursalDestino()) {
            return;
        }

        $nuevo = $this->resolverCodigo($resguardo);
        if ($pedido->estatus_sucursal === $nuevo) {
            return;
        }

        $pedido->update(['estatus_sucursal' => $nuevo]);
    }

    public function marcarEnTransito(PedidoBma $pedido): void
    {
        if (! $pedido->requiereSucursalDestino()) {
            return;
        }

        $pedido->update(['estatus_sucursal' => EstatusSucursalPedidoBma::EN_TRANSITO]);
    }

    public function limpiarSiEntregado(PedidoBma $pedido): void
    {
        if ($pedido->estatus_sucursal === null) {
            return;
        }

        $pedido->update(['estatus_sucursal' => null]);
    }

    private function resolverCodigo(ResguardoPdv $resguardo): ?string
    {
        if ($resguardo->estado === ResguardoPdv::ESTADO_ENTREGADO
            || $resguardo->estado === ResguardoPdv::ESTADO_DEVUELTO) {
            return null;
        }

        $esperada = EstadoResguardoPdv::cantidadEsperada($resguardo);
        $recibidaGerente = EstadoResguardoPdv::cantidadRecibidaGerente($resguardo);
        $enCustodia = EstadoResguardoPdv::cantidadEnCustodia($resguardo);

        if ($recibidaGerente === 0) {
            return EstatusSucursalPedidoBma::EN_TRANSITO;
        }

        if ($recibidaGerente < $esperada) {
            return EstatusSucursalPedidoBma::RECEPCION_GERENTE_PARCIAL;
        }

        if ($enCustodia === 0) {
            return EstatusSucursalPedidoBma::PENDIENTE_CUSTODIA;
        }

        if ($enCustodia < $esperada) {
            return EstatusSucursalPedidoBma::CUSTODIA_PARCIAL;
        }

        return EstatusSucursalPedidoBma::EN_CUSTODIA;
    }
}
