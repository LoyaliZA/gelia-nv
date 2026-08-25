<?php

namespace App\Http\Requests\ControlPedidos;

use Illuminate\Foundation\Http\FormRequest;

class LiberarMercanciaPreparacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->can('control_pedidos.tienda.liberar') ?? false)
            || ($this->user()?->can('control_pedidos.cedis.liberar') ?? false);
    }

    public function rules(): array
    {
        return [
            'motivo' => ['nullable', 'string', 'max:2000'],
            'version' => ['nullable', 'integer', 'min:1'],
            'cantidad_liberada' => ['nullable', 'integer', 'min:0'],
            'incidencia' => ['nullable', 'string', 'max:2000'],
            'confirmacion' => ['required', 'accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'confirmacion.accepted' => 'Debe confirmar: «Ya devolví estas piezas a disponibilidad».',
        ];
    }
}
