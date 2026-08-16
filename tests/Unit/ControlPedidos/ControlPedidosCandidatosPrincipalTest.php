<?php

namespace Tests\Unit\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ControlPedidosCandidatosPrincipalTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    private int $clienteId;

    private CatalogoEstatusPedido $estatusBorrador;

    private CatalogoEstatusPedido $estatusCedis;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::findOrCreate('control_pedidos.crear', 'web');
        $this->usuario = User::factory()->create();
        $this->usuario->givePermissionTo('control_pedidos.crear');

        $now = now();
        if (! DB::table('catalogo_listas_descuento')->exists()) {
            DB::table('catalogo_listas_descuento')->insert([
                'nombre' => 'Lista Test', 'activo' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        $this->clienteId = (int) DB::table('clientes')->insertGetId([
            'numero_cliente' => 'C-PRIN-'.uniqid(),
            'nombre' => 'Cliente Principal Test',
            'lista_actual_id' => DB::table('catalogo_listas_descuento')->value('id'),
            'vendedor_id' => $this->usuario->id,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->estatusBorrador = CatalogoEstatusPedido::create([
            'codigo_interno' => 'BORRADOR_CP',
            'nombre_visual' => 'Borrador',
            'color_hex' => '#94A3B8',
            'fase_ciclo' => CatalogoEstatusPedido::FASE_BORRADOR,
            'orden' => 1,
            'activo' => true,
        ]);

        $this->estatusCedis = CatalogoEstatusPedido::create([
            'codigo_interno' => 'CEDIS_CP',
            'nombre_visual' => 'En CEDIS',
            'color_hex' => '#EAB308',
            'fase_ciclo' => CatalogoEstatusPedido::FASE_EN_CEDIS,
            'orden' => 3,
            'activo' => true,
        ]);
    }

    public function test_incluye_pedido_normal_y_resguardo_excluye_complemento(): void
    {
        $normal = $this->crearPedido([
            'folio' => 'PED-NORM-1',
            'folio_remision' => 'REM-NORM-1',
            'es_resguardo' => false,
            'fecha' => '2026-08-01',
            'catalogo_estatus_pedido_id' => $this->estatusBorrador->id,
        ]);

        $resguardo = $this->crearPedido([
            'folio' => 'PED-RESG-1',
            'folio_remision' => 'REM-RESG-1',
            'es_resguardo' => true,
            'fecha' => '2026-08-02',
            'catalogo_estatus_pedido_id' => $this->estatusBorrador->id,
        ]);

        $complemento = $this->crearPedido([
            'folio' => 'PED-COMP-1',
            'folio_remision' => 'REM-COMP-1',
            'es_resguardo' => true,
            'pedido_principal_id' => $normal->id,
            'fecha' => '2026-08-03',
            'catalogo_estatus_pedido_id' => $this->estatusBorrador->id,
        ]);

        $response = $this->actingAs($this->usuario)
            ->getJson(route('control_pedidos.candidatos_principal', [
                'cliente_id' => $this->clienteId,
            ]));

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($normal->id, $ids);
        $this->assertContains($resguardo->id, $ids);
        $this->assertNotContains($complemento->id, $ids);

        $row = collect($response->json('data'))->firstWhere('id', $normal->id);
        $this->assertSame('PED-NORM-1', $row['folio']);
        $this->assertSame('REM-NORM-1', $row['folio_remision']);
        $this->assertArrayHasKey('fecha', $row);
    }

    public function test_filtro_fase_ciclo(): void
    {
        $borrador = $this->crearPedido([
            'folio' => 'PED-FASE-B',
            'fecha' => '2026-08-01',
            'catalogo_estatus_pedido_id' => $this->estatusBorrador->id,
        ]);
        $cedis = $this->crearPedido([
            'folio' => 'PED-FASE-C',
            'fecha' => '2026-08-01',
            'catalogo_estatus_pedido_id' => $this->estatusCedis->id,
        ]);

        $response = $this->actingAs($this->usuario)
            ->getJson(route('control_pedidos.candidatos_principal', [
                'cliente_id' => $this->clienteId,
                'fase_ciclo' => CatalogoEstatusPedido::FASE_EN_CEDIS,
            ]));

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($cedis->id, $ids);
        $this->assertNotContains($borrador->id, $ids);
    }

    public function test_filtro_fecha(): void
    {
        $julio = $this->crearPedido([
            'folio' => 'PED-JUL',
            'fecha' => '2026-07-15',
            'catalogo_estatus_pedido_id' => $this->estatusBorrador->id,
        ]);
        $agosto = $this->crearPedido([
            'folio' => 'PED-AGO',
            'fecha' => '2026-08-10',
            'catalogo_estatus_pedido_id' => $this->estatusBorrador->id,
        ]);

        $response = $this->actingAs($this->usuario)
            ->getJson(route('control_pedidos.candidatos_principal', [
                'cliente_id' => $this->clienteId,
                'fecha_desde' => '2026-08-01',
                'fecha_hasta' => '2026-08-31',
            ]));

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($agosto->id, $ids);
        $this->assertNotContains($julio->id, $ids);
    }

    private function crearPedido(array $overrides): PedidoBma
    {
        return PedidoBma::create(array_merge([
            'folio' => 'PED-'.uniqid(),
            'fecha' => now()->toDateString(),
            'vendedor_id' => $this->usuario->id,
            'cliente_id' => $this->clienteId,
            'catalogo_estatus_pedido_id' => $this->estatusBorrador->id,
            'total_mercancia' => 100,
            'costo_envio' => 0,
            'es_resguardo' => false,
        ], $overrides));
    }
}
