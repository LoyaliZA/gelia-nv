<?php

namespace App\Http\Requests\Tiendanube;

use Illuminate\Foundation\Http\FormRequest;

class ListarTiendanubePrecioHistorialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('tiendanube.precios.reglas.ver') ?? false;
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:120'],
            'variante_id' => ['nullable', 'integer', 'min:1'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date'],
            'canal' => ['nullable', 'in:api,csv,aprobado_sin_entrega'],
            'resultado' => ['nullable', 'string', 'max:48'],
            'actor_id' => ['nullable', 'integer', 'min:1'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'operacion' => ['nullable', 'uuid'],
        ];
    }
}
