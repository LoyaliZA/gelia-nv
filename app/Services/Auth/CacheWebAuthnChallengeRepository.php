<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\Cache;
use Laragear\WebAuthn\Assertion\Creator\AssertionCreation;
use Laragear\WebAuthn\Assertion\Validator\AssertionValidation;
use Laragear\WebAuthn\Attestation\Creator\AttestationCreation;
use Laragear\WebAuthn\Attestation\Validator\AttestationValidation;
use Laragear\WebAuthn\Challenge\Challenge;
use Laragear\WebAuthn\Contracts\WebAuthnChallengeRepository;

class CacheWebAuthnChallengeRepository implements WebAuthnChallengeRepository
{
    public function store(AttestationCreation|AssertionCreation $ceremony, Challenge $challenge): void
    {
        Cache::put($this->clave($ceremony), $challenge->toArray(), $challenge->expiresAt());
    }

    public function pull(AttestationValidation|AssertionValidation $ceremony): ?Challenge
    {
        $payload = Cache::pull($this->clave($ceremony));

        if (! is_array($payload)) {
            return null;
        }

        $challenge = Challenge::fromArray($payload);

        return $challenge->isValid() ? $challenge : null;
    }

    private function clave(
        AttestationCreation|AssertionCreation|AttestationValidation|AssertionValidation $ceremony
    ): string {
        $esRegistro = $ceremony instanceof AttestationCreation
            || $ceremony instanceof AttestationValidation;

        $userId = $ceremony->user?->getAuthIdentifier();

        if ($userId === null && ! $esRegistro) {
            $userId = app(ResolverUsuarioLogin::class)->resolver(request()->input('login'))?->getAuthIdentifier();
        }

        $prefijo = $esRegistro ? 'webauthn:register' : 'webauthn:login';

        return $prefijo.':'.($userId ?? 'unknown');
    }
}
