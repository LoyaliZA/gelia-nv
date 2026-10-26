<?php

namespace Tests\Feature\Admin;

use App\Models\Area;
use App\Models\Departamento;
use App\Models\MobileDevice;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Support\RefreshDatabaseSafe;
use Tests\TestCase;

class AlcanceGestionUsuariosTest extends TestCase
{
    use RefreshDatabaseSafe;

    private User $superAdmin;

    private User $administrador;

    private User $gerente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            ValidateCsrfToken::class,
            PreventRequestForgery::class,
        ]);

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $gestionar = Permission::query()->firstOrCreate(['name' => 'usuarios.gestionar', 'guard_name' => 'web']);
        $archivar = Permission::query()->firstOrCreate(['name' => 'usuarios.archivar', 'guard_name' => 'web']);
        $restaurar = Permission::query()->firstOrCreate(['name' => 'usuarios.restaurar', 'guard_name' => 'web']);

        $rolSuper = Role::query()->firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $rolAdministrador = Role::query()->firstOrCreate(['name' => 'Administrador', 'guard_name' => 'web']);
        $rolGerente = Role::query()->firstOrCreate(['name' => 'Gerente', 'guard_name' => 'web']);
        Role::query()->firstOrCreate(['name' => 'Colaborador', 'guard_name' => 'web']);
        $rolSuper->givePermissionTo([$gestionar, $archivar, $restaurar]);

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $this->superAdmin = User::factory()->create([
            'username' => 'super.admin',
            'apellido_paterno' => 'Raiz',
        ]);
        $this->superAdmin->assignRole($rolSuper);

        $this->administrador = User::factory()->create([
            'username' => 'admin.general',
            'apellido_paterno' => 'General',
        ]);
        $this->administrador->assignRole($rolAdministrador);
        $this->administrador->givePermissionTo([$gestionar, $archivar, $restaurar]);

        $this->gerente = User::factory()->create([
            'username' => 'gerencia.equipo',
            'apellido_paterno' => 'Equipo',
        ]);
        $this->gerente->assignRole($rolGerente);
        $this->gerente->givePermissionTo([$gestionar, $archivar, $restaurar]);

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_gerente_no_puede_asignar_rol_super_admin(): void
    {
        $colaborador = $this->colaboradorDelGerente();

        $this->actingAs($this->gerente)
            ->put(route('admin.usuarios.update', $colaborador), $this->payload($colaborador, [
                'roles_asignados' => ['Super Admin'],
            ]))
            ->assertRedirect()
            ->assertSessionHasErrors('roles_asignados');

        $this->assertFalse($colaborador->fresh()->hasRole('Super Admin'));
    }

    public function test_gerente_no_puede_elevar_sus_propios_roles(): void
    {
        $this->actingAs($this->gerente)
            ->put(route('admin.usuarios.update', $this->gerente), $this->payload($this->gerente, [
                'roles_asignados' => ['Gerente', 'Administrador'],
            ]))
            ->assertRedirect()
            ->assertSessionHasErrors('roles_asignados');

        $this->assertFalse($this->gerente->fresh()->hasRole('Administrador'));
    }

    public function test_administrador_no_puede_otorgar_ni_quitar_super_admin(): void
    {
        $colaborador = User::factory()->create([
            'username' => 'cuenta.normal',
            'apellido_paterno' => 'Normal',
        ]);

        $this->actingAs($this->administrador)
            ->put(route('admin.usuarios.update', $colaborador), $this->payload($colaborador, [
                'name' => 'Nombre nuevo',
                'roles_asignados' => ['Super Admin'],
            ]))
            ->assertSessionHasErrors('roles_asignados');

        $this->assertFalse($colaborador->fresh()->hasRole('Super Admin'));

        $this->actingAs($this->administrador)
            ->put(route('admin.usuarios.update', $this->superAdmin), $this->payload($this->superAdmin, [
                'name' => 'Raiz editada',
                'roles_asignados' => ['Colaborador'],
            ]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $raiz = $this->superAdmin->fresh();
        $this->assertSame('Raiz editada', $raiz->name);
        $this->assertTrue($raiz->hasRole('Super Admin'));
    }

    public function test_no_se_puede_quitar_el_ultimo_super_admin(): void
    {
        $this->actingAs($this->superAdmin)
            ->put(route('admin.usuarios.update', $this->superAdmin), $this->payload($this->superAdmin, [
                'roles_asignados' => ['Colaborador'],
            ]))
            ->assertSessionHasErrors('roles_asignados');

        $this->assertTrue($this->superAdmin->fresh()->hasRole('Super Admin'));
    }

    public function test_super_admin_puede_degradar_otro_super_admin_si_queda_uno(): void
    {
        $otro = User::factory()->create([
            'username' => 'super.dos',
            'apellido_paterno' => 'Dos',
        ]);
        $otro->assignRole('Super Admin');

        $this->actingAs($this->superAdmin)
            ->put(route('admin.usuarios.update', $otro), $this->payload($otro, [
                'roles_asignados' => ['Colaborador'],
            ]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertFalse($otro->fresh()->hasRole('Super Admin'));
        $this->assertTrue($this->superAdmin->fresh()->hasRole('Super Admin'));
    }

    public function test_gerente_no_gestiona_cuentas_fuera_de_su_equipo(): void
    {
        $ajeno = User::factory()->create([
            'username' => 'fuera.equipo',
            'apellido_paterno' => 'Ajeno',
        ]);

        $this->actingAs($this->gerente)
            ->put(route('admin.usuarios.update', $ajeno), $this->payload($ajeno))
            ->assertForbidden();

        $this->actingAs($this->gerente)
            ->getJson(route('admin.usuarios.mobile_devices.index', $ajeno))
            ->assertForbidden();

        $ajeno->delete();

        $this->actingAs($this->gerente)
            ->post(route('admin.usuarios.restaurar', $ajeno->id))
            ->assertForbidden();
    }

    public function test_alta_de_equipo_fija_al_gerente_y_rechaza_departamento_ajeno(): void
    {
        $propio = Departamento::create(['nombre' => 'Propio', 'activo' => true]);
        $ajeno = Departamento::create(['nombre' => 'Ajeno', 'activo' => true]);
        $this->gerente->departamentos()->attach($propio->id);

        $this->actingAs($this->gerente)
            ->post(route('admin.usuarios.store'), [
                'name' => 'Nueva',
                'apellido_paterno' => 'Cuenta',
                'username' => 'nueva.cuenta',
                'email' => 'nueva.cuenta@example.com',
                'password' => 'clave-segura',
                'roles_asignados' => ['Colaborador'],
                'permisos_individuales' => [],
                'departamentos' => [$propio->id],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $creado = User::where('username', 'nueva.cuenta')->first();
        $this->assertNotNull($creado);
        $this->assertTrue($creado->gerentes->contains(fn (User $gerente) => (int) $gerente->id === (int) $this->gerente->id));

        $this->actingAs($this->gerente)
            ->post(route('admin.usuarios.store'), [
                'name' => 'Otra',
                'apellido_paterno' => 'Cuenta',
                'username' => 'otra.cuenta',
                'email' => 'otra.cuenta@example.com',
                'password' => 'clave-segura',
                'roles_asignados' => ['Colaborador'],
                'permisos_individuales' => [],
                'departamentos' => [$ajeno->id],
            ])
            ->assertSessionHasErrors('departamentos');

        $this->assertNull(User::where('username', 'otra.cuenta')->first());
    }

    public function test_gerente_no_asigna_sucursal_fuera_de_su_alcance(): void
    {
        $propia = Sucursal::factory()->create(['nombre' => 'Propia']);
        $ajena = Sucursal::factory()->create(['nombre' => 'Ajena']);
        $this->gerente->concederAccesoSucursal($propia, true);
        $colaborador = $this->colaboradorDelGerente();

        $this->actingAs($this->gerente)
            ->put(route('admin.usuarios.update', $colaborador), $this->payload($colaborador, [
                'sucursales' => [$ajena->id],
                'sucursal_principal_id' => $ajena->id,
            ]))
            ->assertSessionHasErrors('sucursales');
    }

    public function test_solo_super_admin_archiva_una_cuenta_administrador(): void
    {
        $administrador = User::factory()->create([
            'username' => 'admin.equipo',
            'apellido_paterno' => 'Equipo',
        ]);
        $administrador->assignRole('Administrador');
        $this->gerente->colaboradores()->attach($administrador->id);

        $this->actingAs($this->gerente)
            ->delete(route('admin.usuarios.archivar', $administrador), ['motivo' => 'Salida del equipo'])
            ->assertSessionHasErrors('error');

        $this->assertNull($administrador->fresh()->deleted_at);

        $this->actingAs($this->superAdmin)
            ->delete(route('admin.usuarios.archivar', $administrador), ['motivo' => 'Salida del equipo'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertNotNull(User::withTrashed()->find($administrador->id)?->deleted_at);
    }

    public function test_cambio_de_contrasena_exige_ocho_caracteres_y_cierra_accesos(): void
    {
        $colaborador = $this->colaboradorDelGerente();
        $colaborador->createToken('mobile:prueba');
        MobileDevice::create([
            'user_id' => $colaborador->id,
            'device_uuid' => 'device-prueba',
            'nombre' => 'Telefono',
            'plataforma' => 'android',
        ]);
        DB::table('sessions')->insert([
            'id' => 'sesion-prueba',
            'user_id' => $colaborador->id,
            'payload' => 'x',
            'last_activity' => time(),
        ]);

        $this->actingAs($this->gerente)
            ->put(route('admin.usuarios.update', $colaborador), $this->payload($colaborador, [
                'password' => 'corta',
            ]))
            ->assertSessionHasErrors('password');

        $this->actingAs($this->gerente)
            ->put(route('admin.usuarios.update', $colaborador), $this->payload($colaborador, [
                'password' => 'clave-segura',
            ]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $colaborador->refresh();
        $this->assertTrue(Hash::check('clave-segura', $colaborador->password));
        $this->assertSame(0, $colaborador->tokens()->count());
        $this->assertNotNull($colaborador->mobileDevices()->first()->revocado_at);
        $this->assertNull(DB::table('sessions')->where('id', 'sesion-prueba')->first());
    }

    public function test_filtros_de_organizacion_acotan_el_listado_autorizado(): void
    {
        $aromas = Departamento::create(['nombre' => 'Aromas', 'activo' => true]);
        $caja = Departamento::create(['nombre' => 'Caja', 'activo' => true]);
        $mostrador = Area::create(['nombre' => 'Mostrador', 'departamento_id' => $aromas->id]);
        $centro = Sucursal::factory()->create(['nombre' => 'Centro']);
        $norte = Sucursal::factory()->create(['nombre' => 'Norte']);

        $enAromas = User::factory()->create([
            'username' => 'en.aromas',
            'name' => 'En Aromas',
            'departamento_id' => $aromas->id,
            'area_id' => $mostrador->id,
        ]);
        $enAromas->concederAccesoSucursal($centro, true);

        $unicaSucursal = User::factory()->create([
            'username' => 'unica.sucursal',
            'name' => 'Unica Sucursal',
            'departamento_id' => $caja->id,
        ]);
        $unicaSucursal->concederAccesoSucursal($centro, false);

        $sinPrincipal = User::factory()->create([
            'username' => 'sin.principal',
            'name' => 'Sin Principal',
        ]);
        $sinPrincipal->concederAccesoSucursal($centro, false);
        $sinPrincipal->concederAccesoSucursal($norte, false);

        $fuera = User::factory()->create([
            'username' => 'fuera.filtro',
            'name' => 'Fuera Filtro',
            'departamento_id' => $caja->id,
        ]);

        $sinFiltro = $this->idsDelListado(
            $this->actingAs($this->administrador)->get(route('admin.usuarios'))
        );
        $this->assertContains($enAromas->id, $sinFiltro);
        $this->assertContains($fuera->id, $sinFiltro);
        $this->assertContains($sinPrincipal->id, $sinFiltro);

        $porDepartamento = $this->idsDelListado(
            $this->actingAs($this->administrador)->get(route('admin.usuarios', ['departamento_id' => $aromas->id]))
        );
        $this->assertContains($enAromas->id, $porDepartamento);
        $this->assertNotContains($fuera->id, $porDepartamento);

        $porArea = $this->idsDelListado(
            $this->actingAs($this->administrador)->get(route('admin.usuarios', ['area_id' => $mostrador->id]))
        );
        $this->assertSame([$enAromas->id], $porArea);

        $porSucursal = $this->idsDelListado(
            $this->actingAs($this->administrador)->get(route('admin.usuarios', ['sucursal_id' => $centro->id]))
        );
        $this->assertContains($enAromas->id, $porSucursal);
        $this->assertContains($unicaSucursal->id, $porSucursal);
        $this->assertNotContains($sinPrincipal->id, $porSucursal);
        $this->assertNotContains($fuera->id, $porSucursal);

        $ajeno = User::factory()->create([
            'username' => 'ajeno.equipo',
            'name' => 'Ajeno Equipo',
            'departamento_id' => $aromas->id,
            'area_id' => $mostrador->id,
        ]);
        $equipo = $this->colaboradorDelGerente();
        $equipo->forceFill([
            'departamento_id' => $aromas->id,
            'area_id' => $mostrador->id,
        ])->save();

        $delGerente = $this->idsDelListado(
            $this->actingAs($this->gerente)->get(route('admin.usuarios', [
                'departamento_id' => $aromas->id,
                'area_id' => $mostrador->id,
            ]))
        );
        $this->assertContains($equipo->id, $delGerente);
        $this->assertNotContains($ajeno->id, $delGerente);
        $this->assertNotContains($enAromas->id, $delGerente);
    }

    public function test_area_inexistente_se_rechaza(): void
    {
        $depto = Departamento::create(['nombre' => 'Operacion', 'activo' => true]);
        $area = Area::create(['nombre' => 'Mostrador', 'departamento_id' => $depto->id]);
        $colaborador = User::factory()->create([
            'username' => 'con.area',
            'apellido_paterno' => 'Area',
        ]);

        $this->actingAs($this->administrador)
            ->put(route('admin.usuarios.update', $colaborador), $this->payload($colaborador, [
                'areas' => [$area->id, 999999],
            ]))
            ->assertSessionHasErrors('areas');
    }

    /**
     * @return array<int, int>
     */
    private function idsDelListado($response): array
    {
        $response->assertOk();
        $pagina = $response->inertiaPage();

        return collect($pagina['props']['usuarios']['data'])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function colaboradorDelGerente(): User
    {
        $colaborador = User::factory()->create([
            'username' => 'colaborador.'.uniqid(),
            'apellido_paterno' => 'Reporta',
        ]);
        $this->gerente->colaboradores()->attach($colaborador->id);

        return $colaborador;
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function payload(User $user, array $extra = []): array
    {
        return array_merge([
            'name' => $user->name,
            'apellido_paterno' => $user->apellido_paterno ?? 'Paterno',
            'username' => $user->username,
            'email' => $user->email,
            'roles_asignados' => [],
            'permisos_individuales' => [],
        ], $extra);
    }
}
