<?php

namespace App\Http\Requests\ControlPedidos;

use Illuminate\Foundation\Http\FormRequest;

class ResponderPesajePedidoBmaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('control_pedidos.cedis') ?? false;
    }

    public function rules(): array
    {
        return [
            'peso_real_kg' => ['required', 'numeric', 'min:0'],
            'cajas' => ['required', 'array', 'min:1'],
            'cajas.*.catalogo_tipo_caja_id' => ['required', 'integer', 'exists:catalogo_tipos_caja_pedido,id'],
            'cajas.*.cantidad' => ['required', 'integer', 'min:1', 'max:999'],
        ];
    }

    public function messages(): array
    {
        return [
            'peso_real_kg.required' => 'El peso real es obligatorio.',
            'cajas.required' => 'Indique al menos una caja.',
            'cajas.min' => 'Indique al menos una caja.',
        ];
    }
}
