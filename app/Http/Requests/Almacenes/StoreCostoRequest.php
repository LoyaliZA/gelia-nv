<?php

namespace App\Http\Requests\Almacenes;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCostoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('almacenes.costos.gestionar');
    }

    public function rules(): array
    {
        return [
            'producto_id' => [
                'required',
                'exists:productos,id',
                Rule::unique('producto_costos', 'producto_id'),
            ],
            'costo' => 'nullable|numeric|min:0',
            'costo_reposicion' => 'nullable|numeric|min:0',
        ];
    }
}
