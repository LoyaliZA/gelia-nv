<?php

namespace App\Http\Requests\Mensajeria;

use App\Models\Conversacion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreConversacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'tipo' => ['required', Rule::in([Conversacion::TIPO_DIRECTO, Conversacion::TIPO_GRUPO])],
            'nombre' => ['nullable', 'string', 'max:100'],
            'participante_ids' => ['required', 'array', 'min:1'],
            'participante_ids.*' => ['integer', 'exists:users,id'],
        ];
    }
}
