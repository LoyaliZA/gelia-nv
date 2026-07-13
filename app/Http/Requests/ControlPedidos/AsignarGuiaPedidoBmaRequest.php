<?php

namespace App\Http\Requests\ControlPedidos;

use Illuminate\Foundation\Http\FormRequest;

class AsignarGuiaPedidoBmaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('control_pedidos.delegado') ?? false;
    }

    public function rules(): array
    {
        return [
            'numero_rastreo' => ['required', 'string', 'min:3', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'numero_rastreo.required' => 'Debes capturar el número de guía.',
            'numero_rastreo.min' => 'El número de guía debe tener al menos 3 caracteres.',
        ];
    }
}
