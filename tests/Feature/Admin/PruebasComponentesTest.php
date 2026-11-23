<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PruebasComponentesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_sin_permiso_no_entra(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.pruebas_componentes.index'))
            ->assertForbidden();
    }

    public function test_con_permiso_entra(): void
    {
        Permission::findOrCreate('sistema.pruebas_componentes.ver', 'web');
        $usuario = User::factory()->create();
        $usuario->givePermissionTo('sistema.pruebas_componentes.ver');

        $this->actingAs($usuario)
            ->get(route('admin.pruebas_componentes.index'))
            ->assertOk();
    }
}
