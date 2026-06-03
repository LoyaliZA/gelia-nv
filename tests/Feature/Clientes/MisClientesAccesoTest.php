<?php

namespace Tests\Feature\Clientes;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MisClientesAccesoTest extends TestCase
{
    use RefreshDatabase;

    public function test_sin_permiso_mis_clientes_responde_403(): void
    {
        Permission::findOrCreate('clientes.ver', 'web');
        $role = Role::findOrCreate('colaborador', 'web');
        $role->givePermissionTo('clientes.ver');

        $user = User::factory()->create();
        $user->assignRole($role);

        $this->actingAs($user)
            ->get(route('mis_clientes.index'))
            ->assertForbidden();
    }

    public function test_con_permiso_mis_clientes_accede(): void
    {
        Permission::findOrCreate('mis_clientes.gestionar', 'web');
        $role = Role::findOrCreate('colaborador', 'web');
        $role->givePermissionTo('mis_clientes.gestionar');

        $user = User::factory()->create();
        $user->assignRole($role);

        $response = $this->actingAs($user)->get(route('mis_clientes.index'));

        $response->assertOk();
        $response->assertViewIs('app');
    }
}
