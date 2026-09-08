<?php

namespace App\Http\Requests\PuntoVenta\Pantallas;

use Illuminate\Foundation\Http\FormRequest;

class GestionarEnlacePantallaSalaPdvRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sucursal_id' => ['required', 'integer', 'min:1'],
            'regenerar' => ['sometimes', 'boolean'],
        ];
    }
}
