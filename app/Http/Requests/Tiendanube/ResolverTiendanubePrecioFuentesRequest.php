<?php

namespace App\Http\Requests\Tiendanube;

use Illuminate\Foundation\Http\FormRequest;

class ResolverTiendanubePrecioFuentesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('tiendanube.precios.ver') ?? false;
    }

    public function rules(): array
    {
        return [
            'variante_ids' => ['required', 'array', 'min:1', 'max:500'],
            'variante_ids.*' => ['integer', 'min:1'],
            'tipos' => ['nullable', 'array'],
            'tipos.*' => ['string', 'max:40'],
        ];
    }
}
