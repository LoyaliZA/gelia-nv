<?php

namespace App\Http\Requests\ControlPedidos;

use Illuminate\Foundation\Http\FormRequest;

class RechazarPagosPedidoBmaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user
            && ($user->can('control_pedidos.pagos.rechazar') || $user->can('control_pedidos.auditar'));
    }

    public function rules(): array
    {
        return [
            'pago_ids' => ['required', 'array', 'min:1'],
            'pago_ids.*' => ['integer', 'distinct', 'exists:pedido_bma_pagos,id'],
            'motivo' => ['required', 'string', 'min:5', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'pago_ids.required' => 'Seleccione al menos una exhibición.',
            'motivo.required' => 'Indique el motivo del rechazo.',
            'motivo.min' => 'El motivo debe tener al menos 5 caracteres.',
        ];
    }
}
