<?php

namespace App\Http\Requests\PuntoVenta\Publicidad;

use Illuminate\Foundation\Http\FormRequest;

class ActualizarVolumenPublicidadPdvRequest extends FormRequest
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
            'volumen' => ['required', 'integer', 'min:0', 'max:100'],
        ];
    }
}
