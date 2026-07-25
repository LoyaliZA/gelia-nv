<?php

namespace App\Http\Requests\Facturas;

use App\Models\EnlaceDatosFiscales;
use Illuminate\Foundation\Http\FormRequest;

class StoreDatosFiscalesPublicosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'min:6', 'max:64'],
            'rfc' => ['nullable', 'string', 'max:20'],
            'codigo_postal' => ['nullable', 'string', 'max:10'],
            'regimen_fiscal' => ['nullable', 'string', 'max:120'],
            'correo_electronico' => ['nullable', 'string', 'max:255'],
            'uso_factura' => ['nullable', 'string', 'max:40'],
            'nombre_razon_social' => ['nullable', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:20'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function datosFiscales(): array
    {
        $out = [];
        foreach (EnlaceDatosFiscales::CAMPOS as $campo) {
            if ($this->filled($campo)) {
                $out[$campo] = $this->input($campo);
            }
        }

        return $out;
    }
}
