<?php

namespace App\Http\Requests\Rh;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTipoFaltaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('rh.catalogos.tipos_faltas');
    }

    public function rules(): array
    {
        $tipoFalta = $this->route('tipoFalta');

        return [
            'nombre' => [
                'required',
                'string',
                'max:255',
                Rule::unique('catalogo_tipos_faltas', 'nombre')->ignore($tipoFalta?->id),
            ],
            'factor_penalizacion_puntualidad' => 'required|numeric|min:0|max:999.99',
            'factor_penalizacion_productividad' => 'required|numeric|min:0|max:999.99',
            'aplica_deduccion_salario_base' => 'nullable|boolean',
            'activo' => 'nullable|boolean',
        ];
    }
}
