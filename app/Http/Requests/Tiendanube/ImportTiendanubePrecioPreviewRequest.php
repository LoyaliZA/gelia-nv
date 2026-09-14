<?php

namespace App\Http\Requests\Tiendanube;

use Illuminate\Foundation\Http\FormRequest;

class ImportTiendanubePrecioPreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('tiendanube.precios.importar') ?? false;
    }

    public function rules(): array
    {
        return [
            'archivo' => ['required', 'file', 'max:2048'],
            'delimiter' => ['nullable', 'string', 'max:8'],
            'decimal_sep' => ['nullable', 'string', 'size:1'],
            'identificador' => ['required', 'in:sku,variante_id'],
            'columna_identificador' => ['required', 'string', 'max:120'],
            'columnas_importes' => ['required', 'array', 'min:1'],
            'columnas_importes.*' => ['required', 'string', 'max:120'],
            'columna_moneda' => ['nullable', 'string', 'max:120'],
            'moneda_fija' => ['nullable', 'string', 'size:3'],
        ];
    }
}
