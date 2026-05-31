<?php

namespace App\Http\Requests\Mensajeria;

use App\Models\Mensaje;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAdjuntoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'archivo' => ['required', 'file'],
            'tipo' => ['required', Rule::in([
                Mensaje::TIPO_IMAGEN,
                Mensaje::TIPO_VIDEO,
                Mensaje::TIPO_AUDIO,
                Mensaje::TIPO_ARCHIVO,
            ])],
            'contenido' => ['nullable', 'string', 'max:500'],
            'reply_to_id' => [
                'nullable',
                'integer',
                Rule::exists('mensajes', 'id')->where(
                    'conversacion_id',
                    $this->route('conversacion')?->id
                ),
            ],
        ];
    }
}
