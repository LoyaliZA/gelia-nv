<?php

namespace Tests\Feature\ControlPedidos;

use App\Models\Almacen;
use App\Models\Inventario;
use App\Models\Producto;
use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\CatalogoOrigenPedido;
use App\Models\ControlPedidos\CatalogoPaqueteriaPedido;
use App\Models\ControlPedidos\CatalogoTipoCajaPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Models\ControlPedidos\PedidoBmaRevisionProducto;
use App\Models\User;
use App\Services\ControlPedidos\NotificarPedidoBmaService;
use App\Services\ControlPedidos\ResponderPesajePedidoBmaService;
use App\Services\ControlPedidos\SolicitarPesajePedidoBmaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PesajeNacionalRespuesta20Test extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->mock(NotificarPedidoBmaService::class)->shouldReceive('ejecutar')->zeroOrMoreTimes();
        $this->usuario = User::factory()->create();
        foreach ([
            CatalogoEstatusPedido::FASE_BORRADOR => ['BORRADOR_N20', 'Borrador', 1],
            CatalogoEstatusPedido::FASE_PESAJE_PENDIENTE => ['PESAJE_N20', 'Pesaje', 2],
            CatalogoEstatusPedido::FASE_PESAJE_RESPONDIDO => ['PESAJE_OK_N20', 'Pesaje listo', 3],
        ] as $fase => [$codigo, $nombre, $orden]) {
            CatalogoEstatusPedido::query()->firstOrCreate(
                ['fase_ciclo' => $fase],
                ['codigo_interno' => $codigo, 'nombre_visual' => $nombre, 'color_hex' => '#999999', 'orden' => $orden, 'activo' => true]
            );
        }
    }

    public function test_todo_ok_sin_revisiones_completa_los_uuid_vigentes(): void
    {
        Storage::fake('public');
        [$pedido, $idA, $idB, $caja] = $this->pedidoConPiezas();

        $respondido = app(ResponderPesajePedidoBmaService::class)->ejecutar($pedido, $this->usuario->id, [$caja], [
            'confirmacion_explicita' => true,
            'excepciones' => [],
            'evidencias_envios' => [[UploadedFile::fake()->image('caja.jpg')]],
        ]);

        $revisiones = $respondido->revisionesProducto()->get()->keyBy('sku');
        $mapa = $respondido->solicitud_preparacion_json['respuestas_piezas_nacionales'] ?? [];
        $this->assertCount(2, $revisiones);
        $this->assertArrayHasKey($idA, $mapa);
        $this->assertArrayHasKey($idB, $mapa);
        $this->assertSame(PedidoBmaRevisionProducto::ESTADO_BUENO, $revisiones['SKU-A']->estado_fisico);
        $this->assertSame(1, $revisiones['SKU-A']->cantidad_encontrada);
        $this->assertSame(PedidoBmaRevisionProducto::ESTADO_BUENO, $revisiones['SKU-B']->estado_fisico);
    }

    public function test_una_excepcion_faltante_deja_el_resto_bueno(): void
    {
        Storage::fake('public');
        [$pedido, $idA, $idB, $caja] = $this->pedidoConPiezas();

        $respondido = app(ResponderPesajePedidoBmaService::class)->ejecutar($pedido, $this->usuario->id, [$caja], [
            'confirmacion_explicita' => false,
            'excepciones' => [[
                'descripcion' => 'Pieza A',
                'sku' => 'SKU-A',
                'cantidad' => 1,
                'condicion' => 'faltante',
                'detalle' => 'Sin existencias',
            ]],
            'evidencias_envios' => [[UploadedFile::fake()->image('caja.jpg')]],
        ]);

        $revisiones = $respondido->revisionesProducto()->get()->keyBy('sku');
        $this->assertSame(PedidoBmaRevisionProducto::ESTADO_SIN_EXISTENCIA, $revisiones['SKU-A']->estado_fisico);
        $this->assertSame(0, $revisiones['SKU-A']->cantidad_encontrada);
        $this->assertSame(PedidoBmaRevisionProducto::ESTADO_BUENO, $revisiones['SKU-B']->estado_fisico);
        $this->assertSame(1, $revisiones['SKU-B']->cantidad_encontrada);
    }

    public function test_confirmacion_explicita_sigue_exigiendo_cajas(): void
    {
        [$pedido] = $this->pedidoConPiezas();

        try {
            app(ResponderPesajePedidoBmaService::class)->ejecutar($pedido, $this->usuario->id, [], [
                'confirmacion_explicita' => true,
                'excepciones' => [],
            ]);
            $this->fail('El pesaje nacional 2.0 no puede omitir cajas.');
        } catch (\InvalidArgumentException $error) {
            $this->assertStringContainsString('envío', $error->getMessage());
        }
    }

    public function test_sin_confirmacion_explicita_sigue_exigiendo_cada_uuid(): void
    {
        Storage::fake('public');
        [$pedido, $idA, $idB, $caja] = $this->pedidoConPiezas();

        try {
            app(ResponderPesajePedidoBmaService::class)->ejecutar($pedido, $this->usuario->id, [$caja], [
                'revisiones' => [[
                    'descripcion_producto' => 'Pieza A',
                    'sku' => 'SKU-A',
                    'cantidad_solicitada' => 1,
                    'cantidad_encontrada' => 1,
                    'estado_fisico' => 'bueno',
                    'client_uuid' => $idA,
                ]],
                'evidencias_envios' => [[UploadedFile::fake()->image('caja.jpg')]],
            ]);
            $this->fail('El contrato legado debe seguir pidiendo cada pieza.');
        } catch (\InvalidArgumentException $error) {
            $this->assertStringContainsString('cada pieza', $error->getMessage());
        }

        $this->assertNotNull($idB);
    }

    public function test_pieza_de_catalogo_sin_inventario_se_registra(): void
    {
        Storage::fake('public');
        [$pedido, , , $caja] = $this->pedidoConPiezas();
        $pedido->almacen?->update(['permite_busqueda_productos' => true]);
        $producto = Producto::query()->create([
            'uuid' => (string) Str::uuid(),
            'folio' => 85715166,
            'sku' => '85715166012',
            'descripcion' => 'C ABERCROMBIE catalogo',
            'activo' => true,
        ]);
        $this->assertFalse(Inventario::query()->where('producto_id', $producto->id)->exists());

        $respondido = app(ResponderPesajePedidoBmaService::class)->ejecutar($pedido, $this->usuario->id, [$caja], [
            'almacen_id' => $pedido->almacen_id,
            'confirmacion_explicita' => false,
            'excepciones' => [[
                'descripcion' => 'C ABERCROMBIE catalogo',
                'sku' => '85715166012',
                'producto_id' => $producto->id,
                'cantidad' => 1,
                'condicion' => 'detalle',
                'detalle' => 'tapa rota',
                'evidencias' => [UploadedFile::fake()->image('detalle.jpg')],
            ]],
            'revisiones' => [[
                'descripcion_producto' => 'C ABERCROMBIE catalogo',
                'sku' => '85715166012',
                'producto_id' => $producto->id,
                'cantidad_solicitada' => 1,
                'cantidad_encontrada' => 1,
                'estado_fisico' => 'regular',
                'comentario' => 'tapa rota',
            ]],
            'evidencias_envios' => [[UploadedFile::fake()->image('caja.jpg')]],
        ]);

        $this->assertTrue($respondido->revisionesProducto()->where('sku', '85715166012')->exists());
    }

    public function test_faltante_de_catalogo_sin_almacen_ni_inventario(): void
    {
        Storage::fake('public');
        [$pedido, , , $caja] = $this->pedidoConPiezas();
        $almacenPedido = $pedido->almacen_id;
        $producto = Producto::query()->create([
            'uuid' => (string) Str::uuid(),
            'folio' => 85715167,
            'sku' => '85715166708',
            'descripcion' => 'C ABERCROMBIE PASSION',
            'activo' => true,
        ]);
        $this->assertFalse(Inventario::query()->where('producto_id', $producto->id)->exists());

        $respondido = app(ResponderPesajePedidoBmaService::class)->ejecutar($pedido, $this->usuario->id, [$caja], [
            'confirmacion_explicita' => false,
            'excepciones' => [[
                'descripcion' => 'C ABERCROMBIE PASSION',
                'sku' => '85715166708',
                'producto_id' => $producto->id,
                'cantidad' => 1,
                'condicion' => 'faltante',
                'detalle' => 'Sin existencias',
            ]],
            'evidencias_envios' => [[UploadedFile::fake()->image('caja.jpg')]],
        ]);

        $revision = $respondido->revisionesProducto()->where('sku', '85715166708')->first();
        $this->assertNotNull($revision);
        $this->assertSame(PedidoBmaRevisionProducto::ESTADO_SIN_EXISTENCIA, $revision->estado_fisico);
        $this->assertSame(0, $revision->cantidad_encontrada);
        $this->assertSame($almacenPedido, $respondido->almacen_id);
    }

    public function test_consulta_solo_revisiones_sigue_exigiendo_productos(): void
    {
        $pedido = $this->pedidoConsultaSinPeso();

        try {
            app(ResponderPesajePedidoBmaService::class)->ejecutar($pedido, $this->usuario->id, [], [
                'confirmacion_explicita' => true,
                'excepciones' => [],
                'numero_cajas' => 1,
            ]);
            $this->fail('soloRevisiones no usa el cierre 2.0 de UUIDs nacionales.');
        } catch (\InvalidArgumentException $error) {
            $this->assertStringContainsString('producto', $error->getMessage());
        }
    }

    /**
     * @return array{0: PedidoBma, 1: string, 2: string, 3: array<string, mixed>}
     */
    private function pedidoConPiezas(): array
    {
        $pedido = $this->pedidoNacional();
        $consultado = app(SolicitarPesajePedidoBmaService::class)->ejecutar($pedido, $this->usuario->id);
        $idA = (string) Str::uuid();
        $idB = (string) Str::uuid();
        $json = $consultado->solicitud_preparacion_json;
        $json['piezas_nacionales_vigentes'] = [
            ['id' => $idA, 'sku' => 'SKU-A', 'descripcion' => 'Pieza A', 'cantidad' => 1],
            ['id' => $idB, 'sku' => 'SKU-B', 'descripcion' => 'Pieza B', 'cantidad' => 1],
        ];
        $consultado->update(['solicitud_preparacion_json' => $json]);
        $tipo = CatalogoTipoCajaPedido::query()->create(['nombre' => 'Caja n20 '.uniqid(), 'activo' => true]);
        $uuid = (string) Str::uuid();

        return [
            $consultado->fresh(),
            $idA,
            $idB,
            [
                'catalogo_tipo_caja_id' => $tipo->id,
                'largo' => 20,
                'ancho' => 15,
                'alto' => 10,
                'peso_real_kg' => 1.25,
                'peso_volumetrico_kg' => 0.6,
                'uuid_operativo' => $uuid,
                'client_uuid' => $uuid,
            ],
        ];
    }

    private function pedidoConsultaSinPeso(): PedidoBma
    {
        $estatus = CatalogoEstatusPedido::query()->where('fase_ciclo', CatalogoEstatusPedido::FASE_PESAJE_PENDIENTE)->firstOrFail();
        $origen = CatalogoOrigenPedido::query()->create([
            'codigo' => 'LOC-'.uniqid(),
            'nombre' => 'Local '.uniqid(),
            'requiere_logistica' => true,
            'activo' => true,
        ]);
        $almacen = Almacen::query()->create([
            'codigo' => 'PDV-'.uniqid(),
            'nombre' => 'PDV',
            'activo' => true,
            'permite_salida_pedidos' => true,
        ]);
        $paqueteria = CatalogoPaqueteriaPedido::query()->create([
            'nombre' => 'Local '.uniqid(),
            'categoria' => CatalogoPaqueteriaPedido::CATEGORIA_LOCAL_REGIONAL,
            'requiere_peso' => false,
            'activo' => true,
            'modalidad_tarifa' => CatalogoPaqueteriaPedido::MODALIDAD_FIJA,
        ]);

        return PedidoBma::query()->create([
            'folio' => 'LOC-'.uniqid(),
            'folio_remision' => 'WIZ-'.uniqid(),
            'fecha' => now()->toDateString(),
            'vendedor_id' => $this->usuario->id,
            'origen_id' => $origen->id,
            'almacen_id' => $almacen->id,
            'catalogo_estatus_pedido_id' => $estatus->id,
            'catalogo_paqueteria_id' => $paqueteria->id,
            'estatus_envio' => PedidoBma::ESTATUS_ENVIO_PENDIENTE_PESAJE,
            'solicitud_preparacion_json' => [
                'modalidad_entrega' => 'tienda',
                'codigo_modalidad' => 'RECOGE_TIENDA',
            ],
            'total_mercancia' => 50,
            'costo_envio' => 0,
            'saldo_a_favor' => 0,
            'costo_seguro' => 0,
            'total_a_cobrar' => 50,
            'aplica_seguro' => false,
            'es_resguardo' => false,
        ])->fresh();
    }

    private function pedidoNacional(): PedidoBma
    {
        $estatus = CatalogoEstatusPedido::query()->where('fase_ciclo', CatalogoEstatusPedido::FASE_BORRADOR)->firstOrFail();
        $origen = CatalogoOrigenPedido::query()->create([
            'codigo' => 'NAC-'.uniqid(),
            'nombre' => 'Envío nacional '.uniqid(),
            'requiere_logistica' => true,
            'activo' => true,
        ]);
        $almacen = Almacen::query()->create([
            'codigo' => 'CEDIS-'.uniqid(),
            'nombre' => 'CEDIS',
            'activo' => true,
            'permite_salida_pedidos' => true,
        ]);
        $paqueteria = CatalogoPaqueteriaPedido::query()->create([
            'nombre' => 'DHL '.uniqid(),
            'categoria' => CatalogoPaqueteriaPedido::CATEGORIA_COMERCIAL,
            'requiere_peso' => true,
            'activo' => true,
            'modalidad_tarifa' => CatalogoPaqueteriaPedido::MODALIDAD_FIJA,
        ]);
        $pedido = PedidoBma::query()->create([
            'folio' => 'NAC-'.uniqid(),
            'folio_remision' => 'WIZ-'.uniqid(),
            'fecha' => now()->toDateString(),
            'vendedor_id' => $this->usuario->id,
            'origen_id' => $origen->id,
            'almacen_id' => $almacen->id,
            'catalogo_estatus_pedido_id' => $estatus->id,
            'catalogo_paqueteria_id' => $paqueteria->id,
            'estatus_envio' => PedidoBma::ESTATUS_ENVIO_COMPLETO,
            'solicitud_preparacion_json' => [
                'modalidad_entrega' => 'nacional',
                'codigo_modalidad' => 'ENVIO_BODEGA_NORMAL',
                'captura_nacional' => 'compartida',
            ],
            'total_mercancia' => 100,
            'costo_envio' => 0,
            'saldo_a_favor' => 0,
            'costo_seguro' => 0,
            'total_a_cobrar' => 100,
            'aplica_seguro' => false,
            'es_resguardo' => false,
        ]);
        PedidoBmaDocumento::query()->create([
            'pedido_bma_id' => $pedido->id,
            'tipo' => PedidoBmaDocumento::TIPO_PDF_PEDIDO,
            'ruta_archivo' => 'nacional/pedido.pdf',
            'nombre_original' => 'pedido.pdf',
            'mime_type' => 'application/pdf',
        ]);

        return $pedido->fresh();
    }
}
