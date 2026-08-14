<?php

namespace App\Http\Requests\ControlPedidos;

use Illuminate\Foundation\Http\FormRequest;

class ReportarSinExistenciaPedidoBmaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('control_pedidos.cedis') ?? false;
    }

    public function rules(): array
    {
        return [
            'descripcion_producto' => ['required', 'string', 'max:255'],
            'comentario' => ['required', 'string', 'max:2000'],
            'sku' => ['nullable', 'string', 'max:64'],
            'producto_id' => ['nullable', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'descripcion_producto.required' => 'Indique el producto sin existencias.',
            'comentario.required' => 'El comentario para Ventas es obligatorio.',
        ];
    }
}
