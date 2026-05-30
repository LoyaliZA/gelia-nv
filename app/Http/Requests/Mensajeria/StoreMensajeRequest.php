<?php

namespace App\Http\Requests\Mensajeria;

use App\Models\Mensaje;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMensajeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'contenido' => ['required', 'string', 'max:5000'],
            'reply_to_id' => ['nullable', 'integer', 'exists:mensajes,id'],
        ];
    }
}
