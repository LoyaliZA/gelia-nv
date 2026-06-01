<?php

namespace App\Http\Requests\Rh;

use Illuminate\Foundation\Http\FormRequest;

class StorePuestoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('rh.catalogos.puestos');
    }

    public function rules(): array
    {
        return [
            'nombre' => 'required|string|max:255|unique:catalogo_puestos,nombre',
            'activo' => 'nullable|boolean',
            'bono_ids' => 'nullable|array',
            'bono_ids.*' => 'exists:catalogo_bonos,id',
        ];
    }
}
