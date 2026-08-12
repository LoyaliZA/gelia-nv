<?php

namespace Tests\Unit\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaHistorialEstado;
use App\Models\User;
use App\Services\ControlPedidos\RegistrarHistorialPedidoService;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ControlPedidosBitacoraTest extends TestCase
{
    use RefreshDatabase;

    public function test_writer_persiste_accion_y_evidencia(): void
    {
        $user = User::factory()->create(['name' => 'Auditor Bitacora']);

        $estatus = CatalogoEstatusPedido::create([
            'codigo_interno' => 'BORRADOR_BIT',
            'nombre_visual' => 'Borrador',
            'color_hex' => '#94A3B8',
            'fase_ciclo' => CatalogoEstatusPedido::FASE_BORRADOR,
            'orden' => 1,
            'activo' => true,
        ]);

        $pedido = PedidoBma::create([
            'folio' => 'PED-BIT-'.uniqid(),
            'fecha' => now()->toDateString(),
            'vendedor_id' => $user->id,
            'catalogo_estatus_pedido_id' => $estatus->id,
            'total_mercancia' => 0,
            'costo_envio' => 0,
            'es_resguardo' => false,
        ]);

        $row = app(RegistrarHistorialPedidoService::class)->ejecutar(
            $pedido->id,
            $user->id,
            null,
            $estatus->id,
            'Remisión de prueba.',
            AccionesHistorialPedidoBma::CARGA_REMISION,
            ['ruta' => 'pedidos_bma/remisiones/1/demo.pdf', 'nombre' => 'demo.pdf']
        );

        $this->assertInstanceOf(PedidoBmaHistorialEstado::class, $row);
        $this->assertSame(AccionesHistorialPedidoBma::CARGA_REMISION, $row->accion);
        $this->assertSame('Carga de remisión', $row->accion_etiqueta);
        $this->assertSame('pedidos_bma/remisiones/1/demo.pdf', $row->evidencia_ruta);
        $this->assertSame('demo.pdf', $row->evidencia_nombre);
        $this->assertSame($user->id, $row->usuario_id);
        $this->assertDatabaseHas('pedido_bma_historial_estados', [
            'pedido_bma_id' => $pedido->id,
            'accion' => AccionesHistorialPedidoBma::CARGA_REMISION,
            'evidencia_nombre' => 'demo.pdf',
        ]);
    }
}
