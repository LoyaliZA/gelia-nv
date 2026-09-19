<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Models\WebauthnCredential;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Laragear\WebAuthn\Assertion\Validator\AssertionValidation;
use Laragear\WebAuthn\Assertion\Validator\AssertionValidator;
use Laragear\WebAuthn\Attestation\Validator\AttestationValidation;
use Laragear\WebAuthn\Attestation\Validator\AttestationValidator;
use Laragear\WebAuthn\JsonTransport;
use Tests\TestCase;

class PasskeyAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_options_requiere_autenticacion(): void
    {
        $this->postJson('/api/v1/passkeys/register/options', [
            'client' => 'web',
        ])->assertUnauthorized();
    }

    public function test_register_options_devuelve_challenge(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/passkeys/register/options', [
            'client' => 'web',
            'nickname' => 'Chrome en Windows',
        ])
            ->assertOk()
            ->assertJsonStructure(['challenge', 'rp', 'user', 'pubKeyCredParams']);
    }

    public function test_registro_persiste_credencial(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $pendiente = $user->makeWebAuthnCredential([
            'id' => 'credencial-web-1',
            'user_id' => (string) Str::uuid(),
            'counter' => 0,
            'rp_id' => 'gelianv.neobash.site',
            'origin' => 'https://gelianv.neobash.site',
            'public_key' => 'clave-publica-prueba',
            'attestation_format' => 'none',
        ]);

        $validation = new AttestationValidation($user, new JsonTransport([]));
        $validation->credential = $pendiente;

        $this->mock(AttestationValidator::class, function ($mock) use ($validation) {
            $mock->shouldReceive('send')->once()->andReturnSelf();
            $mock->shouldReceive('thenReturn')->once()->andReturn($validation);
        });

        $this->postJson('/api/v1/passkeys/register', [
            'client' => 'web',
            'nickname' => 'Chrome en Windows',
            'credential' => $this->attestationPayload(),
        ])
            ->assertCreated()
            ->assertJsonPath('nickname', 'Chrome en Windows');

        $this->assertDatabaseHas('webauthn_credentials', [
            'id' => 'credencial-web-1',
            'nickname' => 'Chrome en Windows',
        ]);
    }

    public function test_login_options_devuelve_allow_credentials_activas(): void
    {
        $user = User::factory()->create(['email' => 'operador@empresa.com']);
        $this->crearCredencial($user, 'activa-1');
        $revocada = $this->crearCredencial($user, 'revocada-1');
        $revocada->disable();
        $revocada->forceFill(['revocado_at' => now()])->save();

        $this->postJson('/api/v1/passkeys/login/options', [
            'login' => 'operador@empresa.com',
            'client' => 'web',
        ])
            ->assertOk()
            ->assertJsonPath('allowCredentials.0.id', 'activa-1')
            ->assertJsonMissing(['id' => 'revocada-1']);
    }

    public function test_login_options_usuario_inexistente_no_enumera(): void
    {
        $this->postJson('/api/v1/passkeys/login/options', [
            'login' => 'no-existe@empresa.com',
            'client' => 'web',
        ])
            ->assertOk()
            ->assertJsonPath('allowCredentials', []);
    }

    public function test_verify_web_inicia_sesion(): void
    {
        $user = User::factory()->create(['email' => 'operador@empresa.com']);
        $credential = $this->crearCredencial($user, 'login-web-1');

        $validation = new AssertionValidation(new JsonTransport([]), $user);
        $validation->credential = $credential;

        $this->mock(AssertionValidator::class, function ($mock) use ($validation) {
            $mock->shouldReceive('send')->once()->andReturnSelf();
            $mock->shouldReceive('thenReturn')->once()->andReturn($validation);
        });

        $this->postJson('/api/v1/passkeys/login/verify', [
            'login' => 'operador@empresa.com',
            'client' => 'web',
            'credential' => $this->assertionPayload('login-web-1'),
        ])
            ->assertOk()
            ->assertJsonStructure(['redirect']);

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($credential->fresh()->last_used_at);
    }

    public function test_revocacion_impide_login_y_oculta_credencial(): void
    {
        $user = User::factory()->create(['email' => 'operador@empresa.com']);
        Sanctum::actingAs($user);
        $this->crearCredencial($user, 'para-revocar');

        $this->deleteJson('/api/v1/passkeys/para-revocar')
            ->assertOk();

        $this->assertNotNull(WebauthnCredential::query()->find('para-revocar')?->revocado_at);

        $this->postJson('/api/v1/passkeys/login/options', [
            'login' => 'operador@empresa.com',
            'client' => 'web',
        ])
            ->assertOk()
            ->assertJsonPath('allowCredentials', []);

        $this->getJson('/api/v1/passkeys')
            ->assertOk()
            ->assertJsonPath('data', []);
    }

    public function test_rate_limit_en_login_options(): void
    {
        $payload = [
            'login' => 'operador@empresa.com',
            'client' => 'web',
        ];

        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/v1/passkeys/login/options', $payload)->assertOk();
        }

        $this->postJson('/api/v1/passkeys/login/options', $payload)->assertStatus(429);
    }

    public function test_flag_desactivado_responde_404(): void
    {
        config(['webauthn.enabled' => false]);

        $this->postJson('/api/v1/passkeys/login/options', [
            'login' => 'operador@empresa.com',
            'client' => 'web',
        ])->assertNotFound();
    }

    private function crearCredencial(User $user, string $id): WebauthnCredential
    {
        $credential = $user->makeWebAuthnCredential([
            'id' => $id,
            'user_id' => (string) Str::uuid(),
            'counter' => 1,
            'rp_id' => 'gelianv.neobash.site',
            'origin' => 'https://gelianv.neobash.site',
            'public_key' => 'clave-publica-prueba',
            'attestation_format' => 'none',
            'alias' => 'Prueba',
            'nickname' => 'Prueba',
        ]);
        $credential->save();

        return $credential->fresh();
    }

    /**
     * @return array<string, mixed>
     */
    private function attestationPayload(): array
    {
        return [
            'id' => 'credencial-web-1',
            'rawId' => 'credencial-web-1',
            'type' => 'public-key',
            'response' => [
                'clientDataJSON' => 'e30',
                'attestationObject' => 'e30',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function assertionPayload(string $id): array
    {
        return [
            'id' => $id,
            'rawId' => $id,
            'type' => 'public-key',
            'response' => [
                'authenticatorData' => 'e30',
                'clientDataJSON' => 'e30',
                'signature' => 'e30',
                'userHandle' => null,
            ],
        ];
    }
}
