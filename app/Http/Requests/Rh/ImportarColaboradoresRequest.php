<?php

namespace App\Http\Requests\Rh;

use Illuminate\Foundation\Http\FormRequest;

class ImportarColaboradoresRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('rh.colaboradores.crear');
    }

    public function rules(): array
    {
        return [
            'archivo' => 'required|file|mimes:csv,xlsx,xls,txt|max:10240',
        ];
    }

    public function messages(): array
    {
        return [
            'archivo.required' => 'Selecciona un archivo para importar.',
            'archivo.mimes' => 'El archivo debe ser CSV o Excel (.csv, .xlsx, .xls).',
            'archivo.max' => 'El archivo no puede superar 10 MB.',
        ];
    }
}
