<?php

namespace App\Http\Requests\Medios;

use Illuminate\Foundation\Http\FormRequest;

class PartesCargaMedioRequest extends FormRequest
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
            'part_numbers' => ['required', 'array', 'min:1', 'max:32'],
            'part_numbers.*' => ['integer', 'min:1', 'max:10000'],
        ];
    }
}
