<?php

namespace App\Http\Requests\Rh;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBonoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('rh.catalogos.bonos');
    }

    public function rules(): array
    {
        $bono = $this->route('bono');

        return [
            'nombre' => 'required|string|max:255|unique:catalogo_bonos,nombre,' . $bono->id,
            'codigo' => 'nullable|string|max:100|unique:catalogo_bonos,codigo,' . $bono->id,
            'activo' => 'nullable|boolean',
        ];
    }
}
