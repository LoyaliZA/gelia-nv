<?php

namespace Tests\Feature\Clientes;

use App\Models\CatalogoListaDescuento;
use App\Models\Cliente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ClienteUpdateValidationInertiaTest extends TestCase
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

    public function test_vendedor_id_vacio_no_devuelve_500_en_inertia(): void
    {
        $lista = CatalogoListaDescuento::firstOrCreate(
            ['nombre' => 'PUBLICO GENERAL'],
            ['monto_requerido' => 0, 'activo' => true]
        );

        $cliente = Cliente::create([
            'numero_cliente' => '9001',
            'nombre' => 'Cliente Test',
            'lista_actual_id' => $lista->id,
        ]);

        $response = $this->actingAs($this->admin())
            ->withHeaders(['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest'])
            ->put(route('admin.clientes.update', $cliente), [
                'numero_cliente' => '9001',
                'nombre' => 'Cliente con nombre válido',
                'vendedor_id' => '',
                'catalogo_tipo_cliente_id' => '',
                'monto_venta_actual' => 0,
                'lista_actual_id' => '',
                'lista_bloqueada' => false,
                'es_inactivo' => false,
            ]);

        $response->assertRedirect();
        $this->assertNotEquals(500, $response->status(), 'Inertia no debe mostrar Error 500 por validación');
    }
}
