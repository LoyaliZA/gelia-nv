<?php

namespace Tests\Feature\Clientes;

use App\Models\CatalogoListaDescuento;
use App\Models\Cliente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminClientesOrdenacionTest extends TestCase
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

    private function crearCliente(string $numero, float $monto): void
    {
        Cliente::create([
            'numero_cliente' => $numero,
            'nombre' => "Cliente {$numero}",
            'lista_actual_id' => $this->listaPg()->id,
            'monto_venta_actual' => $monto,
        ]);
    }

    public function test_orden_por_numero_ascendente(): void
    {
        $this->crearCliente('10', 0);
        $this->crearCliente('2', 0);
        $this->crearCliente('100', 0);

        $response = $this->actingAs($this->usuarioAdmin())
            ->get(route('admin.clientes', ['orden' => 'numero_asc']));

        $response->assertOk();
        $clientes = $response->original->getData()['page']['props']['clientes']['data'];
        $numeros = array_column($clientes, 'numero_cliente');

        $this->assertEquals(['2', '10', '100'], $numeros);
    }

    public function test_orden_por_numero_descendente(): void
    {
        $this->crearCliente('10', 0);
        $this->crearCliente('2', 0);
        $this->crearCliente('100', 0);

        $response = $this->actingAs($this->usuarioAdmin())
            ->get(route('admin.clientes', ['orden' => 'numero_desc']));

        $response->assertOk();
        $clientes = $response->original->getData()['page']['props']['clientes']['data'];
        $numeros = array_column($clientes, 'numero_cliente');

        $this->assertEquals(['100', '10', '2'], $numeros);
    }

    public function test_orden_por_monto_descendente(): void
    {
        $this->crearCliente('1', 100);
        $this->crearCliente('2', 500);
        $this->crearCliente('3', 50);

        $response = $this->actingAs($this->usuarioAdmin())
            ->get(route('admin.clientes', ['orden' => 'monto_desc']));

        $response->assertOk();
        $clientes = $response->original->getData()['page']['props']['clientes']['data'];
        $montos = array_column($clientes, 'monto_venta_actual');

        $this->assertEquals(['500.00', '100.00', '50.00'], array_map(fn ($m) => number_format((float) $m, 2, '.', ''), $montos));
    }

    public function test_filtro_solo_inactivos(): void
    {
        $lista = $this->listaPg();

        Cliente::create([
            'numero_cliente' => 'A1',
            'nombre' => 'Activo',
            'lista_actual_id' => $lista->id,
            'es_inactivo' => false,
        ]);

        Cliente::create([
            'numero_cliente' => 'I1',
            'nombre' => 'Inactivo',
            'lista_actual_id' => $lista->id,
            'es_inactivo' => true,
        ]);

        $response = $this->actingAs($this->usuarioAdmin())
            ->get(route('admin.clientes', ['estado' => 'inactivos']));

        $response->assertOk();
        $clientes = $response->original->getData()['page']['props']['clientes']['data'];

        $this->assertCount(1, $clientes);
        $this->assertEquals('I1', $clientes[0]['numero_cliente']);
        $this->assertTrue($clientes[0]['es_inactivo']);
    }
}
