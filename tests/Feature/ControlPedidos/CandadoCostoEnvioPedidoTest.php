<?php

namespace Tests\Feature\ControlPedidos;

use App\Models\Almacen;
use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\CatalogoOrigenPedido;
use App\Models\ControlPedidos\CatalogoPaqueteriaPedido;
use App\Models\ControlPedidos\CatalogoTipoCajaPedido;
use App\Models\ControlPedidos\CatalogoTipoGuiaPedido;
use App\Models\ControlPedidos\CatalogoZonaPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaCaja;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Models\ControlPedidos\PedidoBmaHistorialEstado;
use App\Models\User;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CandadoCostoEnvioPedidoTest extends TestCase
{
    use RefreshDatabase {
        refreshDatabase as private refreshIsolatedDatabase;
    }

    private User $usuario;

    public function refreshDatabase(): void
    {
        $this->assertSame('testing', config('app.env'));
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->refreshIsolatedDatabase();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([ValidateCsrfToken::class, PreventRequestForgery::class]);
        $this->usuario = User::factory()->create();
        $this->usuario->givePermissionTo(Permission::findOrCreate('control_pedidos.crear', 'web'));
    }

    public function test_guardar_cero_sin_confirmacion_conserva_el_envio(): void
    {
        $pedido = $this->pedidoConEnvio();

        $this->actingAs($this->usuario)
            ->putJson('/control-pedidos/'.$pedido->id, $this->captura($pedido, ['costo_envio' => 0]))
            ->assertStatus(422)
            ->assertJsonPath('message', 'El costo de envío ya capturado no puede quedar en cero. Confirme la anulación e indique el motivo.');

        $this->assertEquals(1298, (float) $pedido->fresh()->costo_envio);
        $this->assertSame(0, PedidoBmaHistorialEstado::query()->where('pedido_bma_id', $pedido->id)->where('accion', AccionesHistorialPedidoBma::COSTOS_ENVIO)->count());
    }

    public function test_confirmar_anulacion_guarda_cero_e_historial(): void
    {
        $pedido = $this->pedidoConEnvio();

        $this->actingAs($this->usuario)
            ->putJson('/control-pedidos/'.$pedido->id, $this->captura($pedido, [
                'costo_envio' => 0,
                'confirmar_anular_envio' => true,
                'motivo_anular_envio' => 'El cliente ya no paga las guías',
            ]))
            ->assertOk();

        $fresco = $pedido->fresh();
        $this->assertEquals(0, (float) $fresco->costo_envio);
        $historial = PedidoBmaHistorialEstado::query()
            ->where('pedido_bma_id', $pedido->id)
            ->where('accion', AccionesHistorialPedidoBma::COSTOS_ENVIO)
            ->first();
        $this->assertNotNull($historial);
        $this->assertStringContainsString('1,298.00', (string) $historial->comentarios);
        $this->assertStringContainsString('0.00', (string) $historial->comentarios);
        $this->assertStringContainsString('El cliente ya no paga las guías', (string) $historial->comentarios);
        $this->assertSame($this->usuario->id, $historial->usuario_id);
    }

    public function test_autoguardar_sin_costo_no_pisa_un_envio_positivo(): void
    {
        $pedido = $this->pedidoConEnvio();

        $this->actingAs($this->usuario)
            ->postJson('/control-pedidos/autoguardar', $this->captura($pedido, [
                'pedido_id' => $pedido->id,
                'comentarios_drive' => 'Nota de progreso',
            ]))
            ->assertOk();

        $fresco = $pedido->fresh();
        $this->assertEquals(1298, (float) $fresco->costo_envio);
        $this->assertSame('Nota de progreso', $fresco->comentarios_drive);
    }

    public function test_enviar_con_paqueteria_comercial_y_costo_cero_falla(): void
    {
        $pedido = $this->pedidoListoParaEnviar(0);

        $respuesta = $this->actingAs($this->usuario)
            ->putJson('/control-pedidos/'.$pedido->id, $this->captura($pedido, [
                'costo_envio' => 0,
                'enviar' => true,
            ]));

        $respuesta->assertStatus(422);
        $this->assertStringContainsString('costo de envío mayor a cero', (string) $respuesta->json('message'));
    }

    public function test_guia_del_cliente_y_pedido_sin_pesaje_pueden_quedar_sin_costo(): void
    {
        $sinPesaje = $this->pedidoConEnvio(['pesaje_respondido_at' => null, 'pesaje_respondido_por_id' => null]);
        $estatusBorrador = CatalogoEstatusPedido::query()->firstOrCreate(
            ['fase_ciclo' => CatalogoEstatusPedido::FASE_BORRADOR],
            ['codigo_interno' => 'BORRADOR', 'nombre_visual' => 'Borrador', 'color_hex' => '#999999', 'orden' => 1, 'activo' => true]
        );
        $sinPesaje->update(['catalogo_estatus_pedido_id' => $estatusBorrador->id]);

        $this->actingAs($this->usuario)
            ->putJson('/control-pedidos/'.$sinPesaje->id, $this->captura($sinPesaje->fresh(), ['costo_envio' => 0]))
            ->assertOk();
        $this->assertEquals(0, (float) $sinPesaje->fresh()->costo_envio);

        $guia = $this->pedidoConEnvio(['cliente_proporciona_guia' => true]);
        $this->actingAs($this->usuario)
            ->putJson('/control-pedidos/'.$guia->id, $this->captura($guia->fresh(), [
                'cliente_proporciona_guia' => true,
                'costo_envio' => 0,
            ]))
            ->assertOk();
        $this->assertNull($guia->fresh()->costo_envio);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function captura(PedidoBma $pedido, array $extra): array
    {
        $pedido = $pedido->fresh();

        return array_merge([
            'revision_esperada' => (int) $pedido->revision_registro,
            'base_captura' => $pedido->base_captura,
        ], $extra);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function pedidoConEnvio(array $extra = []): PedidoBma
    {
        $this->clienteMinimo();
        $estatus = CatalogoEstatusPedido::query()->firstOrCreate(
            ['fase_ciclo' => CatalogoEstatusPedido::FASE_PESAJE_RESPONDIDO],
            ['codigo_interno' => 'PESAJE_RESPONDIDO', 'nombre_visual' => 'Pesaje respondido', 'color_hex' => '#64748B', 'orden' => 4, 'activo' => true]
        );
        $origen = CatalogoOrigenPedido::query()->firstOrCreate(
            ['nombre' => 'ENVÍO candado'],
            ['requiere_logistica' => true, 'activo' => true]
        );
        $almacen = Almacen::query()->create([
            'codigo' => 'VTA-'.uniqid(),
            'nombre' => 'CEDIS',
            'activo' => true,
            'visible_en_pedidos' => true,
            'permite_salida_pedidos' => true,
        ]);
        $paqueteria = CatalogoPaqueteriaPedido::query()->firstOrCreate(
            ['nombre' => 'ESTAFETA candado'],
            ['categoria' => CatalogoPaqueteriaPedido::CATEGORIA_COMERCIAL, 'activo' => true]
        );

        return PedidoBma::query()->create(array_merge([
            'folio' => 'PBMA-'.uniqid(),
            'folio_remision' => 'REPRO-'.uniqid(),
            'fecha' => now()->toDateString(),
            'vendedor_id' => $this->usuario->id,
            'cliente_id' => DB::table('clientes')->value('id'),
            'origen_id' => $origen->id,
            'almacen_id' => $almacen->id,
            'catalogo_estatus_pedido_id' => $estatus->id,
            'catalogo_paqueteria_id' => $paqueteria->id,
            'total_mercancia' => 39301.60,
            'costo_envio' => 1298,
            'aplica_seguro' => false,
            'costo_seguro' => 0,
            'saldo_a_favor' => 0,
            'total_a_cobrar' => 40599.60,
            'es_resguardo' => false,
            'cliente_proporciona_guia' => false,
            'envio_por_cobrar' => false,
            'pesaje_respondido_at' => now(),
            'pesaje_respondido_por_id' => $this->usuario->id,
            'consulta_cerrada_at' => now(),
            'peso_real_kg' => 66,
            'peso_cobrado_guia_kg' => 66,
        ], $extra));
    }

    private function pedidoListoParaEnviar(float $costo): PedidoBma
    {
        $guia = CatalogoTipoGuiaPedido::query()->firstOrCreate(['nombre' => 'Terrestre'], ['activo' => true]);
        $zona = CatalogoZonaPedido::query()->firstOrCreate(
            ['nombre' => 'Sin reexpedición'],
            ['costo_adicional' => 0, 'activo' => true]
        );
        $cajaTipo = CatalogoTipoCajaPedido::query()->firstOrCreate(
            ['nombre' => 'CAJA #202'],
            ['peso_volumetrico' => 10, 'largo' => 46, 'ancho' => 32, 'alto' => 35, 'activo' => true]
        );
        $pedido = $this->pedidoConEnvio([
            'costo_envio' => $costo,
            'total_a_cobrar' => 39301.60 + $costo,
            'catalogo_tipo_guia_id' => $guia->id,
            'catalogo_zona_id' => $zona->id,
            'catalogo_tipo_caja_id' => $cajaTipo->id,
            'numero_cajas' => 1,
            'codigo_postal' => '01150',
            'domicilio_entrega' => 'Calle de prueba 1',
        ]);
        $pedido->cajas()->create([
            'uuid_operativo' => (string) Str::uuid(),
            'catalogo_tipo_caja_id' => $cajaTipo->id,
            'orden' => 0,
            'largo' => 46,
            'ancho' => 32,
            'alto' => 35,
            'peso_real_kg' => 22,
            'peso_volumetrico_kg' => 10.304,
            'peso_cobrado_kg' => 22,
            'estado_operativo' => PedidoBmaCaja::ESTADO_ACTIVA,
        ]);
        PedidoBmaDocumento::query()->create([
            'pedido_bma_id' => $pedido->id,
            'tipo' => PedidoBmaDocumento::TIPO_PDF_PEDIDO,
            'ruta_archivo' => 'pedidos_bma/repro/pedido.txt',
            'nombre_original' => 'pedido.txt',
            'mime_type' => 'text/plain',
            'tamano_bytes' => 4,
            'orden' => 1,
            'activo' => true,
        ]);

        return $pedido->fresh();
    }

    private function clienteMinimo(): void
    {
        if (DB::table('clientes')->exists()) {
            return;
        }
        $now = now();
        DB::table('catalogo_listas_descuento')->insert([
            'nombre' => 'Lista test',
            'activo' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('clientes')->insert([
            'numero_cliente' => '4984',
            'nombre' => 'HUB ROMERO VALENCIA EDUARDO',
            'lista_actual_id' => DB::table('catalogo_listas_descuento')->value('id'),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
