<?php

namespace App\Http\Requests\Api\V1\Passkeys;

use Illuminate\Foundation\Http\FormRequest;

class PasskeyRegisterOptionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nickname' => ['nullable', 'string', 'max:120'],
            'client' => ['required', 'in:web,mobile'],
            'device_uuid' => ['required_if:client,mobile', 'nullable', 'uuid'],
            'platform' => ['nullable', 'string', 'max:32'],
        ];
    }
}
