<?php

namespace App\Http\Requests\Tiendanube;

use Illuminate\Foundation\Http\FormRequest;

class ConciliarTiendanubePrecioHistorialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('tiendanube.precios.reglas.ver') ?? false;
    }

    public function rules(): array
    {
        return [
            'evidencia_tipo' => ['nullable', 'in:lectura_api,export_declarado,manual_declarado'],
            'variante_ids' => ['nullable', 'array', 'max:500'],
            'variante_ids.*' => ['integer', 'min:1'],
            'artefacto_id' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
