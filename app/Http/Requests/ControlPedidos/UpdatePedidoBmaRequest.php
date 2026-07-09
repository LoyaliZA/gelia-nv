<?php

namespace App\Http\Requests\ControlPedidos;

class UpdatePedidoBmaRequest extends PedidoBmaRequestBase
{
    public function authorize(): bool
    {
        return $this->user()?->can('control_pedidos.editar') ?? false;
    }

    public function rules(): array
    {
        return array_merge($this->reglasComunes(), [
            'documentos_eliminar' => ['nullable', 'array'],
            'documentos_eliminar.*' => ['integer', 'exists:pedido_bma_documentos,id'],
        ]);
    }
}
