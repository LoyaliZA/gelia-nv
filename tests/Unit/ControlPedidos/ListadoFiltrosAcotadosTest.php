<?php

namespace Tests\Unit\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\User;
use App\Services\ControlPedidos\ListarPedidosBmaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListadoFiltrosAcotadosTest extends TestCase
{
    use RefreshDatabase;

    private function pedido(User $vendedora, string $fecha): PedidoBma
    {
        static $estatus = null;
        $estatus ??= CatalogoEstatusPedido::create([
            'codigo_interno' => 'AUX_FILTROS',
            'nombre_visual' => 'Pendiente auxiliar',
            'color_hex' => '#3B82F6',
            'fase_ciclo' => CatalogoEstatusPedido::FASE_PENDIENTE_AUXILIAR,
            'orden' => 2,
            'activo' => true,
        ]);

        return PedidoBma::create([
            'folio' => 'PED-F-'.uniqid(),
            'fecha' => $fecha,
            'vendedor_id' => $vendedora->id,
            'catalogo_estatus_pedido_id' => $estatus->id,
            'total_mercancia' => 100,
            'costo_envio' => 0,
            'es_resguardo' => false,
        ]);
    }

    public function test_filtra_por_rango_de_fecha_y_solo_mios_e_ignora_valores_invalidos(): void
    {
        $vendedora = User::factory()->create();
        $otra = User::factory()->create();
        $vendedora->colaboradores()->attach($otra->id);

        $viejo = $this->pedido($vendedora, '2026-11-01');
        $reciente = $this->pedido($vendedora, '2026-11-18');
        $ajeno = $this->pedido($otra, '2026-11-18');

        $servicio = app(ListarPedidosBmaService::class);
        $ids = fn (array $f) => $servicio->ejecutar($vendedora, $f, false)->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$reciente->id, $ajeno->id], $ids(['desde' => '2026-11-10']));
        $this->assertEquals([$viejo->id], $ids(['hasta' => '2026-11-05']));
        $this->assertEqualsCanonicalizing([$viejo->id, $reciente->id], $ids(['mios' => 'true']));
        $this->assertCount(3, $ids(['desde' => 'no-es-fecha', 'hasta' => '2026-02-31', 'almacen_id' => 'x', 'mios' => 'false']));
    }
}
