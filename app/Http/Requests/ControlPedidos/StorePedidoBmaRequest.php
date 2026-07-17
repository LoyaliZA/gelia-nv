<?php

namespace App\Http\Requests\ControlPedidos;

class StorePedidoBmaRequest extends PedidoBmaRequestBase
{
    public function authorize(): bool
    {
        return $this->user()?->can('control_pedidos.crear') ?? false;
    }

    public function rules(): array
    {
        return array_merge($this->reglasComunes(), [
            'pedido_id' => ['nullable', 'integer', 'exists:pedidos_bma,id'],
        ]);
    }
}
