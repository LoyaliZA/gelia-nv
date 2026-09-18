<?php

namespace Tests\Feature\Mobile;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MobileAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_emite_token_por_dispositivo(): void
    {
        $user = User::factory()->create(['password' => 'secret123']);
        Permission::findOrCreate('mis_clientes.gestionar', 'web');
        $user->givePermissionTo('mis_clientes.gestionar');

        $response = $this->postJson('/api/v1/mobile/login', [
            'login' => $user->email,
            'password' => 'secret123',
            'device_uuid' => '11111111-1111-1111-1111-111111111111',
            'device_name' => 'Pixel de prueba',
            'platform' => 'android',
            'app_version' => '1.0.0',
        ]);

        $response->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('device.device_uuid', '11111111-1111-1111-1111-111111111111')
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonStructure(['access_token', 'expires_at', 'scope_version', 'permissions']);

        $this->assertDatabaseHas('mobile_devices', [
            'user_id' => $user->id,
            'device_uuid' => '11111111-1111-1111-1111-111111111111',
        ]);
        $this->assertSame(1, PersonalAccessToken::query()->count());
    }

    public function test_login_invalido_responde_401(): void
    {
        $user = User::factory()->create(['password' => 'secret123']);

        $this->postJson('/api/v1/mobile/login', [
            'login' => $user->email,
            'password' => 'incorrecta',
            'device_uuid' => '11111111-1111-1111-1111-111111111111',
        ])->assertUnauthorized();
    }

    public function test_relogin_revoca_token_previo_del_dispositivo(): void
    {
        $user = User::factory()->create(['password' => 'secret123']);

        $primero = $this->postJson('/api/v1/mobile/login', [
            'login' => $user->email,
            'password' => 'secret123',
            'device_uuid' => '11111111-1111-1111-1111-111111111111',
        ])->json('access_token');

        $segundo = $this->postJson('/api/v1/mobile/login', [
            'login' => $user->email,
            'password' => 'secret123',
            'device_uuid' => '11111111-1111-1111-1111-111111111111',
        ])->json('access_token');

        $this->assertNotSame($primero, $segundo);
        $this->assertSame(1, PersonalAccessToken::query()->count());

        $this->withToken($primero)
            ->getJson('/api/v1/mobile/me')
            ->assertUnauthorized();
    }

    public function test_me_y_logout(): void
    {
        $user = User::factory()->create(['password' => 'secret123']);
        Permission::findOrCreate('clientes.ver', 'web');
        $user->givePermissionTo('clientes.ver');

        $token = $this->postJson('/api/v1/mobile/login', [
            'login' => $user->email,
            'password' => 'secret123',
            'device_uuid' => '22222222-2222-2222-2222-222222222222',
        ])->json('access_token');

        $this->withToken($token)
            ->getJson('/api/v1/mobile/me')
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('device.device_uuid', '22222222-2222-2222-2222-222222222222');

        $this->withToken($token)
            ->postJson('/api/v1/mobile/logout')
            ->assertOk();

        $this->assertSame(0, PersonalAccessToken::query()->count());

        $this->withToken($token)
            ->getJson('/api/v1/mobile/me')
            ->assertUnauthorized();
    }
}
