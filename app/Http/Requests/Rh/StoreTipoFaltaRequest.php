<?php

namespace App\Http\Requests\Rh;

use Illuminate\Foundation\Http\FormRequest;

class StoreTipoFaltaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('rh.catalogos.tipos_faltas');
    }

    public function rules(): array
    {
        return [
            'nombre' => 'required|string|max:255|unique:catalogo_tipos_faltas,nombre',
            'factor_penalizacion_puntualidad' => 'required|numeric|min:0|max:999.99',
            'factor_penalizacion_productividad' => 'required|numeric|min:0|max:999.99',
            'aplica_deduccion_salario_base' => 'nullable|boolean',
            'activo' => 'nullable|boolean',
        ];
    }
}
