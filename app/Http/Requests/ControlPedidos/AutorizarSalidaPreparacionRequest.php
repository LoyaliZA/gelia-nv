<?php

namespace App\Http\Requests\ControlPedidos;

use Illuminate\Foundation\Http\FormRequest;

class AutorizarSalidaPreparacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('control_pedidos.salida.autorizar') ?? false;
    }

    public function rules(): array
    {
        return [
            'version' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
