<?php

namespace Tests\Feature\Api;

use App\Models\CatalogoListaDescuento;
use App\Models\Cliente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClienteApiFiscalesTest extends TestCase
{
    use RefreshDatabase;

    public function test_busqueda_sin_con_fiscales_omite_rfc_y_razon_social(): void
    {
        $lista = CatalogoListaDescuento::create([
            'nombre' => 'Lista',
            'monto_requerido' => 0,
            'activo' => true,
        ]);

        Cliente::create([
            'numero_cliente' => '1001',
            'nombre' => 'Cliente Busqueda',
            'rfc' => 'XAXX010101000',
            'nombre_razon_social' => 'Razon Oculta SA',
            'lista_actual_id' => $lista->id,
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/clientes?q=Cliente');

        $response->assertOk();
        $fila = $response->json('0');
        $this->assertNotNull($fila);
        $this->assertSame('Cliente Busqueda', $fila['nombre']);
        $this->assertArrayNotHasKey('rfc', $fila);
        $this->assertArrayNotHasKey('nombre_razon_social', $fila);
    }

    public function test_busqueda_con_fiscales_incluye_rfc_y_razon_social(): void
    {
        $lista = CatalogoListaDescuento::create([
            'nombre' => 'Lista',
            'monto_requerido' => 0,
            'activo' => true,
        ]);

        Cliente::create([
            'numero_cliente' => '1002',
            'nombre' => 'Cliente Fiscal',
            'rfc' => 'XAXX010101000',
            'nombre_razon_social' => 'Razon Visible SA',
            'lista_actual_id' => $lista->id,
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/clientes?q=Cliente&con_fiscales=1');

        $response->assertOk();
        $fila = $response->json('0');
        $this->assertNotNull($fila);
        $this->assertSame('XAXX010101000', $fila['rfc']);
        $this->assertSame('Razon Visible SA', $fila['nombre_razon_social']);
    }
}
