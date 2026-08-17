<?php

namespace App\Http\Requests\ControlPedidos;

use Illuminate\Foundation\Http\FormRequest;

class ActualizarPlazosRetrasoPedidoBmaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('control_pedidos.configurar_plazos') ?? false;
    }

    public function rules(): array
    {
        return [
            'activo' => ['required', 'boolean'],
            'hora_corte' => ['required', 'string', 'regex:/^\d{1,2}:\d{2}$/'],
            'dias_habiles' => ['required', 'array', 'min:1'],
            'dias_habiles.*' => ['integer', 'between:1,7'],
            'temporada_alta' => ['required', 'boolean'],
            'dias_extra_temporada_alta' => ['required', 'integer', 'min:0', 'max:30'],
            'comercial' => ['required', 'array'],
            'comercial.dias_empaque' => ['required', 'integer', 'min:1', 'max:30'],
            'comercial.dias_recoleccion' => ['required', 'integer', 'min:1', 'max:30'],
            'local_regional' => ['required', 'array'],
            'local_regional.dias_empaque' => ['required', 'integer', 'min:1', 'max:30'],
            'local_regional.dias_recoleccion' => ['required', 'integer', 'min:1', 'max:30'],
        ];
    }
}
