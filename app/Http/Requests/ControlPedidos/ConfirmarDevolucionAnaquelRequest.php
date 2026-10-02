<?php

namespace App\Http\Requests\ControlPedidos;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmarDevolucionAnaquelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('control_pedidos.tienda.apartado.confirmar_devolucion') ?? false;
    }

    public function rules(): array
    {
        return [
            'ubicacion' => ['required', 'string', 'max:160'],
            'foto' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'version' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
