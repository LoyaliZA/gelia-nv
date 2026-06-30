<?php

namespace Tests\Feature\Clientes;

use App\Models\CatalogoListaDescuento;
use App\Models\Cliente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminClientesBusquedaTest extends TestCase
{
    use RefreshDatabase;

    private function usuarioAdmin(): User
    {
        Permission::findOrCreate('clientes.ver', 'web');
        $role = Role::findOrCreate('admin', 'web');
        $role->givePermissionTo('clientes.ver');

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function listaPg(): CatalogoListaDescuento
    {
        return CatalogoListaDescuento::firstOrCreate(
            ['nombre' => 'PUBLICO GENERAL'],
            ['monto_requerido' => 0, 'activo' => true]
        );
    }

    public function test_busqueda_por_nombre_filtra_resultados(): void
    {
        Cliente::create([
            'numero_cliente' => '100',
            'nombre' => 'ACME Corporation',
            'lista_actual_id' => $this->listaPg()->id,
            'monto_venta_actual' => 0,
        ]);
        Cliente::create([
            'numero_cliente' => '200',
            'nombre' => 'Otra Empresa',
            'lista_actual_id' => $this->listaPg()->id,
            'monto_venta_actual' => 0,
        ]);

        $user = $this->usuarioAdmin();

        $response = $this->actingAs($user)->get(route('admin.clientes', ['q' => 'ACME']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Clientes')
            ->has('clientes.data', 1)
            ->where('clientes.data.0.nombre', 'ACME Corporation')
            ->where('filtros.q', 'ACME')
        );
    }

    public function test_busqueda_por_numero_cliente(): void
    {
        Cliente::create([
            'numero_cliente' => '98765',
            'nombre' => 'Cliente Numérico',
            'lista_actual_id' => $this->listaPg()->id,
            'monto_venta_actual' => 0,
        ]);
        Cliente::create([
            'numero_cliente' => '11111',
            'nombre' => 'Otro Cliente',
            'lista_actual_id' => $this->listaPg()->id,
            'monto_venta_actual' => 0,
        ]);

        $user = $this->usuarioAdmin();

        $response = $this->actingAs($user)->get(route('admin.clientes', ['q' => '987']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('clientes.data', 1)
            ->where('clientes.data.0.numero_cliente', '98765')
        );
    }

    public function test_tab_auditoria_en_filtros(): void
    {
        $user = $this->usuarioAdmin();

        $response = $this->actingAs($user)->get(route('admin.clientes', ['tab' => 'auditoria']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('filtros.tab', 'auditoria')
            ->where('puedeDescargarImportaciones', false)
        );
    }
}
