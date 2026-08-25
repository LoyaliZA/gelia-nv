<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBma;
use Illuminate\Validation\ValidationException;

final class AssertPedidoNoBloqueadoFase7
{
    public static function assert(PedidoBma $pedido): void
    {
        if ($pedido->tieneCancelacionOperativaActiva()) {
            throw ValidationException::withMessages([
                'cancelacion' => 'El pedido tiene una cancelación operativa activa y no puede avanzar.',
            ]);
        }

        if ($pedido->estaEsperandoPago()) {
            throw ValidationException::withMessages([
                'espera_pago' => 'El pedido está en espera de pago y no puede avanzar hasta registrar el pago o cancelar.',
            ]);
        }
    }
}
