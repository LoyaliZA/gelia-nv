<?php

namespace App\Http\Requests\Tiendanube;

use Illuminate\Foundation\Http\FormRequest;

class RestaurarTiendanubePrecioHistorialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('tiendanube.precios.reglas.ver') ?? false;
    }

    public function rules(): array
    {
        return [
            'filas' => ['nullable', 'array', 'max:500'],
            'filas.*.variante_id' => ['required_with:filas', 'integer', 'min:1'],
            'filas.*.campos' => ['nullable', 'array'],
            'filas.*.campos.*' => ['string', 'in:normal,promocional,costo_remoto'],
            'motivo' => ['nullable', 'string', 'max:240'],
            'aceptar_conflicto' => ['nullable', 'boolean'],
        ];
    }
}
