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
        return $this->reglasComunes();
    }
}
