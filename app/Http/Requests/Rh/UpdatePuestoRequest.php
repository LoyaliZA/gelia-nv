<?php

namespace App\Http\Requests\Rh;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePuestoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('rh.catalogos.puestos');
    }

    public function rules(): array
    {
        $puesto = $this->route('puesto');

        return [
            'nombre' => [
                'required',
                'string',
                'max:255',
                Rule::unique('catalogo_puestos', 'nombre')->ignore($puesto?->id),
            ],
            'activo' => 'nullable|boolean',
            'bono_ids' => 'nullable|array',
            'bono_ids.*' => 'exists:catalogo_bonos,id',
        ];
    }
}
