<?php

namespace App\Http\Requests\Tiendanube;

use Illuminate\Foundation\Http\FormRequest;

class StoreTiendanubePrecioCostoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('tiendanube.precios.editar') ?? false;
    }

    public function rules(): array
    {
        return [
            'variante_id' => ['required', 'integer', 'min:1'],
            'valor' => ['required', 'string', 'max:24'],
            'moneda' => ['required', 'string', 'size:3'],
            'motivo' => ['nullable', 'string', 'max:255'],
            'tipo' => ['nullable', 'in:costo_local,lista_referencia'],
            'lista_id' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
