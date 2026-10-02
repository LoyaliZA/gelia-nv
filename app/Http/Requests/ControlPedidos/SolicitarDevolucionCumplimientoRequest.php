<?php

namespace App\Http\Requests\ControlPedidos;

use Illuminate\Foundation\Http\FormRequest;

class SolicitarDevolucionCumplimientoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('control_pedidos.tienda.apartado.separar') ?? false;
    }

    public function rules(): array
    {
        return [
            'motivo' => ['required', 'string', 'max:500'],
            'version' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
