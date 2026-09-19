<?php

namespace App\Models;

use Laragear\WebAuthn\Models\WebAuthnCredential as LaragearWebAuthnCredential;

class WebauthnCredential extends LaragearWebAuthnCredential
{
    protected $visible = [
        'id',
        'origin',
        'alias',
        'nickname',
        'aaguid',
        'attestation_format',
        'disabled_at',
        'revocado_at',
        'platform',
        'last_used_at',
        'created_at',
        'transports',
    ];

    protected function casts(): array
    {
        return [
            'counter' => 'int',
            'transports' => 'array',
            'public_key' => 'encrypted',
            'certificates' => 'array',
            'disabled_at' => 'timestamp',
            'last_used_at' => 'datetime',
            'revocado_at' => 'datetime',
            'mobile_device_id' => 'integer',
        ];
    }

    public function toApiArray(): array
    {
        return [
            'id' => $this->getKey(),
            'nickname' => $this->nickname ?: $this->alias,
            'platform' => $this->platform,
            'transports' => $this->transports,
            'last_used_at' => optional($this->last_used_at)?->toIso8601String(),
            'revocado_at' => optional($this->revocado_at)?->toIso8601String(),
            'created_at' => optional($this->created_at)?->toIso8601String(),
        ];
    }
}
