<?php

namespace Tests\Feature\Mobile;

use App\Models\ConfiguracionUsuario;
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
            ->assertJsonPath('tema_visual', [])
            ->assertJsonStructure(['access_token', 'expires_at', 'scope_version', 'permissions', 'tema_visual']);

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

    public function test_login_y_me_incluyen_tema_visual_del_usuario(): void
    {
        $user = User::factory()->create(['password' => 'secret123']);
        ConfiguracionUsuario::create([
            'user_id' => $user->id,
            'tema_visual' => [
                'modo' => 'dark',
                'color_nombre' => 'rosa',
                'layout_sidebar_mobile' => 'mobile_bottom',
            ],
        ]);

        $login = $this->postJson('/api/v1/mobile/login', [
            'login' => $user->email,
            'password' => 'secret123',
            'device_uuid' => '33333333-3333-3333-3333-333333333333',
        ])->assertOk();

        $login
            ->assertJsonPath('tema_visual.modo', 'dark')
            ->assertJsonPath('tema_visual.color_nombre', 'rosa')
            ->assertJsonPath('tema_visual.layout_sidebar_mobile', 'mobile_bottom');

        $scopeVersion = $login->json('scope_version');
        $token = $login->json('access_token');

        $this->withToken($token)
            ->getJson('/api/v1/mobile/me')
            ->assertOk()
            ->assertJsonPath('tema_visual.modo', 'dark')
            ->assertJsonPath('tema_visual.color_nombre', 'rosa')
            ->assertJsonPath('scope_version', $scopeVersion);

        ConfiguracionUsuario::query()
            ->where('user_id', $user->id)
            ->update([
                'tema_visual' => [
                    'modo' => 'light',
                    'color_nombre' => 'azul',
                ],
            ]);

        $this->withToken($token)
            ->getJson('/api/v1/mobile/me')
            ->assertOk()
            ->assertJsonPath('tema_visual.modo', 'light')
            ->assertJsonPath('tema_visual.color_nombre', 'azul')
            ->assertJsonPath('scope_version', $scopeVersion);
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
            ->assertJsonPath('device.device_uuid', '22222222-2222-2222-2222-222222222222')
            ->assertJsonStructure(['tema_visual', 'scope_version', 'permissions']);

        $this->withToken($token)
            ->postJson('/api/v1/mobile/logout')
            ->assertOk();

        $this->assertSame(0, PersonalAccessToken::query()->count());

        $this->withToken($token)
            ->getJson('/api/v1/mobile/me')
            ->assertUnauthorized();
    }
}
