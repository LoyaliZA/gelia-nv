<?php

namespace Tests\Feature\Admin;

use App\Models\Area;
use App\Models\AuditoriaConfiguracion;
use App\Models\Departamento;
use App\Models\MobileDevice;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Support\RefreshDatabaseSafe;
use Tests\TestCase;

class IntegridadFormularioUsuariosTest extends TestCase
{
    use RefreshDatabaseSafe;

    private User $superAdmin;

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
        $rolSuper = Role::query()->firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $rolGerente = Role::query()->firstOrCreate(['name' => 'Gerente', 'guard_name' => 'web']);
        Role::query()->firstOrCreate(['name' => 'Colaborador', 'guard_name' => 'web']);
        $rolSuper->givePermissionTo($gestionar);
        $rolGerente->givePermissionTo($gestionar);

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $this->superAdmin = User::factory()->create([
            'username' => 'super.formulario',
            'apellido_paterno' => 'Raiz',
        ]);
        $this->superAdmin->assignRole($rolSuper);

        $this->gerente = User::factory()->create([
            'username' => 'gerente.formulario',
            'apellido_paterno' => 'Equipo',
        ]);
        $this->gerente->assignRole($rolGerente);

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_alta_y_edicion_agregan_y_quitan_relaciones(): void
    {
        $deptoA = Departamento::create(['nombre' => 'Operacion', 'activo' => true]);
        $deptoB = Departamento::create(['nombre' => 'Administracion', 'activo' => true]);
        $areaA = Area::create(['nombre' => 'Mostrador', 'departamento_id' => $deptoA->id]);
        $areaB = Area::create(['nombre' => 'Nomina', 'departamento_id' => $deptoB->id]);
        $sucursalA = Sucursal::factory()->create(['nombre' => 'Norte']);
        $sucursalB = Sucursal::factory()->create(['nombre' => 'Sur']);

        $this->actingAs($this->superAdmin)
            ->post(route('admin.usuarios.store'), [
                'name' => 'Nueva',
                'apellido_paterno' => 'Cuenta',
                'username' => 'nueva.relaciones',
                'email' => 'nueva.relaciones@example.com',
                'password' => 'clave-segura',
                'roles_asignados' => ['Colaborador'],
                'permisos_individuales' => [],
                'departamentos' => [$deptoA->id, $deptoB->id],
                'departamento_id' => $deptoB->id,
                'areas' => [$areaA->id, $areaB->id],
                'area_id' => $areaA->id,
                'sucursales' => [$sucursalA->id, $sucursalB->id],
                'sucursal_principal_id' => $sucursalB->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $creado = User::where('username', 'nueva.relaciones')->firstOrFail();
        $this->assertEqualsCanonicalizing([$deptoA->id, $deptoB->id], $creado->departamentos()->pluck('departamentos.id')->all());
        $this->assertSame($deptoB->id, (int) $creado->departamento_id);
        $this->assertEqualsCanonicalizing([$areaA->id, $areaB->id], $creado->areas()->pluck('areas.id')->all());
        $this->assertSame($areaA->id, (int) $creado->area_id);
        $this->assertSame($sucursalB->id, (int) $creado->sucursalPrincipal()?->id);

        $this->actingAs($this->superAdmin)
            ->put(route('admin.usuarios.update', $creado), $this->payload($creado, [
                'departamentos' => [$deptoA->id],
                'departamento_id' => $deptoA->id,
                'areas' => [$areaA->id],
                'area_id' => $areaA->id,
                'sucursales' => [$sucursalA->id],
                'sucursal_principal_id' => $sucursalA->id,
            ]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $creado->refresh();
        $this->assertEqualsCanonicalizing([$deptoA->id], $creado->departamentos()->pluck('departamentos.id')->all());
        $this->assertSame($deptoA->id, (int) $creado->departamento_id);
        $this->assertEqualsCanonicalizing([$areaA->id], $creado->areas()->pluck('areas.id')->all());
        $this->assertSame($sucursalA->id, (int) $creado->sucursalPrincipal()?->id);
        $this->assertFalse($creado->sucursales()->where('sucursales.id', $sucursalB->id)->exists());
    }

    public function test_editar_sin_contrasena_conserva_el_hash_y_los_accesos(): void
    {
        $colaborador = $this->colaboradorDelGerente();
        $hash = $colaborador->password;
        $token = $colaborador->remember_token;
        $colaborador->createToken('mobile:prueba');
        MobileDevice::create([
            'user_id' => $colaborador->id,
            'device_uuid' => 'device-intacto',
            'nombre' => 'Telefono',
            'plataforma' => 'android',
        ]);
        DB::table('sessions')->insert([
            'id' => 'sesion-intacta',
            'user_id' => $colaborador->id,
            'payload' => 'x',
            'last_activity' => time(),
        ]);

        $this->actingAs($this->gerente)
            ->put(route('admin.usuarios.update', $colaborador), $this->payload($colaborador, [
                'password' => '',
                'name' => 'Nombre Editado',
            ]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $colaborador->refresh();
        $this->assertSame('Nombre Editado', $colaborador->name);
        $this->assertSame($hash, $colaborador->password);
        $this->assertTrue(Hash::check('password', $colaborador->password));
        $this->assertSame($token, $colaborador->remember_token);
        $this->assertSame(1, $colaborador->tokens()->count());
        $this->assertNull($colaborador->mobileDevices()->first()->revocado_at);
        $this->assertNotNull(DB::table('sessions')->where('id', 'sesion-intacta')->first());
    }

    public function test_quitar_relaciones_visibles_conserva_las_ocultas_y_el_principal(): void
    {
        $visible = Departamento::create(['nombre' => 'Visible', 'activo' => true]);
        $oculto = Departamento::create(['nombre' => 'Oculto', 'activo' => true]);
        $areaVisible = Area::create(['nombre' => 'Area visible', 'departamento_id' => $visible->id]);
        $areaOculta = Area::create(['nombre' => 'Area oculta', 'departamento_id' => $oculto->id]);
        $sucursalVisible = Sucursal::factory()->create(['nombre' => 'Sucursal visible']);
        $sucursalOculta = Sucursal::factory()->create(['nombre' => 'Sucursal oculta']);

        $this->gerente->departamentos()->attach($visible->id);
        $this->gerente->areas()->attach($areaVisible->id);
        $this->gerente->concederAccesoSucursal($sucursalVisible, true);

        $colaborador = $this->colaboradorDelGerente();
        $colaborador->departamentos()->attach([$visible->id, $oculto->id]);
        $colaborador->areas()->attach([$areaVisible->id, $areaOculta->id]);
        $colaborador->concederAccesoSucursal($sucursalVisible, false);
        $colaborador->concederAccesoSucursal($sucursalOculta, true);
        $colaborador->update([
            'departamento_id' => $visible->id,
            'area_id' => $areaVisible->id,
        ]);

        $this->actingAs($this->gerente)
            ->put(route('admin.usuarios.update', $colaborador), $this->payload($colaborador, [
                'name' => 'Solo ocultas',
                'departamentos' => [],
                'departamento_id' => $oculto->id,
                'areas' => [],
                'area_id' => $areaOculta->id,
                'sucursales' => [],
                'sucursal_principal_id' => $sucursalOculta->id,
            ]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $colaborador->refresh();
        $this->assertSame('Solo ocultas', $colaborador->name);
        $this->assertEqualsCanonicalizing([$oculto->id], $colaborador->departamentos()->pluck('departamentos.id')->all());
        $this->assertSame($oculto->id, (int) $colaborador->departamento_id);
        $this->assertEqualsCanonicalizing([$areaOculta->id], $colaborador->areas()->pluck('areas.id')->all());
        $this->assertSame($areaOculta->id, (int) $colaborador->area_id);
        $this->assertEqualsCanonicalizing([$sucursalOculta->id], $colaborador->idsSucursalesOperables()->all());
        $this->assertSame($sucursalOculta->id, (int) $colaborador->sucursalPrincipal()?->id);
    }

    public function test_un_principal_visible_retirado_no_borra_la_relacion_oculta(): void
    {
        $visible = Departamento::create(['nombre' => 'Visible principal', 'activo' => true]);
        $oculto = Departamento::create(['nombre' => 'Oculto restante', 'activo' => true]);
        $sucursalVisible = Sucursal::factory()->create(['nombre' => 'Principal visible']);
        $sucursalOculta = Sucursal::factory()->create(['nombre' => 'Principal oculta']);
        $this->gerente->departamentos()->attach($visible->id);
        $this->gerente->concederAccesoSucursal($sucursalVisible, true);

        $colaborador = $this->colaboradorDelGerente();
        $colaborador->departamentos()->attach([$visible->id, $oculto->id]);
        $colaborador->concederAccesoSucursal($sucursalVisible, true);
        $colaborador->concederAccesoSucursal($sucursalOculta, false);
        $colaborador->update(['departamento_id' => $visible->id]);
        $nombre = $colaborador->name;

        $this->actingAs($this->gerente)
            ->from(route('admin.usuarios'))
            ->put(route('admin.usuarios.update', $colaborador), $this->payload($colaborador, [
                'name' => 'No debe guardarse',
                'departamentos' => [],
                'departamento_id' => $oculto->id,
                'sucursales' => [],
                'sucursal_principal_id' => $sucursalVisible->id,
            ]))
            ->assertRedirect()
            ->assertSessionHasErrors('sucursal_principal_id');

        $colaborador->refresh();
        $this->assertSame($nombre, $colaborador->name);
        $this->assertEqualsCanonicalizing(
            [$visible->id, $oculto->id],
            $colaborador->departamentos()->pluck('departamentos.id')->all()
        );
        $this->assertTrue($colaborador->idsSucursalesOperables()->contains($sucursalOculta->id));
    }

    public function test_reenviar_un_id_ajeno_sigue_en_422_de_autorizacion(): void
    {
        $propio = Departamento::create(['nombre' => 'Propio gerente', 'activo' => true]);
        $ajeno = Departamento::create(['nombre' => 'Ajeno gerente', 'activo' => true]);
        $this->gerente->departamentos()->attach($propio->id);
        $colaborador = $this->colaboradorDelGerente();
        $colaborador->departamentos()->attach($propio->id);

        $this->actingAs($this->gerente)
            ->from(route('admin.usuarios'))
            ->put(route('admin.usuarios.update', $colaborador), $this->payload($colaborador, [
                'departamentos' => [$propio->id, $ajeno->id],
            ]))
            ->assertSessionHasErrors('departamentos');

        $this->assertEqualsCanonicalizing(
            [$propio->id],
            $colaborador->fresh()->departamentos()->pluck('departamentos.id')->all()
        );
    }

    public function test_correo_duplicado_no_cambia_el_resto_del_usuario(): void
    {
        $ocupado = User::factory()->create([
            'username' => 'correo.ocupado',
            'email' => 'ocupado@example.com',
            'apellido_paterno' => 'Ocupado',
        ]);
        $colaborador = $this->colaboradorDelGerente();
        $nombre = $colaborador->name;

        $this->actingAs($this->superAdmin)
            ->from(route('admin.usuarios'))
            ->put(route('admin.usuarios.update', $colaborador), $this->payload($colaborador, [
                'name' => 'Nombre que no persiste',
                'email' => $ocupado->email,
            ]))
            ->assertRedirect()
            ->assertSessionHasErrors('email');

        $this->assertSame($nombre, $colaborador->fresh()->name);
    }

    public function test_256_caracteres_los_rechaza_la_validacion_antes_de_escribir(): void
    {
        $colaborador = $this->colaboradorDelGerente();
        $nombre = $colaborador->name;

        $respuesta = $this->actingAs($this->superAdmin)
            ->from(route('admin.usuarios'))
            ->put(route('admin.usuarios.update', $colaborador), $this->payload($colaborador, [
                'name' => str_repeat('A', 256),
            ]));

        $respuesta->assertRedirect();
        $respuesta->assertSessionHasErrors('name');
        $this->assertNotSame(500, $respuesta->status());
        $this->assertSame($nombre, $colaborador->fresh()->name);
    }

    public function test_255_caracteres_caben_en_la_columna_cuando_la_validacion_los_acepta(): void
    {
        $colaborador = $this->colaboradorDelGerente();
        $nombre = str_repeat('B', 255);

        $this->actingAs($this->superAdmin)
            ->put(route('admin.usuarios.update', $colaborador), $this->payload($colaborador, [
                'name' => $nombre,
            ]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame($nombre, $colaborador->fresh()->name);
        $this->assertSame(255, mb_strlen($colaborador->fresh()->name));
    }

    public function test_sqlite_no_impone_el_varchar_255_que_si_exige_la_validacion(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        $this->assertSame('sqlite', $driver);

        $largo = str_repeat('C', 300);
        $usuario = User::factory()->create([
            'name' => $largo,
            'username' => 'varchar.sqlite',
            'apellido_paterno' => 'Largo',
        ]);

        $this->assertSame(300, mb_strlen($usuario->fresh()->name));
    }

    public function test_un_fallo_posterior_revierte_la_revocacion_de_accesos(): void
    {
        $colaborador = $this->colaboradorDelGerente();
        $hash = $colaborador->password;
        $token = $colaborador->remember_token;
        $colaborador->createToken('mobile:prueba');
        $dispositivo = MobileDevice::create([
            'user_id' => $colaborador->id,
            'device_uuid' => 'device-rollback',
            'nombre' => 'Telefono',
            'plataforma' => 'android',
        ]);
        DB::table('sessions')->insert([
            'id' => 'sesion-rollback',
            'user_id' => $colaborador->id,
            'payload' => 'x',
            'last_activity' => time(),
        ]);

        AuditoriaConfiguracion::creating(function () {
            throw new RuntimeException('fallo posterior al cambio de contraseña');
        });

        $respuesta = $this->actingAs($this->gerente)
            ->put(route('admin.usuarios.update', $colaborador), $this->payload($colaborador, [
                'password' => 'clave-segura',
                'name' => 'No debe quedar',
            ]));

        $respuesta->assertStatus(500);

        $colaborador->refresh();
        $dispositivo->refresh();
        $this->assertNotSame('No debe quedar', $colaborador->name);
        $this->assertSame($hash, $colaborador->password);
        $this->assertSame($token, $colaborador->remember_token);
        $this->assertSame(1, $colaborador->tokens()->count());
        $this->assertNull($dispositivo->revocado_at);
        $this->assertNotNull(DB::table('sessions')->where('id', 'sesion-rollback')->first());
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
