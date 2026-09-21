<?php

namespace App\Services\Auth;

use App\Models\MobileDevice;
use App\Models\User;
use App\Models\WebauthnCredential;
use App\Services\Auditoria\RegistrarAuditoriaAccesoService;
use App\Services\Mobile\MobileAuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laragear\WebAuthn\Assertion\Creator\AssertionCreator;
use Laragear\WebAuthn\Assertion\Creator\AssertionCreation;
use Laragear\WebAuthn\Assertion\Validator\AssertionValidation;
use Laragear\WebAuthn\Assertion\Validator\AssertionValidator;
use Laragear\WebAuthn\Attestation\Creator\AttestationCreation;
use Laragear\WebAuthn\Attestation\Creator\AttestationCreator;
use Laragear\WebAuthn\Attestation\Validator\AttestationValidation;
use Laragear\WebAuthn\Attestation\Validator\AttestationValidator;
use Laragear\WebAuthn\Enums\UserVerification;
use Laragear\WebAuthn\Events\CredentialCreated;
use Laragear\WebAuthn\JsonTransport;
use Symfony\Component\HttpKernel\Exception\HttpException;

class WebAuthnCredentialService
{
    public const MENSAJE_CREDENCIAL_INVALIDA = 'Las credenciales proporcionadas no coinciden con nuestros registros.';

    public function __construct(
        protected ResolverUsuarioLogin $resolverUsuario,
        protected MobileAuthService $mobileAuth,
        protected RegistrarAuditoriaAccesoService $auditoriaAcceso,
        protected AttestationCreator $attestationCreator,
        protected AttestationValidator $attestationValidator,
        protected AssertionCreator $assertionCreator,
        protected AssertionValidator $assertionValidator,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function opcionesRegistro(User $user, Request $request): array
    {
        $creation = new AttestationCreation($user);
        $creation->userVerification = UserVerification::Required;
        $creation->uniqueCredentials = false;

        $json = $this->attestationCreator
            ->send($creation)
            ->thenReturn()
            ->json
            ->toArray();

        return $json;
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    public function registrar(User $user, array $datos, Request $request): array
    {
        try {
            $transport = new JsonTransport($datos['credential']);
            $validation = $this->attestationValidator
                ->send(new AttestationValidation($user, $transport))
                ->thenReturn();
        } catch (ValidationException) {
            throw new HttpException(422, 'No se pudo registrar la passkey.');
        }

        $credential = $validation->credential;

        if (! $credential) {
            throw new HttpException(422, 'No se pudo registrar la passkey.');
        }

        $device = $this->resolverDispositivoMovil($user, $datos);

        $credential->forceFill([
            'alias' => $datos['nickname'] ?? $credential->alias,
            'nickname' => $datos['nickname'] ?? $credential->nickname,
            'platform' => $datos['platform'] ?? ($datos['client'] === 'mobile' ? null : 'web'),
            'mobile_device_id' => $device?->id,
        ])->save();

        CredentialCreated::dispatch($user, $credential);

        $this->auditoriaAcceso->registrarEventoPuntual(
            $user,
            $request,
            'passkey:'.substr(hash('sha256', (string) $credential->getKey()), 0, 40),
            'passkey_registered'
        );

        return WebauthnCredential::query()->findOrFail($credential->getKey())->toApiArray();
    }

    /**
     * @return array<string, mixed>
     */
    public function opcionesLogin(array $datos): array
    {
        $user = $this->resolverUsuario->resolver($datos['login'] ?? null);

        if (! $user) {
            return $this->opcionesVacias();
        }

        $creation = new AssertionCreation($user);
        $creation->userVerification = UserVerification::Required;

        $json = $this->assertionCreator
            ->send($creation)
            ->thenReturn()
            ->json
            ->toArray();

        $permitidas = $user->webAuthnCredentials()->whereEnabled()->whereNull('revocado_at')->pluck('id');
        $json['allowCredentials'] = collect($json['allowCredentials'] ?? [])
            ->filter(fn (array $item) => $permitidas->contains($item['id'] ?? null))
            ->values()
            ->all();

        return $json;
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    public function verificarLogin(array $datos, Request $request): array
    {
        $user = $this->resolverUsuario->resolver($datos['login'] ?? null);

        if (! $user) {
            throw $this->credencialInvalida();
        }

        // ponytail: Laragear validateId no decodifica userHandle base64url (16 bytes); con login
        // ya resuelto basta validateUser + firma. Upgrade: pipe custom con Uuid::fromBytes().
        $credencialTransporte = $datos['credential'];
        unset($credencialTransporte['response']['userHandle']);

        try {
            $validation = $this->assertionValidator
                ->send(new AssertionValidation(new JsonTransport($credencialTransporte), $user))
                ->thenReturn();
        } catch (ValidationException) {
            throw $this->credencialInvalida();
        }

        $credential = $validation->credential;

        if (! $credential || $credential->isDisabled() || $credential->revocado_at) {
            throw $this->credencialInvalida();
        }

        $credential->forceFill(['last_used_at' => now()])->save();

        $client = $datos['client'] ?? 'web';

        if ($client === 'mobile') {
            return $this->mobileAuth->emitirSesionMovil($user, $datos, $request);
        }

        Auth::login($user, (bool) ($datos['remember'] ?? true));
        $request->session()->regenerate();
        $this->auditoriaAcceso->registrarLogin($user, $request, $request->session()->getId());

        return [
            'redirect' => redirect()->intended(route('dashboard'))->getTargetUrl(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listar(User $user): array
    {
        return WebauthnCredential::query()
            ->where('authenticatable_type', $user->getMorphClass())
            ->where('authenticatable_id', $user->getKey())
            ->whereNull('revocado_at')
            ->orderByDesc('last_used_at')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (WebauthnCredential $credential) => $credential->toApiArray())
            ->all();
    }

    public function revocar(User $user, string $credentialId): void
    {
        $credential = $this->credencialDelUsuario($user, $credentialId);

        $credential->disable();
        $credential->forceFill(['revocado_at' => now()])->save();

        $this->auditoriaAcceso->registrarEventoPuntual(
            $user,
            request(),
            'passkey:'.substr(hash('sha256', (string) $credential->getKey()), 0, 40),
            'passkey_revoked'
        );
    }

    public function renombrar(User $user, string $credentialId, string $nickname): array
    {
        $credential = $this->credencialDelUsuario($user, $credentialId);
        $credential->forceFill([
            'alias' => $nickname,
            'nickname' => $nickname,
        ])->save();

        return WebauthnCredential::query()->findOrFail($credential->getKey())->toApiArray();
    }

    private function credencialDelUsuario(User $user, string $credentialId): WebauthnCredential
    {
        $credential = WebauthnCredential::query()
            ->whereKey($credentialId)
            ->where('authenticatable_type', $user->getMorphClass())
            ->where('authenticatable_id', $user->getKey())
            ->first();

        if (! $credential || $credential->revocado_at) {
            throw new HttpException(404, 'No se encontró la credencial.');
        }

        return $credential;
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function resolverDispositivoMovil(User $user, array $datos): ?MobileDevice
    {
        if (($datos['client'] ?? 'web') !== 'mobile' || empty($datos['device_uuid'])) {
            return null;
        }

        return MobileDevice::query()
            ->where('user_id', $user->id)
            ->where('device_uuid', $datos['device_uuid'])
            ->whereNull('revocado_at')
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function opcionesVacias(): array
    {
        return [
            'challenge' => bin2hex(random_bytes(16)),
            'timeout' => ((int) config('webauthn.challenge.timeout', 120)) * 1000,
            'rpId' => config('webauthn.relying_party.id'),
            'allowCredentials' => [],
            'userVerification' => UserVerification::Required->value,
        ];
    }

    private function credencialInvalida(): HttpException
    {
        return new HttpException(401, self::MENSAJE_CREDENCIAL_INVALIDA);
    }
}
