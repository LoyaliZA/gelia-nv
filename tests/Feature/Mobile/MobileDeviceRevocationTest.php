<?php

namespace Tests\Feature\Mobile;

use App\Models\MobileDevice;
use App\Models\User;
use App\Services\Mobile\MobileDeviceRevocationService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MobileDeviceRevocationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            ValidateCsrfToken::class,
            PreventRequestForgery::class,
            ThrottleRequests::class,
        ]);
    }

    public function test_dispositivo_revocado_no_puede_iniciar_sesion(): void
    {
        $user = User::factory()->create(['password' => 'secret123']);
        $uuid = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';

        $this->postJson('/api/v1/mobile/login', [
            'login' => $user->email,
            'password' => 'secret123',
            'device_uuid' => $uuid,
        ])->assertOk();

        $device = MobileDevice::query()->where('device_uuid', $uuid)->firstOrFail();
        app(MobileDeviceRevocationService::class)->revocar($device);

        $this->postJson('/api/v1/mobile/login', [
            'login' => $user->email,
            'password' => 'secret123',
            'device_uuid' => $uuid,
        ])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Las credenciales proporcionadas no coinciden con nuestros registros.');
    }

    public function test_tras_rehabilitar_admin_puede_iniciar_sesion(): void
    {
        $user = User::factory()->create(['password' => 'secret123']);
        $uuid = 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb';

        $this->postJson('/api/v1/mobile/login', [
            'login' => $user->email,
            'password' => 'secret123',
            'device_uuid' => $uuid,
        ])->assertOk();

        $device = MobileDevice::query()->where('device_uuid', $uuid)->firstOrFail();
        $service = app(MobileDeviceRevocationService::class);
        $service->revocar($device);

        $service->rehabilitar($device->fresh());

        $this->postJson('/api/v1/mobile/login', [
            'login' => $user->email,
            'password' => 'secret123',
            'device_uuid' => $uuid,
        ])->assertOk();
    }

    public function test_dispositivo_revocado_rechaza_me_y_sync(): void
    {
        Permission::findOrCreate('mis_clientes.gestionar', 'web');
        $user = User::factory()->create(['password' => 'secret123']);
        $user->givePermissionTo('mis_clientes.gestionar');
        $uuid = 'cccccccc-cccc-cccc-cccc-cccccccccccc';

        $token = $this->postJson('/api/v1/mobile/login', [
            'login' => $user->email,
            'password' => 'secret123',
            'device_uuid' => $uuid,
        ])->json('access_token');

        $device = MobileDevice::query()->where('device_uuid', $uuid)->firstOrFail();
        app(MobileDeviceRevocationService::class)->revocar($device);

        $this->withToken($token)
            ->getJson('/api/v1/mobile/me')
            ->assertUnauthorized();

        $this->withToken($token)
            ->getJson('/api/v1/mobile/sync/changes/head')
            ->assertUnauthorized();
    }

    public function test_usuario_archivado_no_inicia_sesion_y_token_previo_deja_de_servir(): void
    {
        Permission::findOrCreate('usuarios.archivar', 'web');
        $admin = User::factory()->create();
        $admin->givePermissionTo('usuarios.archivar');

        $colaborador = User::factory()->create(['password' => 'secret123']);
        $uuid = 'dddddddd-dddd-dddd-dddd-dddddddddddd';

        $token = $this->postJson('/api/v1/mobile/login', [
            'login' => $colaborador->email,
            'password' => 'secret123',
            'device_uuid' => $uuid,
        ])->json('access_token');

        $this->actingAs($admin)
            ->delete(route('admin.usuarios.archivar', $colaborador), ['motivo' => 'Prueba de archivado'])
            ->assertRedirect();

        $this->postJson('/api/v1/mobile/login', [
            'login' => $colaborador->email,
            'password' => 'secret123',
            'device_uuid' => $uuid,
        ])->assertUnauthorized();

        $this->withToken($token)
            ->getJson('/api/v1/mobile/me')
            ->assertUnauthorized();
    }

    public function test_scope_version_trata_permiso_de_rol_igual_que_directo(): void
    {
        $permiso = Permission::findOrCreate('clientes.ver', 'web');
        $rol = Role::findOrCreate('RolClienteVerMovil', 'web');
        $rol->syncPermissions([$permiso]);

        $porRol = User::factory()->create();
        $porRol->assignRole($rol);

        $directo = User::factory()->create();
        $directo->givePermissionTo($permiso);

        $scope = app(\App\Services\Mobile\MobileScopeVersionService::class);

        $this->assertSame($scope->compute($porRol), $scope->compute($directo));
    }
}
