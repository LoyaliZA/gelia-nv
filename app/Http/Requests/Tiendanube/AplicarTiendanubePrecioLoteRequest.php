<?php

namespace App\Http\Requests\Tiendanube;

use Illuminate\Foundation\Http\FormRequest;

class AplicarTiendanubePrecioLoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('tiendanube.precios.aplicar') ?? false;
    }

    public function rules(): array
    {
        return [
            'checksum' => ['required', 'string', 'size:64'],
        ];
    }
}
