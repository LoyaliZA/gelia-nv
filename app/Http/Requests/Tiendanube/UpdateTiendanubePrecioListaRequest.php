<?php

namespace App\Http\Requests\Tiendanube;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTiendanubePrecioListaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('tiendanube.precios.editar') ?? false;
    }

    public function rules(): array
    {
        return [
            'nombre' => ['sometimes', 'required', 'string', 'max:120'],
            'archivar' => ['sometimes', 'boolean'],
        ];
    }
}
