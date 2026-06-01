<?php

namespace App\Http\Requests\Productos;

use Illuminate\Foundation\Http\FormRequest;

class ImportarProductosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('catalogos.gestionar');
    }

    public function rules(): array
    {
        return [
            'archivo' => 'required|file|mimes:csv,txt|max:10240',
        ];
    }
}
