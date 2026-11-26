<?php

namespace Tests\Feature\Mobile;

use App\Models\MobileDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class MobileEspejoRutasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([ThrottleRequests::class]);
    }

    public function test_cada_ruta_web_de_pdv_y_pedidos_tiene_su_equivalente_movil(): void
    {
        $this->artisan('api:paridad-rutas')->assertExitCode(0);
    }

    public function test_el_espejo_movil_exige_sesion_movil(): void
    {
        $this->getJson('/api/v1/mobile/control-pedidos/listado')->assertUnauthorized();
        $this->postJson('/api/v1/mobile/control-pedidos/1/cerrar-consulta')->assertUnauthorized();
    }

    public function test_el_espejo_movil_respeta_el_permiso_de_la_ruta_web(): void
    {
        Permission::findOrCreate('control_pedidos.ver_listado', 'web');
        $sinPermiso = User::factory()->create();

        $this->conToken($this->token($sinPermiso))
            ->getJson('/api/v1/mobile/control-pedidos/listado')
            ->assertForbidden();
    }

    public function test_me_devuelve_los_permisos_efectivos_y_los_roles_sin_que_el_rol_otorgue_acceso(): void
    {
        Permission::findOrCreate('control_pedidos.ver_listado', 'web');
        Permission::findOrCreate('control_pedidos.crear', 'web');
        $rol = Role::findOrCreate('Operador de prueba', 'web');
        $rol->givePermissionTo('control_pedidos.crear');

        $usuario = User::factory()->create();
        $usuario->assignRole($rol);
        $usuario->givePermissionTo('control_pedidos.ver_listado');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->conToken($this->token($usuario))
            ->getJson('/api/v1/mobile/me')
            ->assertOk()
            ->assertJsonPath('permissions', ['control_pedidos.ver_listado'])
            ->assertJsonPath('roles', ['Operador de prueba']);
    }

    private function conToken(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    private function token(User $usuario): string
    {
        $device = MobileDevice::query()->create([
            'user_id' => $usuario->id,
            'device_uuid' => (string) Str::uuid(),
            'last_seen_at' => now(),
        ]);

        return $usuario->createToken('mobile:'.$device->device_uuid, ['mobile'])->plainTextToken;
    }
}
