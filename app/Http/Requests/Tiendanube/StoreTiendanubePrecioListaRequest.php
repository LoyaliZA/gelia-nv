<?php

namespace App\Http\Requests\Tiendanube;

use Illuminate\Foundation\Http\FormRequest;

class StoreTiendanubePrecioListaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('tiendanube.precios.editar') ?? false;
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:120'],
        ];
    }
}
