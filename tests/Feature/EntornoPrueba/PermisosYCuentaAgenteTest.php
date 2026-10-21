<?php

namespace Tests\Feature\EntornoPrueba;

use App\Models\ConfiguracionSistema;
use App\Models\Sucursal;
use App\Models\User;
use Database\Seeders\CuentaAgenteRevisionSeeder;
use Database\Seeders\EntornoPruebaSeeder;
use Database\Seeders\PermisosActualesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PermisosYCuentaAgenteTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_planta_permisos_faltantes_y_conserva_los_extra(): void
    {
        $catalogo = PermisosActualesSeeder::nombres();
        $this->assertNotEmpty($catalogo);

        Permission::findOrCreate('permiso.extra.fuera.de.catalogo', 'web');

        $this->seed(PermisosActualesSeeder::class);
        $this->seed(PermisosActualesSeeder::class);

        $this->assertTrue(Permission::query()->where('name', $catalogo[0])->where('guard_name', 'web')->exists());
        $this->assertTrue(Permission::query()->where('name', 'permiso.extra.fuera.de.catalogo')->exists());

        $rol = Role::findByName('Super Admin', 'web');
        $this->assertSame(Permission::query()->count(), $rol->permissions()->count());
    }

    public function test_cuenta_agente_recibe_rol_permisos_y_sucursal_activa(): void
    {
        config(['entorno_prueba.agente_password' => 'clave-de-prueba']);

        $activa = Sucursal::factory()->create(['activo' => true]);
        $inactiva = Sucursal::factory()->inactiva()->create();

        $this->seed(EntornoPruebaSeeder::class);
        $this->seed(EntornoPruebaSeeder::class);

        $usuario = User::query()->where('email', CuentaAgenteRevisionSeeder::EMAIL)->first();
        $this->assertNotNull($usuario);
        $this->assertSame('Agente de revisión', $usuario->name);
        $this->assertSame(CuentaAgenteRevisionSeeder::USERNAME, $usuario->username);
        $this->assertTrue($usuario->hasRole('Super Admin'), 'La cuenta no tiene el rol Super Admin.');
        $this->assertTrue(Hash::check('clave-de-prueba', $usuario->password), 'La contraseña de la cuenta no coincide.');
        $this->assertSame(Permission::query()->count(), $usuario->permissions()->count());
        $principalId = Sucursal::query()->where('activo', true)->orderBy('id')->value('id');
        $this->assertTrue(
            $usuario->sucursales()->where('sucursales.id', $activa->id)->wherePivot('activo', true)->exists(),
            'La sucursal activa no quedó asignada.'
        );
        $this->assertTrue(
            $usuario->sucursales()->where('sucursales.id', $principalId)->wherePivot('es_principal', true)->exists(),
            'La primera sucursal activa no quedó como principal.'
        );
        $this->assertFalse($usuario->sucursales()->where('sucursales.id', $inactiva->id)->exists());

        $modo = ConfiguracionSistema::query()->where('clave', 'gelia_ai.acceso_modo')->value('valor');
        $this->assertSame('super_admin', $modo);
    }

    public function test_importacion_se_niega_en_produccion(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $this->artisan('db:importar-entorno-prueba', ['--reemplazar-datos' => true])
            ->expectsOutputToContain('production')
            ->assertExitCode(1);
    }

    public function test_importacion_exige_la_bandera_de_reemplazo(): void
    {
        $this->artisan('db:importar-entorno-prueba')
            ->expectsOutputToContain('--reemplazar-datos')
            ->assertExitCode(1);
    }
}
