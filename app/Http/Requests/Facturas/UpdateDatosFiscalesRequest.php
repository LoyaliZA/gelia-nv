<?php

namespace App\Http\Requests\Facturas;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDatosFiscalesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('facturas.gestionar_datos_fiscales');
    }

    public function rules(): array
    {
        return [
            'rfc' => ['nullable', 'string', 'max:13'],
            'codigo_postal' => ['nullable', 'regex:/^\d{5}$/'],
            'regimen_fiscal' => ['nullable', 'string', 'max:255'],
            'correo_electronico' => ['nullable', 'email', 'max:255'],
            'uso_factura' => ['nullable', 'string', 'max:255'],
            'nombre_razon_social' => ['nullable', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:20'],
        ];
    }
}
