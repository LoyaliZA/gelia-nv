<?php

namespace App\Http\Requests\Tiendanube;

use Illuminate\Foundation\Http\FormRequest;

class ValidarTiendanubePrecioCsvPerfilRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('tiendanube.configurar') ?? false;
    }

    public function rules(): array
    {
        return [
            'plantilla' => ['required', 'file', 'max:2048'],
        ];
    }
}
