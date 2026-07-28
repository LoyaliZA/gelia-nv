<?php

namespace App\Http\Requests\ControlPedidos;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ConsolidarPedidosEmpaqueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::any(['control_pedidos.auditar', 'control_pedidos.cedis']);
    }

    public function rules(): array
    {
        return [
            'pedido_ids' => ['required', 'array', 'min:2'],
            'pedido_ids.*' => ['integer', 'distinct', 'exists:pedidos_bma,id'],
            'principal_id' => ['nullable', 'integer', 'exists:pedidos_bma,id'],
            'piezas' => ['nullable', 'array'],
            'piezas.*' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
