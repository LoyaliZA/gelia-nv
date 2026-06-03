<?php

namespace Tests\Feature\Clientes;

use App\Models\CatalogoListaDescuento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NumeroClienteSoloDigitosTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Permission::findOrCreate('clientes.ver', 'web');
        $role = Role::findOrCreate('admin', 'web');
        $role->givePermissionTo('clientes.ver');

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_store_rechaza_numero_con_letras(): void
    {
        $lista = CatalogoListaDescuento::firstOrCreate(
            ['nombre' => 'PUBLICO GENERAL'],
            ['monto_requerido' => 0, 'activo' => true]
        );

        $response = $this->actingAs($this->admin())
            ->post(route('admin.clientes.store'), [
                'numero_cliente' => 'ABC123',
                'nombre' => 'Cliente Prueba',
                'lista_actual_id' => $lista->id,
            ]);

        $response->assertSessionHasErrors('numero_cliente');
    }

    public function test_registro_rapido_acepta_solo_digitos(): void
    {
        Permission::findOrCreate('mis_clientes.gestionar', 'web');
        $role = Role::findOrCreate('colaborador', 'web');
        $role->givePermissionTo('mis_clientes.gestionar');

        CatalogoListaDescuento::firstOrCreate(
            ['nombre' => 'PUBLICO GENERAL'],
            ['monto_requerido' => 0, 'activo' => true]
        );

        $user = User::factory()->create();
        $user->assignRole($role);

        $response = $this->actingAs($user)
            ->post(route('mis_clientes.rapido'), [
                'numero_cliente' => '99001',
                'nombre' => 'Prospecto Nuevo',
            ]);

        $response->assertRedirect(route('mis_clientes.index'));
        $this->assertDatabaseHas('clientes', [
            'numero_cliente' => '99001',
            'nombre' => 'Prospecto Nuevo',
        ]);
    }
}
