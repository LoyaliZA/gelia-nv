<?php

namespace App\Http\Requests\Medios;

use Illuminate\Foundation\Http\FormRequest;

class CompletarCargaMedioRequest extends FormRequest
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
            'parts' => ['nullable', 'array'],
            'parts.*.PartNumber' => ['nullable', 'integer', 'min:1'],
            'parts.*.part_number' => ['nullable', 'integer', 'min:1'],
            'parts.*.ETag' => ['nullable', 'string', 'max:200'],
            'parts.*.etag' => ['nullable', 'string', 'max:200'],
            'duration_seconds' => ['nullable', 'integer', 'min:1', 'max:86400'],
        ];
    }
}
