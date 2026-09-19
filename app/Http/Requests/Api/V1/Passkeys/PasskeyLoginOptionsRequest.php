<?php

namespace App\Http\Requests\Api\V1\Passkeys;

use Illuminate\Foundation\Http\FormRequest;

class PasskeyLoginOptionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'login' => ['required', 'string', 'max:255'],
            'client' => ['required', 'in:web,mobile'],
            'device_uuid' => ['required_if:client,mobile', 'nullable', 'uuid'],
        ];
    }
}
