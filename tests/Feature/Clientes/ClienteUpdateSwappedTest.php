<?php

namespace Tests\Feature\Clientes;

use App\Models\CatalogoListaDescuento;
use App\Models\Cliente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ClienteUpdateSwappedTest extends TestCase
{
    use RefreshDatabase;

    private function admin(array $extraPermisos = []): User
    {
        Permission::findOrCreate('clientes.ver', 'web');
        Permission::findOrCreate('clientes.correccion_emergencia', 'web');
        $role = Role::findOrCreate('admin', 'web');
        $role->givePermissionTo(array_merge(['clientes.ver'], $extraPermisos));

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

    private function payloadCorreccion(int $listaId, bool $emergencia = false): array
    {
        return [
            'numero_cliente' => '8930',
            'nombre' => 'HUB VICTORIANO HERNANDEZ ALAN',
            'vendedor_id' => null,
            'catalogo_tipo_cliente_id' => null,
            'monto_venta_actual' => 10180.5,
            'lista_actual_id' => $listaId,
            'lista_bloqueada' => false,
            'es_inactivo' => false,
            'correccion_emergencia' => $emergencia,
        ];
    }

    public function test_corregir_campos_intercambiados_sin_conflicto_unico(): void
    {
        $lista = $this->listaPg();

        $mal = Cliente::create([
            'numero_cliente' => 'HUB VICTORIANO HERNANDEZ ALAN',
            'nombre' => '8930',
            'lista_actual_id' => $lista->id,
            'monto_venta_actual' => 10180.5,
        ]);

        $response = $this->actingAs($this->admin())
            ->withHeaders(['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest'])
            ->put(route('admin.clientes.update', $mal), $this->payloadCorreccion($lista->id));

        $response->assertRedirect();
        $mal->refresh();
        $this->assertEquals('8930', $mal->numero_cliente);
        $this->assertEquals('HUB VICTORIANO HERNANDEZ ALAN', $mal->nombre);
    }

    public function test_duplicado_numero_devuelve_422_no_500_en_inertia(): void
    {
        $lista = $this->listaPg();

        Cliente::create([
            'numero_cliente' => '8930',
            'nombre' => 'CLIENTE LEGITIMO',
            'lista_actual_id' => $lista->id,
        ]);

        $mal = Cliente::create([
            'numero_cliente' => 'HUB VICTORIANO HERNANDEZ ALAN',
            'nombre' => '8930',
            'lista_actual_id' => $lista->id,
        ]);

        $response = $this->actingAs($this->admin())
            ->withHeaders(['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest'])
            ->put(route('admin.clientes.update', $mal), $this->payloadCorreccion($lista->id));

        $this->assertNotEquals(500, $response->status());
        $response->assertSessionHasErrors('numero_cliente');
    }

    public function test_correccion_emergencia_intercambia_numero_con_conflicto(): void
    {
        $lista = $this->listaPg();

        $legitimo = Cliente::create([
            'numero_cliente' => '8930',
            'nombre' => 'CLIENTE LEGITIMO',
            'lista_actual_id' => $lista->id,
        ]);

        $mal = Cliente::create([
            'numero_cliente' => 'HUB VICTORIANO HERNANDEZ ALAN',
            'nombre' => '8930',
            'lista_actual_id' => $lista->id,
        ]);

        $response = $this->actingAs($this->admin(['clientes.correccion_emergencia']))
            ->withHeaders(['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest'])
            ->put(route('admin.clientes.update', $mal), $this->payloadCorreccion($lista->id, true));

        $response->assertRedirect();
        $mal->refresh();
        $legitimo->refresh();

        $this->assertEquals('8930', $mal->numero_cliente);
        $this->assertEquals('HUB VICTORIANO HERNANDEZ ALAN', $mal->nombre);
        $this->assertEquals('HUB VICTORIANO HERNANDEZ ALAN', $legitimo->numero_cliente);
    }
}
