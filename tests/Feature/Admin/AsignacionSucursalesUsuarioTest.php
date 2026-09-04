<?php

namespace Tests\Feature\Admin;

use App\Models\Sucursal;
use App\Models\User;
use App\Services\PuntoVenta\AlcancePdv;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AsignacionSucursalesUsuarioTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Sucursal $sucursalA;

    private Sucursal $sucursalB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            ValidateCsrfToken::class,
            PreventRequestForgery::class,
        ]);

        $permiso = Permission::findOrCreate('usuarios.gestionar', 'web');
        $rol = Role::findOrCreate('Super Admin', 'web');
        $rol->givePermissionTo($permiso);

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $this->admin = User::factory()->create();
        $this->admin->assignRole($rol);

        $this->sucursalA = Sucursal::factory()->create(['nombre' => 'Sucursal Norte']);
        $this->sucursalB = Sucursal::factory()->create(['nombre' => 'Sucursal Sur']);
    }

    public function test_actualizar_usuario_sincroniza_sucursales_y_principal(): void
    {
        $colaborador = User::factory()->create([
            'username' => 'operador.pdv',
            'apellido_paterno' => 'Paterno',
        ]);

        $this->actingAs($this->admin)
            ->put(route('admin.usuarios.update', $colaborador), [
                'name' => $colaborador->name,
                'apellido_paterno' => $colaborador->apellido_paterno ?? 'Paterno',
                'apellido_materno' => $colaborador->apellido_materno,
                'username' => $colaborador->username,
                'email' => $colaborador->email,
                'roles_asignados' => [],
                'permisos_individuales' => [],
                'sucursales' => [$this->sucursalA->id, $this->sucursalB->id],
                'sucursal_principal_id' => $this->sucursalB->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $colaborador->refresh();

        $this->assertSame(
            [$this->sucursalA->id, $this->sucursalB->id],
            $colaborador->idsSucursalesOperables()->sort()->values()->all()
        );
        $this->assertTrue($this->sucursalB->is($colaborador->sucursalPrincipal()));
        $this->assertSame(
            $this->sucursalB->id,
            app(AlcancePdv::class)->sucursalActivaId($colaborador)
        );
    }

    public function test_rechaza_varias_sucursales_sin_principal(): void
    {
        $colaborador = User::factory()->create([
            'username' => 'colaborador.pdv',
            'apellido_paterno' => 'Paterno',
        ]);

        $this->actingAs($this->admin)
            ->from(route('admin.usuarios'))
            ->put(route('admin.usuarios.update', $colaborador), [
                'name' => $colaborador->name,
                'apellido_paterno' => $colaborador->apellido_paterno ?? 'Paterno',
                'username' => $colaborador->username,
                'email' => $colaborador->email,
                'roles_asignados' => [],
                'permisos_individuales' => [],
                'sucursales' => [$this->sucursalA->id, $this->sucursalB->id],
            ])
            ->assertSessionHasErrors('sucursal_principal_id');
    }
}
