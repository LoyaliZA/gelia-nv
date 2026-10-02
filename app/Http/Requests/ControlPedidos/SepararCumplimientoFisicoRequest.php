<?php

namespace App\Http\Requests\ControlPedidos;

use Illuminate\Foundation\Http\FormRequest;

class SepararCumplimientoFisicoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('control_pedidos.tienda.apartado.separar') ?? false;
    }

    public function rules(): array
    {
        return [
            'cantidad' => ['required', 'integer', 'min:1'],
            'ubicacion' => ['required', 'string', 'max:160'],
            'version' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
