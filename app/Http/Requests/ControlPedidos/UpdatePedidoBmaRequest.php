<?php

namespace App\Http\Requests\ControlPedidos;

class UpdatePedidoBmaRequest extends PedidoBmaRequestBase
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }
        // Autoguardado / borrador: quien puede crear también puede actualizar su borrador.
        return $user->can('control_pedidos.editar') || $user->can('control_pedidos.crear');
    }

    public function rules(): array
    {
        return array_merge($this->reglasComunes(), [
            'documentos_eliminar' => ['nullable', 'array'],
            'documentos_eliminar.*' => ['integer', 'exists:pedido_bma_documentos,id'],
        ]);
    }
}
