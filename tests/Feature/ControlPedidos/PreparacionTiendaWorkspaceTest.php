<?php

namespace Tests\Feature\ControlPedidos;

use App\Models\Almacen;
use App\Models\CatalogoListaDescuento;
use App\Models\Cliente;
use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\CatalogoModalidadPreparacionPedido;
use App\Models\ControlPedidos\CatalogoOrigenPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\Producto;
use App\Models\User;
use App\Services\ControlPedidos\NotificarPedidoBmaService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PreparacionTiendaWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private User $auxiliar;

    private User $ventas;

    private PedidoBmaTareaPreparacion $tarea;

    private Producto $producto;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->travelTo(now()->setDate(2026, 10, 12)->setTime(12, 0));
        $this->withoutMiddleware([ValidateCsrfToken::class, PreventRequestForgery::class]);
        Storage::fake('public');
        Storage::fake('local');
        $this->mock(NotificarPedidoBmaService::class)->shouldReceive('ejecutar')->zeroOrMoreTimes();
        $this->auxiliar = User::factory()->create();
        $this->ventas = User::factory()->create();
        foreach (['control_pedidos.tienda.ver', 'control_pedidos.tienda.tomar', 'control_pedidos.tienda.responder', 'control_pedidos.tienda.reportar_error'] as $permiso) {
            $this->auxiliar->givePermissionTo(Permission::findOrCreate($permiso, 'web'));
        }
        $this->ventas->givePermissionTo(Permission::findOrCreate('control_pedidos.ver_listado', 'web'));
        $almacen = Almacen::create(['codigo' => 'PDV', 'nombre' => 'PDV prueba', 'activo' => true, 'visible_en_pedidos' => true, 'permite_busqueda_productos' => true]);
        $origen = CatalogoOrigenPedido::create(['codigo' => 'SEP_TEST', 'nombre' => 'Separación', 'requiere_logistica' => false, 'activo' => true]);
        $estatus = CatalogoEstatusPedido::create(['codigo_interno' => 'PREP_TEST', 'nombre_visual' => 'Preparación', 'color_hex' => '#EAB308', 'fase_ciclo' => 'PESAJE_PENDIENTE', 'orden' => 1, 'activo' => true]);
        $pedido = PedidoBma::create(['folio' => 'PRUEBA-WORKSPACE', 'fecha' => now()->toDateString(), 'vendedor_id' => $this->ventas->id, 'origen_id' => $origen->id, 'almacen_id' => $almacen->id, 'catalogo_estatus_pedido_id' => $estatus->id, 'estatus_envio' => 'pendiente_pesaje']);
        $modalidad = CatalogoModalidadPreparacionPedido::firstOrCreate(['codigo' => 'RECOGE_TIENDA'], ['nombre' => 'Recoge en tienda', 'activo' => true]);
        $this->tarea = PedidoBmaTareaPreparacion::create(['pedido_bma_id' => $pedido->id, 'catalogo_modalidad_preparacion_id' => $modalidad->id, 'almacen_id' => $almacen->id, 'estado' => 'PENDIENTE', 'version' => 1, 'solicitada_por_id' => $this->ventas->id, 'solicitada_at' => now(), 'fecha_limite' => now()->addHours(5)]);
        $this->producto = Producto::create(['folio' => 9971, 'sku' => 'PDV-PRUEBA', 'descripcion' => 'Crema de prueba', 'activo' => true]);
        $this->producto->inventarios()->create(['almacen_id' => $almacen->id, 'existencia' => 0]);
        $this->actingAs($this->auxiliar);
    }

    public function test_busqueda_de_bandeja_encuentra_remision_y_razon_social(): void
    {
        $lista = CatalogoListaDescuento::firstOrCreate(
            ['nombre' => 'PUBLICO GENERAL'],
            ['monto_requerido' => 0, 'activo' => true]
        );
        $cliente = Cliente::create([
            'numero_cliente' => 'Q82-BUSQ',
            'nombre' => 'Nombre corto Q82',
            'nombre_razon_social' => 'Razon Social Q82 Verificacion',
            'lista_actual_id' => $lista->id,
            'monto_venta_actual' => 0,
        ]);
        $this->tarea->update(['area_responsable_codigo' => 'TIENDA']);
        $this->tarea->pedido->update([
            'folio_remision' => 'Q82-REM-325235',
            'cliente_id' => $cliente->id,
        ]);

        $this->getJson(route('control_pedidos.tienda.listado', ['tab' => 'PENDIENTES', 'q' => 'Q82-REM-325235']))
            ->assertOk()
            ->assertJsonPath('tareas.total', 1);
        $this->getJson(route('control_pedidos.tienda.listado', ['tab' => 'PENDIENTES', 'q' => 'Razon Social Q82']))
            ->assertOk()
            ->assertJsonPath('tareas.total', 1);
        $this->getJson(route('control_pedidos.tienda.listado', ['tab' => 'PENDIENTES', 'q' => 'sin-coincidencia-q82']))
            ->assertOk()
            ->assertJsonPath('tareas.total', 0);
    }

    public function test_auxiliar_atender_buscar_confirmar_y_ventas_ve_respuesta_sin_pesaje(): void
    {
        $this->postJson(route('control_pedidos.tienda.tomar', $this->tarea), ['version' => 1])->assertOk()->assertJsonPath('message', 'Tarea lista para preparar.');
        $vista = $this->getJson(route('control_pedidos.tienda.show', $this->tarea))->assertOk()->assertJsonPath('tarea.puede_preparar', true)->assertJsonPath('tarea.almacen.id', $this->tarea->almacen_id);
        $this->getJson(route('gestion_interna.productos.buscar', ['almacen_id' => $this->tarea->almacen_id, 'q' => 'PDV-PRUEBA']))->assertOk()->assertJsonPath('data.0.id', $this->producto->id);
        $this->postJson(route('control_pedidos.tienda.apartado.confirmar', $this->tarea), [
            'version' => $vista->json('tarea.apartado.version'), 'ubicacion' => 'Anaquel 4',
            'lineas' => [['producto_id' => $this->producto->id, 'cantidad' => 3, 'estado_fisico' => 'bueno']],
        ])->assertOk();
        $this->assertSame('RESPONDIDA', $this->tarea->fresh()->estado);
        $this->assertNull($this->tarea->pedido->fresh()->pesaje_respondido_at);
        $this->actingAs($this->ventas);
        $listado = $this->getJson(route('control_pedidos.listado', ['q' => 'PRUEBA-WORKSPACE']))->assertOk();
        $pedido = collect($listado->json('pedidos.data'))->firstWhere('id', $this->tarea->pedido_bma_id);
        $this->assertTrue($pedido['tarea_preparacion']['respuesta']['respondida']);
        $this->assertSame(3, $pedido['tarea_preparacion']['respuesta']['cantidad']);
        $this->assertSame('Anaquel 4', $pedido['tarea_preparacion']['respuesta']['ubicacion']);
        $caratula = collect($pedido['tarea_preparacion']['respuesta']['documentos'])->firstWhere('tipo', 'caratula_recoge_tienda');
        $this->get($caratula['url'])->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_registra_producto_del_catalogo_aunque_el_pdv_no_tenga_inventario(): void
    {
        $sinFila = Producto::create([
            'folio' => 9972,
            'sku' => '6295199813966',
            'codigo_barras' => '6295199813966',
            'descripcion' => 'C BODY ARMAF ODYSSEY BA HA MAS 200ML.',
            'activo' => true,
        ]);
        $this->postJson(route('control_pedidos.tienda.tomar', $this->tarea), ['version' => 1])->assertOk();
        $vista = $this->getJson(route('control_pedidos.tienda.show', $this->tarea))->assertOk();
        $this->getJson(route('gestion_interna.productos.buscar', [
            'almacen_id' => $this->tarea->almacen_id,
            'q' => 'bahamas',
            'incluir_sin_inventario' => 1,
        ]))->assertOk()->assertJsonPath('data.0.id', $sinFila->id);
        $this->postJson(route('control_pedidos.tienda.apartado.guardar', $this->tarea), [
            'version' => $vista->json('tarea.apartado.version'),
            'lineas' => [['producto_id' => $sinFila->id, 'cantidad' => 1, 'estado_fisico' => 'bueno']],
        ])->assertOk();
        $this->assertSame('6295199813966', $this->tarea->productos()->value('sku'));
    }

    public function test_confirma_apartado_sin_anaquel_y_conserva_respuesta_vacia(): void
    {
        $this->postJson(route('control_pedidos.tienda.tomar', $this->tarea), ['version' => 1])->assertOk();
        $vista = $this->getJson(route('control_pedidos.tienda.show', $this->tarea))->assertOk();
        $this->postJson(route('control_pedidos.tienda.apartado.confirmar', $this->tarea), [
            'version' => $vista->json('tarea.apartado.version'),
            'lineas' => [['producto_id' => $this->producto->id, 'cantidad' => 2, 'estado_fisico' => 'bueno']],
        ])->assertOk();

        $this->assertSame('RESPONDIDA', $this->tarea->fresh()->estado);
        $this->assertNull($this->tarea->pedido->fresh()->pesaje_respondido_at);
        $this->actingAs($this->ventas);
        $listado = $this->getJson(route('control_pedidos.listado', ['q' => 'PRUEBA-WORKSPACE']))->assertOk();
        $pedido = collect($listado->json('pedidos.data'))->firstWhere('id', $this->tarea->pedido_bma_id);
        $this->assertTrue($pedido['tarea_preparacion']['respuesta']['respondida']);
        $this->assertSame(2, $pedido['tarea_preparacion']['respuesta']['cantidad']);
        $this->assertTrue(($pedido['tarea_preparacion']['respuesta']['ubicacion'] ?? null) === null || $pedido['tarea_preparacion']['respuesta']['ubicacion'] === '');
    }

    public function test_incidente_sin_piezas_regresa_a_ventas_y_no_cierra_apartado(): void
    {
        $this->postJson(route('control_pedidos.tienda.tomar', $this->tarea), ['version' => 1])->assertOk();
        $vista = $this->getJson(route('control_pedidos.tienda.show', $this->tarea))->assertOk();
        $this->postJson(route('control_pedidos.tienda.apartado.guardar', $this->tarea), ['version' => $vista->json('tarea.apartado.version'), 'lineas' => [], 'motivo_incidencia' => 'No se localizaron las piezas'])->assertOk();
        $this->assertSame('CON_INCIDENCIA', $this->tarea->fresh()->estado);
        $this->assertSame(0, $this->tarea->productos()->count());
        $this->actingAs($this->ventas);
        $listado = $this->getJson(route('control_pedidos.listado', ['q' => 'PRUEBA-WORKSPACE']))->assertOk();
        $pedido = collect($listado->json('pedidos.data'))->firstWhere('id', $this->tarea->pedido_bma_id);
        $this->assertSame('No se localizaron las piezas', $pedido['tarea_preparacion']['incidencia']['motivo']);
    }

    public function test_preparacion_con_transferencia_guarda_avance_y_responde_en_json_con_fotos(): void
    {
        $modalidad = CatalogoModalidadPreparacionPedido::firstOrCreate(['codigo' => 'RECOGE_TIENDA_TRANSFERENCIA'], ['nombre' => 'Separación con transferencia', 'activo' => true, 'requisitos_json' => ['evidencia_general_obligatoria' => true]]);
        $this->tarea->update(['catalogo_modalidad_preparacion_id' => $modalidad->id]);
        $this->postJson(route('control_pedidos.tienda.tomar', $this->tarea), ['version' => 1])->assertOk();
        $this->post(route('control_pedidos.tienda.preparacion.guardar', $this->tarea), [
            'version' => $this->tarea->fresh()->version,
            'lineas' => [['producto_id' => $this->producto->id, 'cantidad' => 2, 'estado_fisico' => 'bueno', 'evidencias' => [UploadedFile::fake()->image('pieza.jpg')]]],
            'evidencias' => [UploadedFile::fake()->image('conjunto.jpg')],
        ], ['Accept' => 'application/json'])->assertOk();
        $tarea = $this->tarea->fresh();
        $this->assertSame('EN_ATENCION', $tarea->estado);
        $linea = $tarea->productos()->first();
        $this->postJson(route('control_pedidos.tienda.responder', $tarea), [
            'version' => $tarea->version,
            'lineas' => [['id' => $linea->id, 'producto_id' => $this->producto->id, 'cantidad' => 2, 'estado_fisico' => 'bueno']],
        ])->assertOk()->assertJsonStructure(['message']);
        $this->assertSame('RESPONDIDA', $tarea->fresh()->estado);
        $this->assertCount(2, $tarea->pedido->revisionesProducto);
        $this->assertSame(2, $tarea->documentos()->count());
    }

    public function test_tienda_no_utiliza_cedis_por_defecto_y_respeta_almacenes_configurados(): void
    {
        $cedis = Almacen::create(['codigo' => 'VTA', 'nombre' => 'CEDIS de prueba', 'activo' => true]);
        $tipoPdv = \App\Models\CatalogoTipoAlmacen::create(['nombre' => 'PDV']);
        $sucursal = Almacen::create([
            'codigo' => 'PDV-1',
            'nombre' => 'Carmen Cadena',
            'activo' => true,
            'tipo_almacen_id' => $tipoPdv->id,
        ]);
        $config = \Mockery::mock(\App\Services\ControlPedidos\PreparacionTiendaConfig::class)->makePartial();
        $config->shouldReceive('almacenesHabilitados')->andReturn([]);
        $this->assertTrue($config->almacenPermitido($this->tarea->almacen_id));
        $this->assertTrue($config->almacenPermitido($sucursal->id));
        $this->assertFalse($config->almacenPermitido($cedis->id));
        $otro = \Mockery::mock(\App\Services\ControlPedidos\PreparacionTiendaConfig::class)->makePartial();
        $otro->shouldReceive('almacenesHabilitados')->andReturn([$this->tarea->almacen_id]);
        $this->assertFalse($otro->almacenPermitido($cedis->id));
    }
}
