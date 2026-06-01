<?php

namespace App\Http\Requests\Rh;

use Illuminate\Foundation\Http\FormRequest;

class StoreBonoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('rh.catalogos.bonos');
    }

    public function rules(): array
    {
        return [
            'nombre' => 'required|string|max:255|unique:catalogo_bonos,nombre',
            'codigo' => 'nullable|string|max:100|unique:catalogo_bonos,codigo',
            'activo' => 'nullable|boolean',
        ];
    }
}
