<?php

namespace Tests\Feature\Mobile;

use App\Models\CatalogoListaDescuento;
use App\Models\Cliente;
use App\Models\User;
use App\Services\Mobile\MobileScopeVersionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MobileClienteSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_vendedor_solo_busca_sus_clientes(): void
    {
        $vendedor = $this->usuarioMovil(['mis_clientes.gestionar']);
        $otro = User::factory()->create();
        $lista = $this->lista();

        $propio = Cliente::create([
            'numero_cliente' => '201',
            'nombre' => 'Farmacia Central',
            'lista_actual_id' => $lista->id,
            'vendedor_id' => $vendedor->id,
            'vendedor_original_id' => $vendedor->id,
        ]);
        Cliente::create([
            'numero_cliente' => '202',
            'nombre' => 'Farmacia Ajena',
            'lista_actual_id' => $lista->id,
            'vendedor_id' => $otro->id,
            'vendedor_original_id' => $otro->id,
        ]);

        $response = $this->withHeaders($this->headers($vendedor))
            ->getJson('/api/v1/mobile/clientes?q=Farmacia')
            ->assertOk()
            ->json();

        $this->assertSame(1, $response['meta']['total']);
        $this->assertSame($propio->id, $response['data'][0]['id']);
    }

    public function test_clientes_ver_busca_todos(): void
    {
        $admin = $this->usuarioMovil(['clientes.ver']);
        $lista = $this->lista();

        Cliente::create([
            'numero_cliente' => '301',
            'nombre' => 'Abarrotes Norte',
            'lista_actual_id' => $lista->id,
        ]);
        Cliente::create([
            'numero_cliente' => '302',
            'nombre' => 'Abarrotes Sur',
            'lista_actual_id' => $lista->id,
        ]);

        $this->withHeaders($this->headers($admin))
            ->getJson('/api/v1/mobile/clientes?q=Abarrotes')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
    }

    public function test_busqueda_por_nombre_es_paginada(): void
    {
        $admin = $this->usuarioMovil(['clientes.ver']);
        $lista = $this->lista();

        for ($i = 1; $i <= 12; $i++) {
            Cliente::create([
                'numero_cliente' => (string) (500 + $i),
                'nombre' => 'Tienda Paginada '.$i,
                'lista_actual_id' => $lista->id,
            ]);
        }

        $page1 = $this->withHeaders($this->headers($admin))
            ->getJson('/api/v1/mobile/clientes?q=Paginada&page=1&per_page=10')
            ->assertOk()
            ->json();

        $this->assertCount(10, $page1['data']);
        $this->assertSame(12, $page1['meta']['total']);
        $this->assertSame(2, $page1['meta']['last_page']);

        $page2 = $this->withHeaders($this->headers($admin))
            ->getJson('/api/v1/mobile/clientes?q=Paginada&page=2&per_page=10')
            ->assertOk()
            ->json();

        $this->assertCount(2, $page2['data']);
    }

    public function test_show_devuelve_cliente_autorizado(): void
    {
        $vendedor = $this->usuarioMovil(['mis_clientes.gestionar']);
        $lista = $this->lista();
        $cliente = Cliente::create([
            'numero_cliente' => '401',
            'nombre' => 'Cliente Visible',
            'lista_actual_id' => $lista->id,
            'vendedor_id' => $vendedor->id,
            'vendedor_original_id' => $vendedor->id,
        ]);

        $this->withHeaders($this->headers($vendedor))
            ->getJson('/api/v1/mobile/clientes/401')
            ->assertOk()
            ->assertJsonPath('data.id', $cliente->id);
    }

    public function test_show_acepta_numero_de_un_digito(): void
    {
        $vendedor = $this->usuarioMovil(['mis_clientes.gestionar']);
        $lista = $this->lista();
        $cliente = Cliente::create([
            'numero_cliente' => '7',
            'nombre' => 'Cliente Corto',
            'lista_actual_id' => $lista->id,
            'vendedor_id' => $vendedor->id,
            'vendedor_original_id' => $vendedor->id,
        ]);

        $this->withHeaders($this->headers($vendedor))
            ->getJson('/api/v1/mobile/clientes/7')
            ->assertOk()
            ->assertJsonPath('data.id', $cliente->id)
            ->assertJsonPath('data.numero_cliente', '7');
    }

    public function test_show_devuelve_404_fuera_de_alcance(): void
    {
        $vendedor = $this->usuarioMovil(['mis_clientes.gestionar']);
        $otro = User::factory()->create();
        $lista = $this->lista();

        Cliente::create([
            'numero_cliente' => '501',
            'nombre' => 'Cliente Ajeno',
            'lista_actual_id' => $lista->id,
            'vendedor_id' => $otro->id,
            'vendedor_original_id' => $otro->id,
        ]);

        $this->withHeaders($this->headers($vendedor))
            ->getJson('/api/v1/mobile/clientes/501')
            ->assertNotFound();
    }

    public function test_busqueda_requiere_minimo_dos_caracteres(): void
    {
        $admin = $this->usuarioMovil(['clientes.ver']);

        $this->withHeaders($this->headers($admin))
            ->getJson('/api/v1/mobile/clientes?q=a')
            ->assertStatus(422);
    }

    /**
     * @param  array<int, string>  $permisos
     */
    private function usuarioMovil(array $permisos): User
    {
        $user = User::factory()->create(['password' => 'secret123']);
        foreach ($permisos as $permiso) {
            Permission::findOrCreate($permiso, 'web');
            $user->givePermissionTo($permiso);
        }

        return $user;
    }

    /**
     * @return array<string, string>
     */
    private function headers(User $user): array
    {
        $token = $this->postJson('/api/v1/mobile/login', [
            'login' => $user->email,
            'password' => 'secret123',
            'device_uuid' => 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb',
        ])->json('access_token');

        return [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ];
    }

    private function lista(): CatalogoListaDescuento
    {
        return CatalogoListaDescuento::first() ?? CatalogoListaDescuento::create([
            'nombre' => 'PUBLICO GENERAL',
            'monto_requerido' => 0,
            'activo' => true,
        ]);
    }
}
