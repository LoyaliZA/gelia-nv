<?php

namespace App\Http\Requests\ControlPedidos;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmarEntregaRecogeHoyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('control_pedidos.salida.confirmar_entrega') ?? false;
    }

    public function rules(): array
    {
        return [
            'receptor' => ['required', 'string', 'max:160'],
            'version' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
