<?php

namespace App\Http\Requests\ControlPedidos;

use Illuminate\Foundation\Http\FormRequest;

class ReservaConversionModalidadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('control_pedidos.crear') ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('instancia_id') && $this->query('instancia_id')) {
            $this->merge(['instancia_id' => $this->query('instancia_id')]);
        }
    }

    public function rules(): array
    {
        return [
            'instancia_id' => ['required', 'uuid'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'instancia_id.required' => 'Indique la instancia del cambio de modalidad.',
            'instancia_id.uuid' => 'La instancia del cambio de modalidad no es válida.',
        ];
    }
}
