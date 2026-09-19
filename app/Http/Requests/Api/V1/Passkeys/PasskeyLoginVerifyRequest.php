<?php

namespace App\Http\Requests\Api\V1\Passkeys;

use Illuminate\Foundation\Http\FormRequest;

class PasskeyLoginVerifyRequest extends FormRequest
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
            'remember' => ['sometimes', 'boolean'],
            'device_uuid' => ['required_if:client,mobile', 'nullable', 'uuid'],
            'device_name' => ['nullable', 'string', 'max:120'],
            'platform' => ['nullable', 'string', 'max:32'],
            'app_version' => ['nullable', 'string', 'max:32'],
            'credential' => ['required', 'array'],
            'credential.id' => ['required', 'string'],
            'credential.rawId' => ['required', 'string'],
            'credential.type' => ['required', 'string'],
            'credential.response' => ['required', 'array'],
            'credential.response.authenticatorData' => ['required', 'string'],
            'credential.response.clientDataJSON' => ['required', 'string'],
            'credential.response.signature' => ['required', 'string'],
            'credential.response.userHandle' => ['nullable', 'string'],
        ];
    }
}
