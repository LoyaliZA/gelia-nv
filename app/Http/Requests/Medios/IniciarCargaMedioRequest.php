<?php

namespace App\Http\Requests\Medios;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IniciarCargaMedioRequest extends FormRequest
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
            'filename' => ['required', 'string', 'max:180'],
            'size' => ['required', 'integer', 'min:1', 'max:'.(int) config('medios.max_bytes')],
            'mime_type' => ['required', 'string', 'max:127', Rule::in(array_keys(config('medios.mimes', [])))],
            'proposito' => ['required', 'string', Rule::in(array_keys(config('medios.propositos', [])))],
        ];
    }
}
