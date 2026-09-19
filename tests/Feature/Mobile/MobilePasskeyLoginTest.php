<?php

namespace Tests\Feature\Mobile;

use App\Models\User;
use App\Models\WebauthnCredential;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laragear\WebAuthn\Assertion\Validator\AssertionValidation;
use Laragear\WebAuthn\Assertion\Validator\AssertionValidator;
use Laragear\WebAuthn\JsonTransport;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MobilePasskeyLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_verify_emite_token_con_misma_forma_que_login_movil(): void
    {
        $user = User::factory()->create([
            'email' => 'movil@empresa.com',
            'password' => 'secret123',
        ]);
        Permission::findOrCreate('mis_clientes.gestionar', 'web');
        $user->givePermissionTo('mis_clientes.gestionar');

        $credential = $this->crearCredencial($user, 'movil-cred-1');
        $validation = new AssertionValidation(new JsonTransport([]), $user);
        $validation->credential = $credential;

        $this->mock(AssertionValidator::class, function ($mock) use ($validation) {
            $mock->shouldReceive('send')->once()->andReturnSelf();
            $mock->shouldReceive('thenReturn')->once()->andReturn($validation);
        });

        $response = $this->postJson('/api/v1/passkeys/login/verify', [
            'login' => 'movil@empresa.com',
            'client' => 'mobile',
            'device_uuid' => '11111111-1111-1111-1111-111111111111',
            'device_name' => 'Pixel de prueba',
            'platform' => 'android',
            'app_version' => '1.0.0',
            'credential' => [
                'id' => 'movil-cred-1',
                'rawId' => 'movil-cred-1',
                'type' => 'public-key',
                'response' => [
                    'authenticatorData' => 'e30',
                    'clientDataJSON' => 'e30',
                    'signature' => 'e30',
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('device.device_uuid', '11111111-1111-1111-1111-111111111111')
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonStructure(['access_token', 'expires_at', 'scope_version', 'permissions', 'tema_visual']);

        $this->assertDatabaseHas('mobile_devices', [
            'user_id' => $user->id,
            'device_uuid' => '11111111-1111-1111-1111-111111111111',
        ]);
    }

    public function test_verify_revocada_responde_401(): void
    {
        $user = User::factory()->create(['email' => 'movil@empresa.com']);
        $credential = $this->crearCredencial($user, 'movil-revocada');
        $credential->disable();
        $credential->forceFill(['revocado_at' => now()])->save();

        $validation = new AssertionValidation(new JsonTransport([]), $user);
        $validation->credential = $credential->fresh();

        $this->mock(AssertionValidator::class, function ($mock) use ($validation) {
            $mock->shouldReceive('send')->once()->andReturnSelf();
            $mock->shouldReceive('thenReturn')->once()->andReturn($validation);
        });

        $this->postJson('/api/v1/passkeys/login/verify', [
            'login' => 'movil@empresa.com',
            'client' => 'mobile',
            'device_uuid' => '11111111-1111-1111-1111-111111111111',
            'credential' => [
                'id' => 'movil-revocada',
                'rawId' => 'movil-revocada',
                'type' => 'public-key',
                'response' => [
                    'authenticatorData' => 'e30',
                    'clientDataJSON' => 'e30',
                    'signature' => 'e30',
                ],
            ],
        ])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Las credenciales proporcionadas no coinciden con nuestros registros.');
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
        ]);
        $credential->save();

        return $credential->fresh();
    }
}
