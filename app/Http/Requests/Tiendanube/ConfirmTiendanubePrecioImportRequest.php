<?php

namespace App\Http\Requests\Tiendanube;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmTiendanubePrecioImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('tiendanube.precios.importar') ?? false;
    }

    public function rules(): array
    {
        return [
            'item_ids' => ['required', 'array', 'min:1'],
            'item_ids.*' => ['integer', 'min:1'],
        ];
    }
}
