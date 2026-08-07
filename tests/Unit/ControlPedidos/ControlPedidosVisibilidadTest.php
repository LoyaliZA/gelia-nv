<?php

namespace Tests\Unit\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Models\User;
use App\Services\ControlPedidos\ListarPedidosBmaService;
use App\Support\ControlPedidos\VisibilidadPedidoBma;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ControlPedidosVisibilidadTest extends TestCase
{
    use RefreshDatabase;

    public function test_vendedora_no_ve_pedido_ajeno_y_gerente_si_consulta(): void
    {
        $vendedoraA = User::factory()->create(['name' => 'Vendedora A']);
        $vendedoraB = User::factory()->create(['name' => 'Vendedora B']);
        $gerente = User::factory()->create(['name' => 'Gerente']);
        $gerente->colaboradores()->attach($vendedoraA->id);

        $estatus = CatalogoEstatusPedido::create([
            'codigo_interno' => 'BORRADOR_VIS',
            'nombre_visual' => 'Borrador',
            'color_hex' => '#94A3B8',
            'fase_ciclo' => CatalogoEstatusPedido::FASE_BORRADOR,
            'orden' => 1,
            'activo' => true,
        ]);

        $pedidoA = PedidoBma::create([
            'folio' => 'PED-A-'.uniqid(),
            'fecha' => now()->toDateString(),
            'vendedor_id' => $vendedoraA->id,
            'catalogo_estatus_pedido_id' => $estatus->id,
            'total_mercancia' => 100,
            'costo_envio' => 0,
            'es_resguardo' => false,
        ]);

        $pedidoB = PedidoBma::create([
            'folio' => 'PED-B-'.uniqid(),
            'fecha' => now()->toDateString(),
            'vendedor_id' => $vendedoraB->id,
            'catalogo_estatus_pedido_id' => $estatus->id,
            'total_mercancia' => 100,
            'costo_envio' => 0,
            'es_resguardo' => false,
        ]);

        $listadoA = app(ListarPedidosBmaService::class)->ejecutar($vendedoraA, [], false);
        $this->assertTrue($listadoA->contains('id', $pedidoA->id));
        $this->assertFalse($listadoA->contains('id', $pedidoB->id));

        $listadoGerente = app(ListarPedidosBmaService::class)->ejecutar($gerente, [], false);
        $this->assertTrue($listadoGerente->contains('id', $pedidoA->id));
        $this->assertFalse($listadoGerente->contains('id', $pedidoB->id));

        $this->assertTrue(VisibilidadPedidoBma::puedeConsultarEnListadoBma($gerente, $pedidoA));
        $this->assertFalse(VisibilidadPedidoBma::puedeMutarComoVendedora($gerente, $pedidoA));
        $this->assertTrue(VisibilidadPedidoBma::puedeMutarComoVendedora($vendedoraA, $pedidoA));
        $this->assertFalse(VisibilidadPedidoBma::puedeMutarComoVendedora($vendedoraB, $pedidoA));

        $pedidoGerente = $listadoGerente->firstWhere('id', $pedidoA->id);
        $this->assertFalse((bool) $pedidoGerente->puede_editar);
    }

    public function test_documento_requiere_consulta_autorizada(): void
    {
        Storage::fake('public');

        $vendedora = User::factory()->create();
        $ajeno = User::factory()->create();

        $estatus = CatalogoEstatusPedido::create([
            'codigo_interno' => 'BORRADOR_DOC',
            'nombre_visual' => 'Borrador',
            'color_hex' => '#94A3B8',
            'fase_ciclo' => CatalogoEstatusPedido::FASE_BORRADOR,
            'orden' => 1,
            'activo' => true,
        ]);

        $pedido = PedidoBma::create([
            'folio' => 'PED-DOC-'.uniqid(),
            'fecha' => now()->toDateString(),
            'vendedor_id' => $vendedora->id,
            'catalogo_estatus_pedido_id' => $estatus->id,
            'total_mercancia' => 50,
            'costo_envio' => 0,
            'es_resguardo' => false,
        ]);

        $ruta = "pedidos_bma/remisiones/{$pedido->id}/demo.pdf";
        Storage::disk('public')->put($ruta, '%PDF-1.4 demo');

        $doc = PedidoBmaDocumento::create([
            'pedido_bma_id' => $pedido->id,
            'tipo' => PedidoBmaDocumento::TIPO_REMISION,
            'ruta_archivo' => $ruta,
            'nombre_original' => 'demo.pdf',
            'mime_type' => 'application/pdf',
            'tamano_bytes' => 12,
            'orden' => 0,
        ]);

        $this->actingAs($vendedora)
            ->get(route('control_pedidos.documentos.show', [$pedido, $doc]))
            ->assertOk();

        $this->actingAs($ajeno)
            ->get(route('control_pedidos.documentos.show', [$pedido, $doc]))
            ->assertForbidden();
    }
}
