<?php

namespace App\Http\Requests\ControlPedidos;

use Illuminate\Foundation\Http\FormRequest;

class MarcarEnviadoPedidoBmaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('control_pedidos.cedis.enviar') ?? false;
    }

    public function rules(): array
    {
        return [
            'cajas' => ['nullable', 'array'],
            'cajas.*.id' => ['required_with:cajas', 'integer'],
            'cajas.*.numero_rastreo' => ['nullable', 'string', 'min:3', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'cajas.*.id.required_with' => 'Cada envío seleccionado debe indicar su id.',
            'cajas.*.numero_rastreo.min' => 'El número de guía de la caja debe tener al menos 3 caracteres.',
        ];
    }
}
