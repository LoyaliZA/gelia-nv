<?php

namespace App\Http\Requests\ControlPedidos;

use Illuminate\Foundation\Http\FormRequest;

class AnexarPagoEnvioPedidoBmaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user
            && ($user->can('control_pedidos.crear') || $user->can('control_pedidos.auditar'));
    }

    public function rules(): array
    {
        return [
            'monto' => ['required', 'numeric', 'gt:0'],
            'catalogo_banco_id' => ['required', 'exists:catalogo_bancos,id'],
            'comentarios' => ['nullable', 'string', 'max:2000'],
            'comprobante' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
        ];
    }
}
