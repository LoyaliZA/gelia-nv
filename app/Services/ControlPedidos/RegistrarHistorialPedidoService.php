<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBmaHistorialEstado;

class RegistrarHistorialPedidoService
{
    public function ejecutar(
        int $pedidoId,
        int $usuarioId,
        ?int $estatusAnteriorId,
        int $estatusNuevoId,
        ?string $comentarios = null
    ): PedidoBmaHistorialEstado {
        return PedidoBmaHistorialEstado::create([
            'pedido_bma_id' => $pedidoId,
            'usuario_id' => $usuarioId,
            'estatus_anterior_id' => $estatusAnteriorId,
            'estatus_nuevo_id' => $estatusNuevoId,
            'comentarios' => $comentarios,
        ]);
    }

    public function registrarCreacion(int $pedidoId, int $usuarioId, int $estatusId): PedidoBmaHistorialEstado
    {
        return $this->ejecutar($pedidoId, $usuarioId, null, $estatusId, 'Pedido creado.');
    }

    public function registrarTransicion(
        int $pedidoId,
        int $usuarioId,
        CatalogoEstatusPedido $anterior,
        CatalogoEstatusPedido $nuevo,
        ?string $comentarios = null
    ): PedidoBmaHistorialEstado {
        return $this->ejecutar($pedidoId, $usuarioId, $anterior->id, $nuevo->id, $comentarios);
    }
}
