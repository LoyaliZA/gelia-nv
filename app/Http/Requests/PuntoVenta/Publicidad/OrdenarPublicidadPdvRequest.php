<?php

namespace App\Http\Requests\PuntoVenta\Publicidad;

use Illuminate\Foundation\Http\FormRequest;

class OrdenarPublicidadPdvRequest extends FormRequest
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
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'min:1'],
        ];
    }
}
