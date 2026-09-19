<?php

namespace App\Http\Requests\Api\V1\Passkeys;

use Illuminate\Foundation\Http\FormRequest;

class PasskeyUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nickname' => ['required', 'string', 'max:120'],
        ];
    }
}
