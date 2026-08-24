<?php

namespace Tests\Unit\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\CatalogoOrigenPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Models\User;
use App\Services\ControlPedidos\CalcularProgresoPedidoBmaService;
use App\Services\ControlPedidos\FormularioProgresivoPedidoBmaConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Fase3FormularioProgresivoTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    private CalcularProgresoPedidoBmaService $progreso;

    protected function setUp(): void
    {
        parent::setUp();
        $this->usuario = User::factory()->create();
        $this->progreso = app(CalcularProgresoPedidoBmaService::class);
        $this->seedMinimo();
    }

    public function test_nuevo_pedido_arranca_en_solicitud(): void
    {
        $r = $this->progreso->calcular(null);
        $this->assertSame('solicitud', $r['etapa_actual']);
        $this->assertSame('bloqueada', $this->etapa($r, 'pago')['estado']);
    }

    public function test_tienda_sin_consulta_bloquea_cotizacion_y_pago(): void
    {
        $pedido = $this->pedidoBase(requiereLogistica: false);
        $r = $this->progreso->calcular($pedido);
        $this->assertContains($r['etapa_actual'], ['solicitud', 'consulta']);
        $this->assertSame('bloqueada', $this->etapa($r, 'cotizacion')['estado']);
        $this->assertSame('bloqueada', $this->etapa($r, 'pago')['estado']);
    }

    public function test_envio_pendiente_pesaje_accion_esperar_cedis(): void
    {
        $pedido = $this->pedidoBase(requiereLogistica: true, completoSolicitud: true);
        $pedido->update([
            'estatus_envio' => PedidoBma::ESTATUS_ENVIO_PENDIENTE_PESAJE,
            'catalogo_estatus_pedido_id' => $this->estatus(CatalogoEstatusPedido::FASE_PESAJE_PENDIENTE)->id,
        ]);
        PedidoBmaDocumento::query()->create([
            'pedido_bma_id' => $pedido->id,
            'tipo' => PedidoBmaDocumento::TIPO_PDF_PEDIDO,
            'ruta_archivo' => 't.pdf',
            'nombre_original' => 't.pdf',
            'mime_type' => 'application/pdf',
            'tamano_bytes' => 1,
            'orden' => 0,
        ]);

        $r = $this->progreso->calcular($pedido->fresh(['origen', 'estatus', 'documentos']));
        $this->assertSame('Esperar respuesta de CEDIS', $r['accion_recomendada']);
        $this->assertSame('bloqueada', $this->etapa($r, 'confirmacion')['estado']);
    }

    public function test_consulta_cerrada_habilita_cotizacion(): void
    {
        $pedido = $this->pedidoBase(requiereLogistica: false, completoSolicitud: true);
        $pedido->update([
            'pesaje_respondido_at' => now(),
            'consulta_cerrada_at' => now(),
            'total_mercancia' => 500,
        ]);
        PedidoBmaDocumento::query()->create([
            'pedido_bma_id' => $pedido->id,
            'tipo' => PedidoBmaDocumento::TIPO_PDF_PEDIDO,
            'ruta_archivo' => 't.pdf',
            'nombre_original' => 't.pdf',
            'mime_type' => 'application/pdf',
            'tamano_bytes' => 1,
            'orden' => 0,
        ]);

        $r = $this->progreso->calcular($pedido->fresh(['origen', 'estatus', 'documentos', 'paqueteria', 'zona', 'tipoGuia']));
        $this->assertSame('completa', $this->etapa($r, 'confirmacion')['estado']);
        $this->assertNotSame('bloqueada', $this->etapa($r, 'cotizacion')['estado']);
    }

    public function test_reopen_bloquea_cotizacion_y_pago(): void
    {
        $pedido = $this->pedidoBase(requiereLogistica: true, completoSolicitud: true);
        $pedido->update([
            'pesaje_respondido_at' => now(),
            'consulta_cerrada_at' => null,
            'consulta_actualizacion_pendiente' => true,
            'estatus_envio' => PedidoBma::ESTATUS_ENVIO_PENDIENTE_PESAJE,
            'total_mercancia' => 100,
        ]);

        $r = $this->progreso->calcular($pedido->fresh(['origen', 'estatus', 'documentos']));
        $this->assertSame('bloqueada', $this->etapa($r, 'cotizacion')['estado']);
        $this->assertSame('bloqueada', $this->etapa($r, 'pago')['estado']);
        $this->assertNotEmpty($r['bloqueos']);
    }

    public function test_config_flag_default_off(): void
    {
        $cfg = app(FormularioProgresivoPedidoBmaConfig::class);
        $this->assertFalse($cfg->formularioProgresivo());
        $todas = $cfg->todas();
        $this->assertArrayHasKey('autosave_debounce_ms', $todas);
        $this->assertArrayHasKey('max_reintentos_autosave', $todas);
    }

    public function test_autoguardar_conflicto_409(): void
    {
        $this->usuario->givePermissionTo(
            \Spatie\Permission\Models\Permission::findOrCreate('control_pedidos.crear', 'web')
        );
        $pedido = $this->pedidoBase(requiereLogistica: false, completoSolicitud: true);
        DB::table('pedidos_bma')->where('id', $pedido->id)->update([
            'updated_at' => now()->subMinutes(5),
        ]);
        $pedido->refresh();

        $respuesta = $this->actingAs($this->usuario)
            ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class)
            ->postJson('/control-pedidos/autoguardar', [
                'pedido_id' => $pedido->id,
                'origen_id' => $pedido->origen_id,
                'cliente_id' => $pedido->cliente_id,
                'almacen_id' => $pedido->almacen_id,
                'updated_at_esperado' => now()->toIso8601String(),
            ]);

        $respuesta->assertStatus(409);
        $respuesta->assertJsonStructure(['message', 'updated_at', 'progreso']);
    }

    /**
     * @param  array<string, mixed>  $r
     * @return array<string, mixed>
     */
    private function etapa(array $r, string $codigo): array
    {
        foreach ($r['etapas'] as $e) {
            if ($e['codigo'] === $codigo) {
                return $e;
            }
        }

        $this->fail("Etapa {$codigo} ausente");
    }

    private function pedidoBase(bool $requiereLogistica, bool $completoSolicitud = false): PedidoBma
    {
        $origen = CatalogoOrigenPedido::query()->firstOrCreate(
            ['nombre' => $requiereLogistica ? 'Envío Test F3' : 'Tienda Test F3'],
            ['requiere_logistica' => $requiereLogistica, 'activo' => true]
        );
        $estatus = $this->estatus(CatalogoEstatusPedido::FASE_BORRADOR);

        return PedidoBma::query()->create([
            'folio' => 'F3-'.uniqid(),
            'folio_remision' => $completoSolicitud ? 'WIZ-001' : null,
            'fecha' => now()->toDateString(),
            'vendedor_id' => $this->usuario->id,
            'cliente_id' => $completoSolicitud ? DB::table('clientes')->value('id') : null,
            'origen_id' => $completoSolicitud ? $origen->id : null,
            'almacen_id' => $completoSolicitud ? DB::table('almacenes')->value('id') : null,
            'catalogo_estatus_pedido_id' => $estatus->id,
            'total_mercancia' => 0,
            'costo_envio' => null,
            'aplica_seguro' => false,
            'costo_seguro' => 0,
            'saldo_a_favor' => 0,
            'total_a_cobrar' => 0,
            'es_resguardo' => false,
        ]);
    }

    private function estatus(string $fase): CatalogoEstatusPedido
    {
        return CatalogoEstatusPedido::query()->firstOrCreate(
            ['fase_ciclo' => $fase],
            [
                'codigo_interno' => $fase,
                'nombre_visual' => $fase,
                'color_hex' => '#64748B',
                'activo' => true,
                'orden' => 1,
            ]
        );
    }

    private function seedMinimo(): void
    {
        $now = now();
        if (! DB::table('catalogo_listas_descuento')->exists()) {
            DB::table('catalogo_listas_descuento')->insert([
                'nombre' => 'Lista', 'activo' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        if (! DB::table('clientes')->exists()) {
            DB::table('clientes')->insert([
                'numero_cliente' => '1001',
                'nombre' => 'Cliente',
                'lista_actual_id' => DB::table('catalogo_listas_descuento')->value('id'),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        if (! DB::table('almacenes')->exists()) {
            DB::table('almacenes')->insert([
                'codigo' => 'VTA', 'nombre' => 'CEDIS', 'activo' => true, 'visible_en_pedidos' => true,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        $this->estatus(CatalogoEstatusPedido::FASE_BORRADOR);
        $this->estatus(CatalogoEstatusPedido::FASE_PESAJE_PENDIENTE);
    }
}
