<?php

namespace Tests\Feature\ControlPedidos;

use App\Models\Almacen;
use App\Models\CatalogoEstadoSolicitud;
use App\Models\CatalogoTipoAlmacen;
use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\CatalogoModalidadPreparacionPedido;
use App\Models\ControlPedidos\CatalogoOrigenPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaTareaDocumento;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\ControlPedidos\PedidoBmaTareaProducto;
use App\Models\Producto;
use App\Models\SolicitudTraspaso;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\ControlPedidos\AsegurarCumplimientoFisicoService;
use App\Services\ControlPedidos\GenerarCaratulaRecogeHoyService;
use App\Services\ControlPedidos\GuardarApartadoRecogeHoyService;
use App\Services\ControlPedidos\NotificarPedidoBmaService;
use App\Services\Traspasos\AbastecerPreparacionService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AbastecimientoPreparacionQ61Test extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    private Almacen $origen;

    private PedidoBmaTareaPreparacion $tarea;

    private PedidoBmaTareaProducto $linea;

    private AbastecerPreparacionService $servicio;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        config(['inertia.pages.paths' => [resource_path('js/Pages')]]);
        Storage::fake('public');
        $this->actor = User::factory()->create();
        foreach (['control_pedidos.tienda.responder', 'control_pedidos.cedis', 'traspasos.responder', 'control_pedidos.preparacion.corregir'] as $permiso) {
            $this->actor->givePermissionTo(Permission::findOrCreate($permiso, 'web'));
        }
        foreach (['Pendiente', 'Respondida', 'Verificada', 'Incorrecta'] as $nombre) {
            CatalogoEstadoSolicitud::firstOrCreate(['nombre' => $nombre]);
        }
        $sucursal = Sucursal::factory()->create();
        $this->actor->concederAccesoSucursal($sucursal, true);
        $destino = Almacen::create(['codigo' => 'PDV', 'nombre' => 'Destino', 'activo' => true, 'sucursal_id' => $sucursal->id]);
        $this->origen = Almacen::create(['codigo' => 'CEDIS', 'nombre' => 'Origen', 'activo' => true, 'visible_en_traspasos' => true]);
        $estatus = CatalogoEstatusPedido::create(['codigo_interno' => 'Q61', 'nombre_visual' => 'Preparación', 'color_hex' => '#999999', 'fase_ciclo' => 'BORRADOR', 'orden' => 1]);
        $origenPedido = CatalogoOrigenPedido::create(['codigo' => 'Q61', 'nombre' => 'Tienda', 'requiere_logistica' => false]);
        $pedido = PedidoBma::create(['folio' => 'GELIA-Q61', 'folio_remision' => 'PED-Q61', 'fecha' => now()->toDateString(), 'vendedor_id' => $this->actor->id,
            'cliente_id' => DB::table('clientes')->insertGetId(['numero_cliente' => 'Q61-CLI', 'nombre' => 'Prueba', 'lista_actual_id' => DB::table('catalogo_listas_descuento')->insertGetId(['nombre' => 'Q61', 'activo' => true])]), 'almacen_id' => $destino->id, 'origen_id' => $origenPedido->id, 'catalogo_estatus_pedido_id' => $estatus->id]);
        $this->tarea = PedidoBmaTareaPreparacion::create(['pedido_bma_id' => $pedido->id, 'almacen_id' => $destino->id, 'catalogo_modalidad_preparacion_id' => CatalogoModalidadPreparacionPedido::firstOrCreate(['codigo' => 'RECOGE_TIENDA'], ['nombre' => 'Recoge en tienda', 'activo' => true])->id, 'estado' => 'EN_ATENCION', 'asignada_a_id' => $this->actor->id, 'version' => 1]);
        $producto = Producto::create(['folio' => 99889, 'sku' => 'Q61-SKU', 'descripcion' => 'Producto de prueba', 'activo' => true]);
        $producto->inventarios()->create(['almacen_id' => $this->origen->id, 'existencia' => 0]);
        $this->linea = PedidoBmaTareaProducto::create(['pedido_bma_tarea_preparacion_id' => $this->tarea->id, 'pedido_bma_origen_id' => $pedido->id, 'producto_id' => $producto->id,
            'sku' => $producto->sku, 'descripcion_snapshot' => $producto->descripcion, 'cantidad_solicitada' => 3, 'cantidad_encontrada' => 0, 'estado_fisico' => 'bueno', 'orden' => 0]);
        $this->servicio = app(AbastecerPreparacionService::class);
    }

    private function crear(string $clave = 'q61-crear'): SolicitudTraspaso
    {
        return $this->servicio->crear($this->tarea, $this->actor, $this->origen->id, [['id' => $this->linea->id, 'cantidad' => 3]], $clave, $this->tarea->fresh()->version);
    }

    private function salida(SolicitudTraspaso $s): SolicitudTraspaso
    {
        $p = $s->productos()->sole();

        return $this->servicio->operar($s, $this->actor, 'SALIDA', [], 'q61-salida-'.$s->id, $s->version_operativa,
            UploadedFile::fake()->image('salida.jpg'), revisiones: array_fill(0, (int) $p->piezas, ['solicitud_traspaso_producto_id' => $p->id, 'estado_fisico' => 'bueno']));
    }

    private function llegada(SolicitudTraspaso $s, int $buenas, int $danadas, string $clave): SolicitudTraspaso
    {
        return $this->servicio->operar($s, $this->actor, 'LLEGADA', [['id' => $s->productos()->sole()->id, 'buenas' => $buenas, 'danadas' => $danadas]], $clave,
            $s->version_operativa, UploadedFile::fake()->image('llegada.jpg'), 'Revisión física documentada');
    }

    public function test_cedis_abastece_tarea_existente_con_llegada_parcial_complemento_y_reintento(): void
    {
        $inventario = DB::table('inventarios')->get()->toJson();
        $s = $this->crear();
        $this->assertSame($this->tarea->id, $s->tarea_receptora_id);
        $this->assertSame($this->tarea->pedido_bma_id, $s->pedido_receptor_id);
        $this->assertSame($this->tarea->almacen_id, $s->almacen_destino_id);
        $this->assertSame($s->id, $this->crear()->id);
        $s = $this->salida($s);
        $this->assertSame(0, $this->linea->fresh()->cantidad_encontrada);
        $this->assertSame('EN_ATENCION', $this->tarea->fresh()->estado);
        $s = $this->llegada($s, 2, 0, 'q61-llegada-1');
        $this->assertSame(2, $this->linea->fresh()->cantidad_encontrada);
        $this->assertSame(1, $this->servicio->resumen($s)[0]['pendientes']);
        $this->llegada($s, 2, 0, 'q61-llegada-1');
        $this->assertSame(2, $this->linea->fresh()->cantidad_encontrada);
        $s = $this->llegada($s, 1, 0, 'q61-llegada-2');
        $this->servicio->asegurarSatisfecho($this->tarea);
        $this->assertSame(3, $this->linea->fresh()->cantidad_encontrada);
        $this->assertSame(CatalogoEstadoSolicitud::idDe('Verificada'), $s->catalogo_estado_solicitud_id);
        $this->assertSame(1, PedidoBma::count());
        $this->assertDatabaseCount('pdv_resguardos', 0);
        $this->assertDatabaseCount('pdv_resguardo_entregas', 0);
        $this->assertSame($inventario, DB::table('inventarios')->get()->toJson());
    }

    public function test_otro_pdv_abastece_sin_exigir_stock_sin_peso_guia_o_remision(): void
    {
        $sucursal = Sucursal::factory()->create();
        $this->actor->concederAccesoSucursal($sucursal);
        $tipo = CatalogoTipoAlmacen::firstOrCreate(['nombre' => 'PDV']);
        $this->origen->update(['codigo' => 'PDV-ORIGEN', 'tipo_almacen_id' => $tipo->id, 'sucursal_id' => $sucursal->id]);
        $s = $this->llegada($this->salida($this->crear()), 3, 0, 'q61-pdv');
        $this->assertSame(0, $this->servicio->resumen($s)[0]['pendientes']);
        $this->assertNull($this->tarea->fresh()->peso_real_kg);
        $this->assertSame(1, PedidoBma::count());
    }

    public function test_salida_y_recepcion_parcial_no_permiten_cerrar_preparacion(): void
    {
        $s = $this->salida($this->crear());
        $this->llegada($s, 1, 0, 'q61-parcial');
        $this->expectException(ValidationException::class);
        $this->servicio->asegurarSatisfecho($this->tarea);
    }

    public function test_danadas_no_abastecen_y_bloquean_hasta_rechazo_autorizado_y_reposicion(): void
    {
        $s = $this->llegada($this->salida($this->crear()), 2, 1, 'q61-dano');
        $this->assertSame(2, $this->linea->fresh()->cantidad_encontrada);
        $this->assertSame(1, $this->servicio->resumen($s)[0]['incidencia_abierta']);
        try {
            $this->servicio->asegurarSatisfecho($this->tarea);
            $this->fail('Debe bloquear daño');
        } catch (ValidationException) {
        }
        $this->servicio->operar($s, $this->actor, 'RECHAZO_AUTORIZADO', [['id' => $s->productos()->sole()->id, 'cantidad' => 1]], 'q61-rechazo', $s->version_operativa, UploadedFile::fake()->image('rechazo.jpg'), 'Se autoriza rechazo por rotura');
        $this->assertSame(1, $this->servicio->pendientes($this->tarea)[0]['por_abastecer']);
        $nuevo = $this->servicio->crear($this->tarea, $this->actor, $this->origen->id, [['id' => $this->linea->id, 'cantidad' => 1]], 'q61-reposicion', $this->tarea->fresh()->version);
        $this->llegada($this->salida($nuevo), 1, 0, 'q61-reposicion-llegada');
        $this->servicio->asegurarSatisfecho($this->tarea);
        $this->assertSame(3, $this->linea->fresh()->cantidad_encontrada);
    }

    public function test_doble_llegada_con_clave_distinta_y_version_obsoleta_no_consume_unidades(): void
    {
        $s = $this->salida($this->crear());
        $this->llegada($s, 2, 0, 'q61-terminal-a');
        try {
            $this->llegada($s, 2, 0, 'q61-terminal-b');
            $this->fail('Debe rechazar versión');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('version', $e->errors());
        }
        $this->assertSame(2, $this->linea->fresh()->cantidad_encontrada);
        $this->assertSame(3, $s->operaciones()->count());
    }

    public function test_producto_de_otra_tarea_no_puede_vincularse(): void
    {
        $this->expectException(ValidationException::class);
        $this->servicio->crear($this->tarea, $this->actor, $this->origen->id, [['id' => 999999, 'cantidad' => 1]], 'q61-ajeno', 1);
    }

    public function test_otra_sucursal_no_puede_registrar_llegada(): void
    {
        $s = $this->salida($this->crear());
        $intruso = User::factory()->create();
        $intruso->givePermissionTo('control_pedidos.tienda.responder');
        $this->expectException(HttpException::class);
        $this->servicio->operar($s, $intruso, 'LLEGADA', [], 'q61-intruso', $s->version_operativa, UploadedFile::fake()->image('e.jpg'));
    }

    public function test_no_se_permite_duplicar_solicitud_por_encima_del_pendiente(): void
    {
        $this->crear();
        $this->expectException(ValidationException::class);
        $this->crear('q61-otra');
    }

    public function test_formulario_y_request_http_ejecutan_salida_y_llegada_con_evidencia(): void
    {
        $this->withoutMiddleware([ValidateCsrfToken::class, PreventRequestForgery::class]);
        $this->actingAs($this->actor);
        $url = route('traspasos.abastecimiento.index', ['tarea' => $this->tarea->id]);
        $this->get($url)->assertOk()->assertInertia(fn ($page) => $page->component('Traspasos/Abastecimiento')->where('tarea.id', $this->tarea->id));
        $this->from($url)->post(route('traspasos.abastecimiento.crear', $this->tarea), [
            'version' => 1, 'idempotency_key' => 'q61-http-creacion', 'almacen_origen_id' => $this->origen->id,
            'lineas' => [['id' => $this->linea->id, 'cantidad' => 3]],
        ])->assertRedirect($url)->assertSessionHasNoErrors();
        $s = SolicitudTraspaso::sole();
        $p = $s->productos()->sole();
        $this->post(route('traspasos.abastecimiento.operar', $s), ['tipo' => 'SALIDA', 'version' => 1, 'idempotency_key' => 'q61-http-salida',
            'evidencia' => UploadedFile::fake()->image('salida.jpg'),
            'revisiones' => array_fill(0, 3, ['solicitud_traspaso_producto_id' => $p->id, 'estado_fisico' => 'bueno']),
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('traspasos.abastecimiento.operar', $s), ['tipo' => 'LLEGADA', 'version' => 2, 'idempotency_key' => 'q61-http-llegada',
            'lineas' => [['id' => $p->id, 'buenas' => 2, 'danadas' => 0]], 'evidencia' => UploadedFile::fake()->image('llegada.jpg'),
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(2, $this->linea->fresh()->cantidad_encontrada);
        $this->get($url)->assertOk()->assertInertia(fn ($page) => $page->where('traspasos.data.0.lineas.0.pendientes', 1));
    }

    public function test_la_preparacion_puede_confirmarse_despues_de_abastecimiento_completo(): void
    {
        $this->travelTo(now()->setTime(12, 0));
        $this->tarea->update(['fecha_limite' => now()->addHours(4)]);
        $this->mock(NotificarPedidoBmaService::class)->shouldReceive('ejecutar')->zeroOrMoreTimes();
        $this->mock(GenerarCaratulaRecogeHoyService::class)->shouldReceive('ejecutar')->zeroOrMoreTimes()->andReturn(new PedidoBmaTareaDocumento);
        $this->llegada($this->salida($this->crear()), 3, 0, 'q61-cierre-llegada');
        $a = app(AsegurarCumplimientoFisicoService::class)->ejecutar($this->tarea->fresh());
        $resultado = app(GuardarApartadoRecogeHoyService::class)->ejecutar($this->tarea, $this->actor, [
            'version' => $a->version, 'lineas' => [['id' => $this->linea->id, 'producto_id' => $this->linea->producto_id, 'cantidad' => 3, 'estado_fisico' => 'bueno']],
        ], true);
        $this->assertSame('RESPONDIDA', $this->tarea->fresh()->estado);
        $this->assertSame(3, $resultado->cantidad);
        $this->assertDatabaseCount('pdv_resguardos', 0);
    }

    public function test_faltante_conserva_incidencia_hasta_llegada_complementaria(): void
    {
        $s = $this->llegada($this->salida($this->crear()), 2, 0, 'q61-dos');
        $s = $this->servicio->operar($s, $this->actor, 'FALTANTE', [['id' => $s->productos()->sole()->id, 'cantidad' => 1]], 'q61-falta', $s->version_operativa,
            UploadedFile::fake()->image('faltante.jpg'), 'Falta una pieza del traslado');
        $this->assertSame(1, $this->servicio->resumen($s)[0]['faltantes_reportados']);
        $s = $this->llegada($s, 1, 0, 'q61-complementa-falta');
        $this->assertSame(0, $this->servicio->resumen($s)[0]['faltantes_reportados']);
        $this->assertSame(1, $s->operaciones()->where('tipo', 'FALTANTE')->count());
    }

    public function test_una_pantalla_anterior_a_la_llegada_no_sobrescribe_el_apartado(): void
    {
        $a = app(AsegurarCumplimientoFisicoService::class)->ejecutar($this->tarea);
        $this->llegada($this->salida($this->crear()), 2, 0, 'q61-recepcion-stale');
        try {
            app(GuardarApartadoRecogeHoyService::class)->ejecutar($this->tarea, $this->actor, [
                'version' => $a->version, 'lineas' => [['id' => $this->linea->id, 'producto_id' => $this->linea->producto_id, 'cantidad' => 0, 'estado_fisico' => 'sin_existencia', 'observacion' => 'Pantalla anterior']],
            ]);
            $this->fail('Debía rechazar la versión anterior');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('version', $e->errors());
        }
        $this->assertSame(2, $this->linea->fresh()->cantidad_encontrada);
    }
}
