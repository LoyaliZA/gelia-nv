<?php

namespace App\Http\Requests\Api\V1\Passkeys;

use Illuminate\Foundation\Http\FormRequest;

class PasskeyRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client' => ['required', 'in:web,mobile'],
            'nickname' => ['nullable', 'string', 'max:120'],
            'device_uuid' => ['required_if:client,mobile', 'nullable', 'uuid'],
            'platform' => ['nullable', 'string', 'max:32'],
            'credential' => ['required', 'array'],
            'credential.id' => ['required', 'string'],
            'credential.rawId' => ['required', 'string'],
            'credential.type' => ['required', 'string'],
            'credential.response' => ['required', 'array'],
            'credential.response.clientDataJSON' => ['required', 'string'],
            'credential.response.attestationObject' => ['required', 'string'],
        ];
    }
}
