<?php

namespace App\Http\Requests\ControlPedidos;

use Illuminate\Foundation\Http\FormRequest;

class RechazarPedidoBmaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('control_pedidos.auditar') ?? false;
    }

    public function rules(): array
    {
        return [
            'motivo' => ['required', 'string', 'min:5', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'motivo.required' => 'Debe indicar el motivo del rechazo.',
            'motivo.min' => 'El motivo debe tener al menos 5 caracteres.',
        ];
    }
}
