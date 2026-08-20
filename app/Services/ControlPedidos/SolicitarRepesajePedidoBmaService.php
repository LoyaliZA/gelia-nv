<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBma;

/**
 * Wrapper legacy: redirige al flujo no destructivo ActualizarConsulta.
 *
 * @deprecated Preferir ActualizarConsultaPedidoBmaService
 */
class SolicitarRepesajePedidoBmaService
{
    public function __construct(
        private ActualizarConsultaPedidoBmaService $actualizarConsulta,
    ) {}

    public function ejecutar(PedidoBma $pedido, int $usuarioId, string $motivo): PedidoBma
    {
        return $this->actualizarConsulta->ejecutar($pedido, $usuarioId, $motivo);
    }
}
