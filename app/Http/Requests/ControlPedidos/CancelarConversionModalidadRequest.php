<?php

namespace App\Http\Requests\ControlPedidos;

use Illuminate\Foundation\Http\FormRequest;

class CancelarConversionModalidadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('control_pedidos.crear') ?? false;
    }

    public function rules(): array
    {
        return [
            'instancia_id' => ['required', 'uuid'],
            'revision_esperada' => ['required', 'integer', 'min:0'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'instancia_id.required' => 'Indique la instancia del cambio de modalidad.',
            'revision_esperada.required' => 'Indique la revisión esperada del pedido.',
        ];
    }
}
