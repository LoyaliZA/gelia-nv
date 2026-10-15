<?php

namespace Tests\Feature\ControlPedidos;

use App\Models\Almacen;
use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\CatalogoOrigenPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ConcurrenciaCapturaPedidoTest extends TestCase
{
    use RefreshDatabase { refreshDatabase as private refreshIsolatedDatabase; }
    private PedidoBma $pedido;
    private User $usuario;

    public function refreshDatabase(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->refreshIsolatedDatabase();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class, \Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class]);
        $this->usuario = User::factory()->create();
        $this->usuario->givePermissionTo(Permission::findOrCreate('control_pedidos.crear', 'web'));
        $estado = CatalogoEstatusPedido::firstOrCreate(['fase_ciclo' => 'BORRADOR'], ['codigo_interno' => 'BORRADOR', 'nombre_visual' => 'Borrador', 'color_hex' => '#999999', 'orden' => 1, 'activo' => true]);
        $origen = CatalogoOrigenPedido::create(['codigo' => 'Q3', 'nombre' => 'Q3', 'requiere_logistica' => true, 'activo' => true]);
        $almacen = Almacen::create(['codigo' => 'Q3', 'nombre' => 'Q3', 'activo' => true, 'permite_salida_pedidos' => true]);
        $this->pedido = PedidoBma::create(['folio' => 'Q3-SINTETICO', 'fecha' => '2026-10-15', 'vendedor_id' => $this->usuario->id, 'catalogo_estatus_pedido_id' => $estado->id, 'origen_id' => $origen->id, 'almacen_id' => $almacen->id, 'comentarios_drive' => 'inicial', 'prioridad_md' => true, 'solicitud_preparacion_json' => ['version' => 1, 'codigo_modalidad' => 'ENVIO_MUNICIPIO', 'entrega_municipal' => ['municipio_destino' => 'Municipio Q3', 'destinatario_es_cliente' => false]]]);
        $this->actingAs($this->usuario);
    }

    public function test_creacion_reintentada_conserva_id_y_rechaza_clave_con_otro_contenido(): void
    {
        $datos = ['captura_clave' => 'creacion-q3', 'origen_id' => $this->pedido->origen_id, 'almacen_id' => $this->pedido->almacen_id, 'comentarios_drive' => 'alta'];
        $uno = $this->postJson('/control-pedidos/autoguardar', $datos)->assertOk()->json('id');
        $this->postJson('/control-pedidos/autoguardar', $datos)->assertOk()->assertJsonPath('id', $uno);
        $datos['comentarios_drive'] = 'otra intención';
        $this->postJson('/control-pedidos/autoguardar', $datos)->assertStatus(409);
        $this->assertSame(2, PedidoBma::count());
    }

    public function test_preparacion_replay_exclusividad_y_hash_de_contenido(): void
    {
        $config = $this->partialMock(\App\Services\ControlPedidos\PreparacionTiendaConfig::class);
        foreach (['activo', 'usuarioHabilitado', 'modalidadPermitida', 'almacenPermitido'] as $metodo) $config->shouldReceive($metodo)->andReturn(true);
        $this->usuario->givePermissionTo(Permission::findOrCreate('control_pedidos.preparacion.solicitar', 'web'));
        \App\Models\ControlPedidos\CatalogoModalidadPreparacionPedido::firstOrCreate(['codigo' => 'ENVIO_BODEGA_NORMAL'], ['nombre' => 'Bodega Q3', 'activo' => true]);
        CatalogoEstatusPedido::firstOrCreate(['fase_ciclo' => 'PESAJE_PENDIENTE'], ['codigo_interno' => 'PESAJE_PENDIENTE', 'nombre_visual' => 'Consulta', 'color_hex' => '#999999', 'orden' => 2, 'activo' => true]);
        $this->pedido->documentos()->create(['tipo' => 'pdf_pedido', 'ruta_archivo' => 'q3/soporte.pdf', 'nombre_original' => 'soporte.pdf', 'activo' => true]);
        $datos = $this->base() + ['codigo_modalidad' => 'ENVIO_BODEGA_NORMAL', 'almacen_id' => $this->pedido->almacen_id, 'idempotencia_clave' => 'solicitud-q3'];
        $url = route('control_pedidos.solicitar_preparacion_tienda', $this->pedido);
        $this->post($url, $datos)->assertRedirect()->assertSessionHas('success');
        $this->postJson($url, $datos)->assertOk()->assertJsonPath('ok', true);
        $datos['observaciones'] = 'incompatible';
        $this->postJson($url, $datos)->assertStatus(409);
        $datos['idempotencia_clave'] = 'segunda-q3';
        $datos = array_replace($datos, $this->base());
        $this->post($url, $datos)->assertRedirect()->assertSessionHas('error');
        $this->assertSame(1, DB::table('pedido_bma_tareas_preparacion')->count());
        $this->assertSame(1, DB::table('pedido_bma_cumplimiento_fisico')->count());
        $this->assertSame(1, DB::table('pedido_bma_historial_estados')->count());
    }

    private function base(): array
    {
        $p = $this->pedido->fresh();
        $attrs = $p->getRawOriginal();
        ksort($attrs);
        return ['pedido_id' => $p->id, 'revision_esperada' => (int) ($p->revision_registro ?? 0), 'base_captura' => $p->base_captura];
    }

    public function test_dos_editores_en_el_mismo_segundo_no_se_sobrescriben(): void
    {
        $this->freezeTime();
        $base = $this->base();
        $this->postJson('/control-pedidos/autoguardar', $base + ['comentarios_drive' => 'primero'])->assertOk();
        $this->postJson('/control-pedidos/autoguardar', $base + ['comentarios_drive' => 'obsoleto'])->assertStatus(409);
        $this->assertSame('primero', $this->pedido->fresh()->comentarios_drive);
    }

    public function test_cliente_antiguo_no_puede_escribir_sin_base_confirmada(): void
    {
        $this->postJson('/control-pedidos/autoguardar', ['pedido_id' => $this->pedido->id, 'comentarios_drive' => 'sin control'])->assertStatus(409);
        $this->assertSame('inicial', $this->pedido->fresh()->comentarios_drive);
    }

    public function test_escritor_operativo_invalida_base_sin_incrementar_revision_de_captura(): void
    {
        $base = $this->base();
        $this->pedido->update(['consulta_actualizacion_pendiente' => true]);
        $this->postJson('/control-pedidos/autoguardar', $base + ['comentarios_drive' => 'antiguo'])->assertStatus(409);
        $this->assertSame('inicial', $this->pedido->fresh()->comentarios_drive);
    }

    public function test_secuencia_preserva_q2_false_cero_null_y_omite_sin_borrar(): void
    {
        $r = $this->postJson('/control-pedidos/autoguardar', $this->base() + ['prioridad_md' => false, 'numero_cajas' => 0, 'solicitud_preparacion' => ['folio_referencia' => null]])->assertOk();
        $r->assertJsonPath('revision_registro', 1);
        $this->postJson('/control-pedidos/autoguardar', $this->base() + ['comentarios_drive' => 'segundo'])->assertOk()->assertJsonPath('revision_registro', 2);
        $p = $this->pedido->fresh();
        $this->assertFalse($p->prioridad_md);
        $this->assertSame(0, $p->numero_cajas);
        $this->assertSame('Municipio Q3', $p->solicitud_preparacion_json['entrega_municipal']['municipio_destino']);
        $this->assertFalse($p->solicitud_preparacion_json['entrega_municipal']['destinatario_es_cliente']);
        $this->assertNull($p->solicitud_preparacion_json['folio_referencia']);
        foreach (['pedido_bma_tareas_preparacion', 'pedido_bma_caratulas', 'resguardos_pdv'] as $tabla) {
            if (\Illuminate\Support\Facades\Schema::hasTable($tabla)) $this->assertSame(0, DB::table($tabla)->count());
        }
    }
}
