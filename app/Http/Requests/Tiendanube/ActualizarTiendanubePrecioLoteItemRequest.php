<?php

namespace App\Http\Requests\Tiendanube;

use Illuminate\Foundation\Http\FormRequest;

class ActualizarTiendanubePrecioLoteItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('tiendanube.precios.reglas.ver') ?? false;
    }

    public function rules(): array
    {
        return [
            'accion' => ['required', 'in:excluir,reincluir,ajustar'],
            'motivo' => ['nullable', 'string', 'max:255'],
            'destino' => ['required_if:accion,ajustar', 'nullable', 'string'],
            'valor' => ['required_if:accion,ajustar', 'nullable', 'string'],
        ];
    }
}
