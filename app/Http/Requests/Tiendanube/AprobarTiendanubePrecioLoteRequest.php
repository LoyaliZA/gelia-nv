<?php

namespace App\Http\Requests\Tiendanube;

use Illuminate\Foundation\Http\FormRequest;

class AprobarTiendanubePrecioLoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('tiendanube.precios.aprobar') ?? false;
    }

    public function rules(): array
    {
        return [
            'checksum' => ['required', 'string', 'size:64'],
        ];
    }
}
