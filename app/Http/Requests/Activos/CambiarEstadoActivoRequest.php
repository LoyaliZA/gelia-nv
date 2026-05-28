<?php

namespace App\Http\Requests\Activos;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CambiarEstadoActivoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('activos.cambiar_estado');
    }

    public function rules(): array
    {
        return [
            'estado' => ['required', Rule::in(['disponible', 'mantenimiento', 'baja'])],
            'motivo' => 'nullable|string|max:255',
            'notas' => 'nullable|string|max:1000',
        ];
    }
}
