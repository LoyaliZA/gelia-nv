<?php

namespace App\Http\Requests\ControlPedidos;

use Illuminate\Foundation\Http\FormRequest;

class ReportarIncidenciaEmpaqueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('control_pedidos.cedis') ?? false;
    }

    public function rules(): array
    {
        return [
            'detalle' => ['required', 'string', 'min:5', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'detalle.required' => 'Debe describir el detalle encontrado.',
            'detalle.min' => 'El detalle debe tener al menos 5 caracteres.',
        ];
    }
}
