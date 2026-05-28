<?php

namespace App\Http\Requests\Activos;

use Illuminate\Foundation\Http\FormRequest;

class AsignarActivoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('activos.asignar');
    }

    public function rules(): array
    {
        return [
            'user_id' => 'required|exists:users,id',
            'notas' => 'nullable|string|max:1000',
        ];
    }
}
