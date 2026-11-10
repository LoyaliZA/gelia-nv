<?php

namespace Tests\Feature\ControlPedidos;

use App\Models\Almacen;
use App\Models\CatalogoTipoAlmacen;
use App\Models\Cliente;
use App\Models\ConfiguracionSistema;
use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\CatalogoModalidadPreparacionPedido;
use App\Models\ControlPedidos\CatalogoOrigenPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaCumplimientoFisico;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Models\ControlPedidos\PedidoBmaRevisionProducto;
use App\Models\ControlPedidos\PedidoBmaTareaDocumento;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\Departamento;
use App\Models\Inventario;
use App\Models\Producto;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\SaldosAFavor\SafCredito;
use App\Models\SaldosAFavor\SafCuenta;
use App\Models\Sucursal;
use App\Models\User;
use App\Notifications\AlertaPedidoBma;
use App\Services\ControlPedidos\CompromisoInventarioTienda;
use App\Services\ControlPedidos\ResponderDisponibilidadInicialService;
use App\Support\ControlPedidos\ActualizacionConsulta;
use App\Support\ControlPedidos\RegistrarPedidos\ExpedienteRegistrarPedidos;
use App\Support\ControlPedidos\RespuestaPreparacionTienda;
use App\Support\ControlPedidos\RevisionCapturaPedido;
use Carbon\Carbon;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SolicitudRegistrarTiendaTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    private Departamento $departamento;

    private Almacen $pdv;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->withoutMiddleware([ValidateCsrfToken::class, PreventRequestForgery::class]);
        $this->departamento = Departamento::query()->create(['nombre' => 'Ventas', 'activo' => true]);
        $this->usuario = User::factory()->create(['departamento_id' => $this->departamento->id]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach ([
            'control_pedidos.crear',
            'control_pedidos.tienda.ver',
            'control_pedidos.cedis',
            'control_pedidos.cedis.ver_todos',
        ] as $permiso) {
            $this->usuario->givePermissionTo(Permission::findOrCreate($permiso, 'web'));
        }
        foreach ([
            CatalogoEstatusPedido::FASE_BORRADOR => 'BORRADOR',
            CatalogoEstatusPedido::FASE_PESAJE_PENDIENTE => 'PESAJE',
        ] as $fase => $codigo) {
            CatalogoEstatusPedido::query()->firstOrCreate(
                ['fase_ciclo' => $fase],
                ['codigo_interno' => $codigo, 'nombre_visual' => $fase, 'color_hex' => '#999999', 'orden' => 1, 'activo' => true]
            );
        }
        $this->estatusRespondido();
        CatalogoModalidadPreparacionPedido::query()->firstOrCreate(
            ['codigo' => CatalogoModalidadPreparacionPedido::CODIGO_RECOGE_TIENDA],
            ['nombre' => 'Recoge en tienda', 'activo' => true]
        );
        $tipo = CatalogoTipoAlmacen::query()->firstOrCreate(['nombre' => 'PDV']);
        $this->pdv = Almacen::query()->create([
            'codigo' => 'SUC-211',
            'nombre' => 'Sucursal Centro',
            'activo' => true,
            'visible_en_pedidos' => true,
            'permite_salida_pedidos' => true,
            'tipo_almacen_id' => $tipo->id,
        ]);
    }

    public function test_el_borrador_guarda_el_destino_y_no_crea_tarea(): void
    {
        $pedido = $this->pedido();

        $respuesta = $this->actingAs($this->usuario)->postJson('/control-pedidos/autoguardar', $this->captura($pedido, [
            'solicitud_preparacion' => [
                'modalidad_entrega' => 'tienda',
                'area_revision' => 'preparacion_tienda',
                'almacen_revision_id' => $this->pdv->id,
            ],
        ]))->assertOk();

        $fresco = $pedido->fresh();
        $this->assertSame('preparacion_tienda', $fresco->solicitud_preparacion_json['area_revision']);
        $this->assertSame($this->pdv->id, (int) $fresco->solicitud_preparacion_json['almacen_revision_id']);
        $this->assertSame(CatalogoEstatusPedido::FASE_BORRADOR, $fresco->estatus->fase_ciclo);
        $this->assertSame(0, PedidoBmaTareaPreparacion::query()->count());
        $this->assertSame('preparacion_tienda', $respuesta->json('solicitud_preparacion.area_revision'));

        $this->actingAs($this->usuario)->postJson('/control-pedidos/autoguardar', $this->captura($fresco, [
            'solicitud_preparacion' => [
                'modalidad_entrega' => 'tienda',
                'area_revision' => 'preparacion_tienda',
                'almacen_revision_id' => $this->pdv->id,
            ],
        ]))->assertOk();
        $this->assertSame(0, PedidoBmaTareaPreparacion::query()->count());
    }

    public function test_la_solicitud_llega_a_preparacion_tienda_sin_apartar(): void
    {
        $pedido = $this->pedido();
        $this->documento($pedido);

        $cuerpo = $this->solicitud($pedido, 'preparacion_tienda', $this->pdv->id);
        $primero = $this->actingAs($this->usuario)->postJson(
            "/control-pedidos/{$pedido->id}/solicitud-tienda",
            $cuerpo
        )->assertOk();

        $fresco = $pedido->fresh(['estatus']);
        $this->assertFalse((bool) $fresco->prioridad_md);
        $this->assertNull($fresco->pago_validado_at);
        $this->assertSame(CatalogoEstatusPedido::FASE_PESAJE_PENDIENTE, $fresco->estatus->fase_ciclo);
        $this->assertSame('preparacion_tienda', $fresco->solicitud_preparacion_json['area_revision']);
        $this->assertArrayNotHasKey('codigo_modalidad', $fresco->solicitud_preparacion_json);
        $this->assertSame(1, PedidoBmaTareaPreparacion::query()->count());
        $tarea = PedidoBmaTareaPreparacion::query()->first();
        $this->assertSame(PedidoBmaTareaPreparacion::ESTADO_PENDIENTE, $tarea->estado);
        $this->assertSame('TIENDA', $tarea->area_responsable_codigo);
        $this->assertSame($this->pdv->id, (int) $tarea->almacen_id);
        $this->assertNull($tarea->fecha_limite);
        $this->assertSame(0, $tarea->productos()->count());
        $this->assertSame(
            PedidoBmaCumplimientoFisico::ESTADO_POR_SEPARAR,
            $tarea->cumplimientoFisico->estado
        );
        $this->assertStringContainsString('Preparación Tienda', (string) $primero->json('espera'));

        $lista = $this->actingAs($this->usuario)->getJson('/control-pedidos/tienda/listado?tab=PENDIENTES')->assertOk();
        $lista->assertJsonPath('tareas.data.0.pedido.folio_remision', 'FOL-211');
        $lista->assertJsonPath('tareas.data.0.pedido.prioridad_md', false);
        $lista->assertJsonPath('tareas.data.0.almacen.nombre', 'Sucursal Centro');

        $this->actingAs($this->usuario)->getJson('/control-pedidos/tienda/tareas/'.$tarea->id)
            ->assertOk()
            ->assertJsonPath('documentos_solicitud.0.nombre_original', 'pedido-ficticio.pdf')
            ->assertJsonPath('tarea.pedido.folio_remision', 'FOL-211');

        $this->actingAs($this->usuario)->postJson(
            "/control-pedidos/{$pedido->id}/solicitud-tienda",
            $cuerpo
        )->assertOk()->assertJsonPath('idempotente', true);
        $this->assertSame(1, PedidoBmaTareaPreparacion::query()->count());
    }

    public function test_la_solicitud_llega_a_control_pedidos_sin_confirmar_disponibilidad(): void
    {
        $pedido = $this->pedido();
        $this->documento($pedido);

        $respuesta = $this->actingAs($this->usuario)->postJson(
            "/control-pedidos/{$pedido->id}/solicitud-tienda",
            $this->solicitud($pedido, 'control_pedidos')
        )->assertOk();

        $fresco = $pedido->fresh(['estatus']);
        $this->assertSame(0, PedidoBmaTareaPreparacion::query()->count());
        $this->assertSame('pendiente_pesaje', $fresco->estatus_envio);
        $this->assertNull($fresco->pago_validado_at);
        $this->assertFalse((bool) $fresco->prioridad_md);
        $this->assertTrue($fresco->consultaCedisModoRevisionMercancia());
        $this->assertSame('control_pedidos', $fresco->solicitud_preparacion_json['area_revision']);
        $this->assertStringContainsString('Control Pedidos', (string) $respuesta->json('espera'));

        $this->actingAs($this->usuario)->getJson('/control-pedidos/cedis/listado?tab=PENDIENTES_PESAJE')
            ->assertOk()
            ->assertJsonPath('pedidos.data.0.folio_remision', 'FOL-211')
            ->assertJsonPath('pedidos.data.0.documentos.0.nombre_original', 'pedido-ficticio.pdf');

        $this->actingAs($this->usuario)->postJson('/control-pedidos/autoguardar', $this->captura($fresco, [
            'solicitud_preparacion' => [
                'modalidad_entrega' => 'tienda',
                'area_revision' => 'preparacion_tienda',
            ],
        ]))->assertStatus(422);
    }

    public function test_mismo_dia_no_se_clasifica_automaticamente_como_apartado_en_pdv(): void
    {
        $pedido = $this->pedido();
        $this->documento($pedido);
        $this->actingAs($this->usuario)->postJson('/control-pedidos/autoguardar', $this->captura($pedido, [
            'solicitud_preparacion' => [
                'modalidad_entrega' => 'tienda',
                'caso_tienda' => 'mismo_dia_pdv',
                'area_revision' => 'preparacion_tienda',
                'almacen_revision_id' => $this->pdv->id,
            ],
        ]))->assertOk();

        $this->actingAs($this->usuario)->postJson(
            "/control-pedidos/{$pedido->id}/solicitud-tienda",
            $this->solicitud($pedido->fresh(), 'preparacion_tienda', $this->pdv->id)
        )->assertStatus(422)->assertJsonValidationErrors('caso_tienda');

        $fresco = $pedido->fresh();
        $this->assertSame('mismo_dia_pdv', $fresco->solicitud_preparacion_json['caso_tienda']);
        $this->assertFalse((bool) $fresco->prioridad_md);
        $this->assertSame(0, PedidoBmaTareaPreparacion::query()->count());
    }

    public function test_tienda_md_se_identifica_desde_la_solicitud_a_cedis_con_sucursal_sin_permiso_especial_md(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-04 11:00:00', 'America/Mexico_City'));
        $this->beforeApplicationDestroyed(fn () => Carbon::setTestNow());
        CatalogoModalidadPreparacionPedido::query()->firstOrCreate(['codigo' => 'RECOGE_TIENDA_TRANSFERENCIA'], ['nombre' => 'Transferencia', 'activo' => true]);
        $pedido = $this->pedido();
        $this->documento($pedido);
        $this->actingAs($this->usuario)->postJson('/control-pedidos/autoguardar', $this->captura($pedido, [
            'sucursal_destino_id' => $pedido->sucursal_destino_id,
            'solicitud_preparacion' => ['modalidad_entrega' => 'tienda', 'caso_tienda' => 'mismo_dia_pdv', 'area_revision' => 'control_pedidos',
                'codigo_modalidad' => 'RECOGE_TIENDA_TRANSFERENCIA', 'atencion_tienda' => 'prevista'],
        ]))->assertOk();
        $this->actingAs($this->usuario)->postJson("/control-pedidos/{$pedido->id}/solicitud-tienda", $this->solicitud($pedido->fresh(), 'control_pedidos'))->assertOk();
        $this->assertSame('prevista', $pedido->fresh()->solicitud_preparacion_json['atencion_tienda']);
        $this->assertSame('2026-11-04', $pedido->fresh()->solicitud_preparacion_json['tienda_md_solicitud']['fecha']);
        $this->assertFalse($pedido->fresh()->prioridad_md);
        $this->assertSame(0, PedidoBmaTareaPreparacion::query()->count());
    }

    public function test_la_revision_de_tienda_se_toma_y_responde_sin_el_falso_cierre(): void
    {
        Storage::fake('public');
        $this->permitirRevision();
        $tarea = $this->solicitarTienda();

        $this->actingAs($this->usuario)->postJson(
            '/control-pedidos/tienda/tareas/'.$tarea->id.'/tomar',
            ['version' => $tarea->version]
        )->assertOk();

        $respuesta = $this->actingAs($this->usuario)->post(
            '/control-pedidos/tienda/tareas/'.$tarea->id.'/disponibilidad',
            [
                'confirmacion_explicita' => 1,
                'evidencias' => [UploadedFile::fake()->image('conjunto.jpg', 40, 40)],
            ],
            ['Accept' => 'application/json']
        )->assertOk();

        $tarea = $tarea->fresh();
        $pedido = $tarea->pedido->fresh();
        $this->assertFalse($respuesta->json('idempotente'));
        $this->assertSame('sin_excepciones', $respuesta->json('estado'));
        $this->assertSame(ResponderDisponibilidadInicialService::DECISION_TIENDA, $respuesta->json('decision'));
        $this->assertStringNotContainsString('cierre', mb_strtolower((string) $respuesta->json('decision')));
        $this->assertSame(PedidoBmaTareaPreparacion::ESTADO_EN_ATENCION, $tarea->estado);
        $this->assertNull($tarea->fecha_limite);
        $this->assertFalse((bool) $pedido->prioridad_md);
        $this->assertNull($pedido->pago_validado_at);
        $this->assertSame(
            PedidoBmaCumplimientoFisico::ESTADO_POR_SEPARAR,
            $tarea->cumplimientoFisico->estado
        );
        $this->assertSame(1, $pedido->documentos()->where('tipo', PedidoBmaDocumento::TIPO_EVIDENCIA_CONDICION)->count());
        $this->assertSame(1, $tarea->documentos()->where('tipo_evidencia', PedidoBmaTareaDocumento::TIPO_EVIDENCIA_GENERAL)->count());
        $visible = RespuestaPreparacionTienda::datos($tarea->fresh(['documentos', 'productos', 'atendidaPor', 'cumplimientoFisico']));
        $this->assertSame('image/jpeg', $visible['documentos'][0]['mime_type']);
        $this->assertSame(0, $pedido->revisionesProducto()->count());

        $this->actingAs($this->usuario)->post(
            '/control-pedidos/tienda/tareas/'.$tarea->id.'/disponibilidad',
            [
                'confirmacion_explicita' => 1,
                'evidencias' => [UploadedFile::fake()->create('otro.pdf', 20, 'application/pdf')],
            ],
            ['Accept' => 'application/json']
        )->assertOk()->assertJsonPath('idempotente', true);
        $this->assertSame(1, $pedido->documentos()->where('tipo', PedidoBmaDocumento::TIPO_EVIDENCIA_CONDICION)->count());

        $this->actingAs($this->usuario)->getJson('/control-pedidos/tienda/tareas/'.$tarea->id)
            ->assertOk()
            ->assertJsonPath('documentos_solicitud.0.nombre_original', 'pedido-ficticio.pdf')
            ->assertJsonPath('tarea.pedido.revision_disponibilidad.estado', 'sin_excepciones');
    }

    public function test_la_ausencia_de_marcas_no_concluye_la_revision(): void
    {
        Storage::fake('public');
        $this->permitirRevision();
        $tarea = $this->solicitarTienda();
        $this->actingAs($this->usuario)->postJson(
            '/control-pedidos/tienda/tareas/'.$tarea->id.'/tomar',
            ['version' => $tarea->version]
        )->assertOk();

        $this->actingAs($this->usuario)->post(
            '/control-pedidos/tienda/tareas/'.$tarea->id.'/disponibilidad',
            ['confirmacion_explicita' => 0, 'evidencias' => [UploadedFile::fake()->create('vacio.pdf', 20, 'application/pdf')]],
            ['Accept' => 'application/json']
        )->assertStatus(422);

        $this->assertNull($tarea->pedido->fresh()->solicitud_preparacion_json['revision_disponibilidad'] ?? null);
        $this->assertSame(0, $tarea->pedido->revisionesProducto()->count());
    }

    public function test_una_instruccion_sustituida_no_puede_responderse(): void
    {
        Storage::fake('public');
        $this->permitirRevision();
        $tarea = $this->solicitarTienda();
        $tarea->update(['estado' => PedidoBmaTareaPreparacion::ESTADO_SUSTITUIDA]);

        $this->actingAs($this->usuario)->post(
            '/control-pedidos/tienda/tareas/'.$tarea->id.'/disponibilidad',
            [
                'confirmacion_explicita' => 1,
                'evidencias' => [UploadedFile::fake()->create('pedido-completo.pdf', 20, 'application/pdf')],
            ],
            ['Accept' => 'application/json']
        )->assertStatus(422)->assertJsonValidationErrors('instruccion');

        $this->assertSame(0, $tarea->pedido->revisionesProducto()->count());
        $this->assertSame(1, PedidoBmaTareaPreparacion::query()->count());
    }

    public function test_cedis_separa_captura_y_fotos_del_defecto_con_reintento_idempotente(): void
    {
        Storage::fake('public');
        Notification::fake();
        $pedido = $this->pedido();
        $this->documento($pedido);
        $this->actingAs($this->usuario)->postJson('/control-pedidos/'.$pedido->id.'/solicitud-tienda', $this->solicitud($pedido, 'control_pedidos'))->assertOk();
        $url = '/control-pedidos/cedis/'.$pedido->id.'/disponibilidad';
        $datos = [
            'confirmacion_explicita' => 0,
            'excepciones' => [['descripcion' => 'Perfume con defecto', 'sku' => 'PRUEBA', 'cantidad' => 1, 'condicion' => 'detalle', 'detalle' => 'Caja rota']],
            'evidencias' => [UploadedFile::fake()->image('captura.jpg')],
        ];
        $this->post($url, $datos, ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('excepciones.0.evidencias');
        $this->assertSame(0, $pedido->revisionesProducto()->count());
        $datos['excepciones'][0]['evidencias'] = [UploadedFile::fake()->create('invalido.pdf', 10, 'application/pdf')];
        $this->post($url, $datos, ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('excepciones.0.evidencias.0');
        $datos['excepciones'][0]['evidencias'] = [UploadedFile::fake()->image('defecto.jpg')];
        $this->post($url, $datos, ['Accept' => 'application/json'])->assertOk()->assertJsonPath('estado', 'excepciones_pendientes');
        $this->post($url, $datos, ['Accept' => 'application/json'])->assertOk()->assertJsonPath('idempotente', true);
        $pieza = $pedido->revisionesProducto()->sole();
        $this->assertSame('defecto.jpg', $pieza->evidencias()->sole()->nombre_original);
        Storage::disk('public')->assertExists($pieza->evidencias()->sole()->ruta_archivo);
        $this->assertSame(1, $pedido->documentos()->where('relacion_tipo', PedidoBmaDocumento::RELACION_REVISION_GENERAL)->count());
        $this->assertSame(1, $pedido->documentos()->where('relacion_tipo', PedidoBmaDocumento::RELACION_REVISION_PRODUCTO)->count());
        $this->getJson('/control-pedidos/'.$pedido->id.'/disponibilidad-tienda')->assertOk()->assertJsonFragment(['nombre_original' => 'defecto.jpg']);
        $this->assertSame(0, $pedido->tareasPreparacion()->count());
    }

    public function test_cedis_conserva_unidades_repetidas_atributos_y_hasta_cinco_fotos(): void
    {
        Storage::fake('public');
        Notification::fake();
        $pedido = $this->pedido();
        $this->documento($pedido);
        $this->actingAs($this->usuario)->postJson('/control-pedidos/'.$pedido->id.'/solicitud-tienda', $this->solicitud($pedido, 'control_pedidos'))->assertOk();
        $fotos = fn (int $cantidad) => array_map(fn ($i) => UploadedFile::fake()->image("defecto-{$i}.jpg"), range(1, $cantidad));
        $datos = [
            'confirmacion_explicita' => 0,
            'excepciones' => [
                ['descripcion' => 'Perfume', 'sku' => 'MISMO-SKU', 'cantidad' => 1, 'condicion' => 'detalle', 'detalle' => 'Caja rota', 'unica_pieza' => 1, 'mejor_ejemplar' => 1, 'evidencias' => $fotos(6)],
                ['descripcion' => 'Perfume', 'sku' => 'MISMO-SKU', 'cantidad' => 1, 'condicion' => 'faltante', 'detalle' => 'Sin existencias', 'unica_pieza' => 0, 'mejor_ejemplar' => 0],
            ],
            'evidencias' => [UploadedFile::fake()->image('captura.jpg')],
        ];
        $url = '/control-pedidos/cedis/'.$pedido->id.'/disponibilidad';
        $this->post($url, $datos, ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('excepciones.0.evidencias');
        $this->assertSame(0, $pedido->revisionesProducto()->count());
        $datos['excepciones'][0]['evidencias'] = $fotos(5);
        $datos['excepciones'][1]['unica_pieza'] = 1;
        $this->post($url, $datos, ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('excepciones.1.condicion');
        $datos['excepciones'][1]['unica_pieza'] = 0;
        $this->post($url, $datos, ['Accept' => 'application/json'])->assertOk();
        $this->post($url, $datos, ['Accept' => 'application/json'])->assertOk()->assertJsonPath('idempotente', true);
        $piezas = $pedido->revisionesProducto()->orderBy('orden')->get();
        $this->assertCount(2, $piezas);
        $this->assertSame([1, 1], $piezas->pluck('cantidad_solicitada')->all());
        $this->assertTrue($piezas[0]->unica_pieza);
        $this->assertTrue($piezas[0]->mejor_ejemplar);
        $this->assertFalse($piezas[1]->unica_pieza);
        $this->assertFalse($piezas[1]->mejor_ejemplar);
        $this->assertSame(5, $piezas[0]->evidencias()->count());
        $this->assertSame(0, $piezas[1]->evidencias()->count());
        $this->getJson('/control-pedidos/'.$pedido->id.'/disponibilidad-tienda')->assertOk()->assertJsonPath('revisiones_producto.0.unica_pieza', true)->assertJsonPath('revisiones_producto.0.mejor_ejemplar', true);
    }

    public function test_cedis_admite_atributos_sin_comentario_y_cantidad_interna_por_unidad(): void
    {
        Storage::fake('public');
        Notification::fake();
        foreach ([[1, 0], [0, 1], [1, 1]] as [$unica, $mejor]) {
            $pedido = $this->pedido();
            $this->documento($pedido);
            $this->actingAs($this->usuario)->postJson('/control-pedidos/'.$pedido->id.'/solicitud-tienda', $this->solicitud($pedido, 'control_pedidos'))->assertOk();
            $datos = [
                'confirmacion_explicita' => 0,
                'excepciones' => [['descripcion' => 'Perfume', 'sku' => 'ATRIBUTO', 'condicion' => 'detalle', 'detalle' => '', 'unica_pieza' => $unica, 'mejor_ejemplar' => $mejor, 'evidencias' => [UploadedFile::fake()->image('unidad.jpg')]]],
                'evidencias' => [UploadedFile::fake()->image('captura.jpg')],
            ];
            $url = '/control-pedidos/cedis/'.$pedido->id.'/disponibilidad';
            $this->post($url, $datos, ['Accept' => 'application/json'])->assertOk();
            $this->post($url, $datos, ['Accept' => 'application/json'])->assertOk()->assertJsonPath('idempotente', true);
            $pieza = $pedido->revisionesProducto()->sole();
            $this->assertSame(1, $pieza->cantidad_solicitada);
            $this->assertSame('', $pieza->comentario);
            $this->assertSame((bool) $unica, $pieza->unica_pieza);
            $this->assertSame((bool) $mejor, $pieza->mejor_ejemplar);
            $this->assertSame(1, $pieza->evidencias()->count());
        }
    }

    public function test_cedis_defecto_sin_atributos_requiere_descripcion(): void
    {
        Storage::fake('public');
        $pedido = $this->pedido();
        $this->documento($pedido);
        $this->actingAs($this->usuario)->postJson('/control-pedidos/'.$pedido->id.'/solicitud-tienda', $this->solicitud($pedido, 'control_pedidos'))->assertOk();
        $this->post('/control-pedidos/cedis/'.$pedido->id.'/disponibilidad', [
            'excepciones' => [['descripcion' => 'Perfume', 'condicion' => 'detalle', 'evidencias' => [UploadedFile::fake()->image('unidad.jpg')]]],
            'evidencias' => [UploadedFile::fake()->image('captura.jpg')],
        ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('excepciones.0.detalle');
        $this->assertSame(0, $pedido->revisionesProducto()->count());
    }

    public function test_cedis_sin_existencias_no_exige_ni_admite_fotos_del_producto(): void
    {
        Storage::fake('public');
        Notification::fake();
        $pedido = $this->pedido();
        $this->documento($pedido);
        $this->actingAs($this->usuario)->postJson('/control-pedidos/'.$pedido->id.'/solicitud-tienda', $this->solicitud($pedido, 'control_pedidos'))->assertOk();
        $datos = [
            'confirmacion_explicita' => 0,
            'excepciones' => [['descripcion' => 'Agotado', 'condicion' => 'faltante', 'evidencias' => [UploadedFile::fake()->image('no-aplica.jpg')]]],
            'evidencias' => [UploadedFile::fake()->image('captura.jpg')],
        ];
        $url = '/control-pedidos/cedis/'.$pedido->id.'/disponibilidad';
        $this->post($url, $datos, ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('excepciones.0.evidencias');
        unset($datos['excepciones'][0]['evidencias']);
        $this->post($url, $datos, ['Accept' => 'application/json'])->assertOk();
        $this->assertSame(1, $pedido->revisionesProducto()->sole()->cantidad_solicitada);
        $this->assertSame('Sin existencias', $pedido->revisionesProducto()->sole()->comentario);
        $this->assertSame(0, $pedido->revisionesProducto()->sole()->evidencias()->count());
        $this->assertSame(1, $pedido->documentos()->where('relacion_tipo', PedidoBmaDocumento::RELACION_REVISION_GENERAL)->count());
    }

    public function test_la_consulta_cedis_devuelve_disponibilidad_sin_traslado(): void
    {
        Storage::fake('public');
        $this->estatusRespondido();
        $pedido = $this->pedido();
        $this->documento($pedido);
        $this->actingAs($this->usuario)->postJson(
            "/control-pedidos/{$pedido->id}/solicitud-tienda",
            $this->solicitud($pedido, 'control_pedidos')
        )->assertOk();

        $respuesta = $this->actingAs($this->usuario)->post(
            "/control-pedidos/cedis/{$pedido->id}/disponibilidad",
            [
                'confirmacion_explicita' => 1,
                'evidencias' => [UploadedFile::fake()->create('disponibilidad.pdf', 20, 'application/pdf')],
            ],
            ['Accept' => 'application/json']
        )->assertOk();

        $fresco = $pedido->fresh(['estatus']);
        $this->assertSame(ResponderDisponibilidadInicialService::DECISION_CEDIS, $respuesta->json('decision'));
        $this->assertSame('pesaje_listo', $fresco->estatus_envio);
        $this->assertSame(CatalogoEstatusPedido::FASE_PESAJE_RESPONDIDO, $fresco->estatus->fase_ciclo);
        $this->assertFalse((bool) $fresco->prioridad_md);
        $this->assertNull($fresco->pago_validado_at);
        $this->assertSame(0, PedidoBmaTareaPreparacion::query()->count());
        $this->assertSame(
            ResponderDisponibilidadInicialService::DECISION_CEDIS,
            app(ExpedienteRegistrarPedidos::class)->desdePedido($fresco)['decision_pendiente']
        );
    }

    public function test_el_faltante_queda_pendiente_del_cliente_y_no_se_acepta(): void
    {
        Storage::fake('public');
        $pedido = $this->pedido();
        $this->documento($pedido);
        $this->actingAs($this->usuario)->postJson(
            "/control-pedidos/{$pedido->id}/solicitud-tienda",
            $this->solicitud($pedido, 'control_pedidos')
        )->assertOk();

        $this->actingAs($this->usuario)->post(
            "/control-pedidos/cedis/{$pedido->id}/disponibilidad",
            [
                'confirmacion_explicita' => 0,
                'excepciones' => [[
                    'descripcion' => 'Crema corporal',
                    'cantidad' => 2,
                    'condicion' => 'faltante',
                    'detalle' => 'No está en el anaquel de CEDIS',
                ]],
                'evidencias' => [UploadedFile::fake()->create('faltante.pdf', 20, 'application/pdf')],
            ],
            ['Accept' => 'application/json']
        )->assertOk()->assertJsonPath('estado', 'excepciones_pendientes');

        $fresco = $pedido->fresh(['estatus']);
        $pieza = $fresco->revisionesProducto()->first();
        $this->assertSame(PedidoBmaRevisionProducto::ESTADO_SIN_EXISTENCIA, $pieza->estado_fisico);
        $this->assertNull($pieza->resolucion);
        $this->assertSame(2, (int) $pieza->cantidad_solicitada);
        $this->assertSame('pendiente_pesaje', $fresco->estatus_envio);
        $this->assertNotSame(CatalogoEstatusPedido::FASE_PESAJE_RESPONDIDO, $fresco->estatus->fase_ciclo);
        $expediente = app(ExpedienteRegistrarPedidos::class)->desdePedido($fresco);
        $this->assertSame(ResponderDisponibilidadInicialService::DECISION_EXCEPCIONES, $expediente['decision_pendiente']);
        $this->assertSame('Crema corporal', $expediente['excepciones'][0]['descripcion']);
        $this->assertNull($pieza->fresh()->resolucion);

        $this->getJson('/control-pedidos/'.$pedido->id.'/pagos')->assertOk()
            ->assertJsonPath('puede_cerrar_consulta', false);
        // Una respuesta sustituida conserva el bloqueo aunque tenga incidencias registradas.
        $json = $fresco->solicitud_preparacion_json;
        $json['revision_disponibilidad']['vigente'] = false;
        $fresco->update(['solicitud_preparacion_json' => $json]);
        $this->getJson('/control-pedidos/'.$pedido->id.'/pagos')->assertOk()
            ->assertJsonPath('puede_cerrar_consulta', false);
        $this->postJson('/control-pedidos/'.$pedido->id.'/cerrar-consulta')->assertStatus(422);
        $json['revision_disponibilidad']['vigente'] = true;
        $json['anexo_sin_remision'] = [['entrada' => 'documento', 'documento_id' => 1]];
        $fresco->update(['solicitud_preparacion_json' => $json]);
        $this->assertFalse($fresco->fresh()->puedeCerrarConsulta());
        unset($json['anexo_sin_remision']);
        $fresco->update(['solicitud_preparacion_json' => $json]);
        $this->postJson('/control-pedidos/'.$pedido->id.'/cerrar-consulta')->assertUnprocessable();
        $this->postJson('/control-pedidos/'.$pedido->id.'/disponibilidad-tienda', ['revision_id' => $pieza->id, 'accion' => 'retirar', 'solicitud_id' => (string) Str::uuid()])->assertOk();
        $this->postJson('/control-pedidos/'.$pedido->id.'/cerrar-consulta')->assertOk();
        $this->assertTrue($fresco->fresh()->consultaCerrada());
        $this->postJson('/control-pedidos/'.$pedido->id.'/cobertura-tienda', ['cobertura' => 'transferencia'])->assertOk();
        $this->assertNull($fresco->fresh()->pago_validado_at);
        $this->assertSame(0, PedidoBmaTareaPreparacion::query()->count());
    }

    public function test_ventas_retira_faltantes_sin_sku_y_conserva_pendiente_el_retiro_fisico(): void
    {
        $pedido = $this->pedido(['total_mercancia' => 899, 'solicitud_preparacion_json' => [
            'modalidad_entrega' => 'tienda', 'area_revision' => 'control_pedidos',
            'revision_disponibilidad' => ['estado' => 'excepciones_pendientes', 'vigente' => true],
        ]]);
        $faltante = $pedido->revisionesProducto()->create([
            'descripcion_producto' => 'Crema sin SKU', 'cantidad_solicitada' => 2,
            'estado_fisico' => 'sin_existencia', 'orden' => 1,
        ]);
        $ruta = "/control-pedidos/{$pedido->id}/retiro-piezas-tienda";
        $this->actingAs($this->usuario)->postJson($ruta, [
            'motivo' => 'Cliente acepta retirar el faltante',
            'piezas' => [['revision_id' => $faltante->id, 'descripcion' => 'Crema sin SKU', 'cantidad' => 1]],
        ])->assertUnprocessable();
        $this->assertNull($faltante->fresh()->resolucion);
        $this->postJson($ruta, [
            'motivo' => 'Cliente acepta retirar el faltante',
            'piezas' => [['revision_id' => $faltante->id, 'descripcion' => 'Crema sin SKU', 'cantidad' => 2]],
        ])->assertOk();
        $fresco = $pedido->fresh();
        $this->assertSame('retirar', $faltante->fresh()->resolucion);
        $this->assertFalse($fresco->tieneSinExistenciaAbierta());
        $this->assertTrue($fresco->tieneAjusteTiendaPendiente());
        $this->assertSame('aplicada', $fresco->solicitud_preparacion_json['retiro_piezas'][0]['estado']);
        $this->assertFalse($fresco->puedeCerrarConsulta());
        $this->assertEquals(899, $fresco->total_mercancia);
        $this->assertNull($fresco->pago_validado_at);
        $this->assertNull($fresco->numero_remision);
        $this->assertSame(0, PedidoBmaTareaPreparacion::query()->count());
        // Una selección ajena o ya atendida no se convierte en un retiro físico pendiente.
        $this->postJson($ruta, ['motivo' => 'Repetido', 'piezas' => [
            ['revision_id' => $faltante->id, 'descripcion' => 'Crema', 'cantidad' => 2],
        ]])->assertUnprocessable();
        $otro = $pedido->revisionesProducto()->create([
            'sku' => 'SIN-STOCK', 'descripcion_producto' => 'Perfume agotado',
            'cantidad_solicitada' => 1, 'estado_fisico' => 'sin_existencia', 'orden' => 2,
        ]);
        $this->postJson($ruta, ['motivo' => 'Ajustar ambas piezas', 'piezas' => [
            ['sku' => 'SIN-STOCK', 'descripcion' => 'Perfume agotado', 'cantidad' => 1],
            ['sku' => 'APARTADO', 'descripcion' => 'Perfume reservado', 'cantidad' => 1],
        ]])->assertOk();
        $fresco = $pedido->fresh();
        $this->assertSame('retirar', $otro->fresh()->resolucion);
        $this->assertTrue($fresco->tieneAjusteTiendaPendiente());
        $this->assertFalse($fresco->puedeCerrarConsulta());
        $this->assertSame('APARTADO', $fresco->solicitud_preparacion_json['retiro_piezas'][1]['piezas'][0]['sku']);
    }

    public function test_el_retiro_de_piezas_queda_documentado_despues_de_la_respuesta(): void
    {
        $pedido = $this->pedido([
            'solicitud_preparacion_json' => [
                'version' => 1,
                'modalidad_entrega' => 'tienda',
                'area_revision' => 'preparacion_tienda',
                'revision_disponibilidad' => [
                    'estado' => 'sin_excepciones',
                    'confirmada' => true,
                ],
            ],
        ]);

        $this->actingAs($this->usuario)->postJson("/control-pedidos/{$pedido->id}/retiro-piezas-tienda", [
            'motivo' => 'El cliente ya no quiere la pieza de regalo',
            'piezas' => [[
                'sku' => 'SKU-1',
                'descripcion' => 'Crema corporal',
                'cantidad' => 1,
            ]],
        ])->assertOk()->assertJsonPath('ok', true);

        $leida = $pedido->fresh()->solicitud_preparacion;
        $this->assertSame('El cliente ya no quiere la pieza de regalo', $leida['retiro_piezas'][0]['motivo']);
        $this->assertSame('Crema corporal', $leida['retiro_piezas'][0]['piezas'][0]['descripcion']);
        $this->assertNull($pedido->fresh()->pesaje_respondido_at);
    }

    public function test_el_retiro_conserva_varias_evidencias_de_la_peticion(): void
    {
        Storage::fake('public');
        $pedido = $this->pedido([
            'solicitud_preparacion_json' => [
                'version' => 1,
                'modalidad_entrega' => 'tienda',
                'area_revision' => 'preparacion_tienda',
                'revision_disponibilidad' => [
                    'estado' => 'sin_excepciones',
                    'confirmada' => true,
                ],
            ],
        ]);

        $this->actingAs($this->usuario)->post(
            "/control-pedidos/{$pedido->id}/retiro-piezas-tienda",
            [
                'motivo' => 'El cliente ya no lo quiere',
                'piezas' => [[
                    'sku' => 'SKU-1',
                    'descripcion' => 'Crema corporal',
                    'cantidad' => 1,
                ]],
                'evidencias' => [
                    UploadedFile::fake()->image('captura-1.jpg'),
                    UploadedFile::fake()->image('captura-2.jpg'),
                    UploadedFile::fake()->image('captura-3.jpg'),
                ],
            ],
            ['Accept' => 'application/json']
        )->assertOk();

        $documentos = $pedido->fresh()->documentos()->orderBy('orden')->get();
        $this->assertCount(3, $documentos);
        $this->assertSame(
            ['captura-1.jpg', 'captura-2.jpg', 'captura-3.jpg'],
            $documentos->pluck('nombre_original')->all()
        );
    }

    public function test_el_retiro_no_procede_sin_respuesta(): void
    {
        $pedido = $this->pedido();

        $this->actingAs($this->usuario)->postJson("/control-pedidos/{$pedido->id}/retiro-piezas-tienda", [
            'motivo' => 'Retiro anticipado',
            'piezas' => [[
                'sku' => 'SKU-1',
                'descripcion' => 'Crema corporal',
                'cantidad' => 1,
            ]],
        ])->assertStatus(422);
    }

    public function test_el_pdf_del_pedido_no_sustituye_la_foto_del_conjunto(): void
    {
        Storage::fake('public');
        $this->permitirRevision();
        $tarea = $this->solicitarTienda();
        $this->actingAs($this->usuario)->postJson(
            '/control-pedidos/tienda/tareas/'.$tarea->id.'/tomar',
            ['version' => $tarea->version]
        )->assertOk();

        $this->actingAs($this->usuario)->post(
            '/control-pedidos/tienda/tareas/'.$tarea->id.'/disponibilidad',
            [
                'confirmacion_explicita' => 1,
                'evidencias' => [UploadedFile::fake()->create('pedido-completo.pdf', 20, 'application/pdf')],
            ],
            ['Accept' => 'application/json']
        )->assertStatus(422)->assertJsonValidationErrors('evidencias');

        $this->assertNull($tarea->pedido->fresh()->solicitud_preparacion_json['revision_disponibilidad'] ?? null);
    }

    public function test_una_foto_ya_guardada_se_reconoce_sin_volver_a_cargarla(): void
    {
        Storage::fake('public');
        $this->permitirRevision();
        $tarea = $this->solicitarTienda();
        $this->actingAs($this->usuario)->postJson(
            '/control-pedidos/tienda/tareas/'.$tarea->id.'/tomar',
            ['version' => $tarea->version]
        )->assertOk();
        $foto = $tarea->documentos()->create([
            'tipo_evidencia' => PedidoBmaTareaDocumento::TIPO_EVIDENCIA_GENERAL,
            'ruta_interna' => 'pedidos_bma/tareas_preparacion/'.$tarea->id.'/conjunto.jpg',
            'nombre_original' => 'conjunto.jpg',
            'mime_type' => 'image/jpeg',
            'tamano_bytes' => 1200,
            'subido_por_id' => $this->usuario->id,
            'subido_at' => now(),
        ]);

        $this->actingAs($this->usuario)->post(
            '/control-pedidos/tienda/tareas/'.$tarea->id.'/disponibilidad',
            [
                'confirmacion_explicita' => 1,
                'evidencias_guardadas' => [$foto->id],
            ],
            ['Accept' => 'application/json']
        )->assertOk()->assertJsonPath('estado', 'sin_excepciones');

        $pedido = $tarea->pedido->fresh();
        $this->assertSame(1, $pedido->documentos()->where('tipo', PedidoBmaDocumento::TIPO_EVIDENCIA_CONDICION)->count());
        $this->assertSame(0, $pedido->revisionesProducto()->count());
        $visible = RespuestaPreparacionTienda::datos($tarea->fresh(['documentos', 'productos', 'atendidaPor', 'cumplimientoFisico']));
        $this->assertSame($foto->id, $visible['documentos'][0]['id']);
    }

    public function test_la_revision_conserva_atributos_y_solo_registra_la_pieza_afectada(): void
    {
        Storage::fake('public');
        $this->permitirRevision();
        $tarea = $this->solicitarTienda();
        $this->actingAs($this->usuario)->postJson(
            '/control-pedidos/tienda/tareas/'.$tarea->id.'/tomar',
            ['version' => $tarea->version]
        )->assertOk();

        $this->actingAs($this->usuario)->post(
            '/control-pedidos/tienda/tareas/'.$tarea->id.'/disponibilidad',
            [
                'confirmacion_explicita' => 0,
                'version' => 1,
                'lineas' => [
                    [
                        'sku' => 'SKU-UNICA',
                        'descripcion' => 'Frasco único',
                        'cantidad' => 1,
                        'estado_fisico' => 'bueno',
                        'observacion' => '',
                        'unica_pieza' => 1,
                        'mejor_ejemplar' => 1,
                    ],
                    [
                        'sku' => 'SKU-FALTA',
                        'descripcion' => 'Crema agotada',
                        'unica_pieza' => 1,
                        'mejor_ejemplar' => 1,
                        'cantidad' => 0,
                        'estado_fisico' => 'sin_existencia',
                        'observacion' => 'Sin existencias',
                    ],
                ],
                'excepciones' => [[
                    'descripcion' => 'Crema agotada',
                    'sku' => 'SKU-FALTA',
                    'cantidad' => 1,
                    'condicion' => 'faltante',
                    'detalle' => 'Sin existencias',
                ]],
                'evidencias' => [UploadedFile::fake()->image('conjunto.jpg', 40, 40)],
            ],
            ['Accept' => 'application/json']
        )->assertOk()->assertJsonPath('estado', 'excepciones_pendientes');

        $tarea = $tarea->fresh(['productos', 'documentos', 'atendidaPor', 'cumplimientoFisico']);
        $unica = $tarea->productos->firstWhere('sku', 'SKU-UNICA');
        $this->assertTrue((bool) $unica->unica_pieza);
        $this->assertTrue((bool) $unica->mejor_ejemplar);
        $this->assertSame('bueno', $unica->estado_fisico);
        $faltante = $tarea->productos->firstWhere('sku', 'SKU-FALTA');
        $this->assertFalse((bool) $faltante->unica_pieza);
        $this->assertFalse((bool) $faltante->mejor_ejemplar);
        $this->assertSame(1, $tarea->pedido->revisionesProducto()->count());
        $this->assertSame('Crema agotada', $tarea->pedido->revisionesProducto()->value('descripcion_producto'));
        $visible = RespuestaPreparacionTienda::datos($tarea);
        $this->assertTrue($visible['productos'][0]['unica_pieza'] || $visible['productos'][1]['unica_pieza']);
        $this->assertSame('image/jpeg', $visible['documentos'][0]['mime_type']);
    }

    public function test_el_anexo_documental_no_exige_sku_y_queda_pendiente_de_identificar(): void
    {
        Storage::fake('public');
        $this->permitirRevision();
        $tarea = $this->solicitarTienda();
        $pedido = $tarea->pedido;

        $this->actingAs($this->usuario)->post(
            "/control-pedidos/{$pedido->id}/anexo-tienda",
            [
                'entrada' => 'documento',
                'evidencia' => UploadedFile::fake()->create('anexo.pdf', 20, 'application/pdf'),
            ],
            ['Accept' => 'application/json']
        )->assertOk();

        $fresco = $pedido->fresh();
        $linea = $fresco->solicitud_preparacion_json['anexo_sin_remision'][0];
        $this->assertSame('documento', $linea['entrada']);
        $this->assertTrue($linea['pendiente_identificacion']);
        $this->assertArrayNotHasKey('sku', $linea);
        $this->assertSame(1, $fresco->documentos()->where('tipo', PedidoBmaDocumento::TIPO_ANEXO_PIEZAS)->count());

        $this->actingAs($this->usuario)->postJson(
            '/control-pedidos/tienda/tareas/'.$tarea->id.'/tomar',
            ['version' => $tarea->version]
        )->assertOk();
        $this->actingAs($this->usuario)->getJson('/control-pedidos/tienda/tareas/'.$tarea->id)
            ->assertOk()
            ->assertJsonPath('tarea.pedido.anexos_pendientes.0.entrada', 'documento');
    }

    public function test_el_anexo_por_catalogo_conserva_las_cantidades(): void
    {
        Storage::fake('public');
        $pedido = $this->pedido();
        $this->documento($pedido);

        $this->actingAs($this->usuario)->post(
            "/control-pedidos/{$pedido->id}/anexo-tienda",
            [
                'entrada' => 'catalogo',
                'piezas' => [
                    ['sku' => 'SKU-A', 'descripcion' => 'Crema', 'cantidad' => 2],
                    ['sku' => 'SKU-B', 'descripcion' => 'Loción', 'cantidad' => 1],
                ],
                'evidencia' => UploadedFile::fake()->create('anexo.pdf', 20, 'application/pdf'),
            ],
            ['Accept' => 'application/json']
        )->assertOk();

        $lineas = $pedido->fresh()->solicitud_preparacion_json['anexo_sin_remision'];
        $this->assertSame(2, $lineas[0]['cantidad']);
        $this->assertSame('SKU-A', $lineas[0]['sku']);
        $this->assertSame(1, $lineas[1]['cantidad']);
        $this->assertSame('catalogo', $lineas[1]['entrada']);
    }

    public function test_cerrar_consulta_sin_respuesta_responde_json(): void
    {
        $pedido = $this->pedido();

        $this->actingAs($this->usuario)
            ->postJson("/control-pedidos/{$pedido->id}/cerrar-consulta")
            ->assertStatus(422)
            ->assertJsonStructure(['message']);
    }

    public function test_el_retiro_documental_conserva_todas_las_capturas_sin_sku(): void
    {
        Storage::fake('public');
        $pedido = $this->pedido([
            'solicitud_preparacion_json' => [
                'version' => 1,
                'modalidad_entrega' => 'tienda',
                'area_revision' => 'preparacion_tienda',
                'revision_disponibilidad' => [
                    'estado' => 'sin_excepciones',
                    'confirmada' => true,
                ],
            ],
        ]);
        $archivos = [];
        for ($i = 1; $i <= 21; $i++) {
            $archivos[] = UploadedFile::fake()->image("pieza-{$i}.jpg");
        }

        $this->actingAs($this->usuario)->post(
            "/control-pedidos/{$pedido->id}/retiro-piezas-tienda",
            [
                'entrada' => 'documento',
                'motivo_opcion' => 'El cliente ya no lo quiere',
                'motivo' => 'El cliente ya no lo quiere',
                'documentos' => $archivos,
            ],
            ['Accept' => 'application/json']
        )->assertOk();

        $retiro = $pedido->fresh()->solicitud_preparacion_json['retiro_piezas'][0];
        $this->assertSame('pendiente', $retiro['estado']);
        $this->assertSame('documento', $retiro['entrada']);
        $this->assertSame([], $retiro['piezas']);
        $this->assertCount(21, $retiro['documentos_piezas']);
        $this->assertSame(21, $pedido->fresh()->documentos()->where('relacion_tipo', PedidoBmaDocumento::RELACION_RETIRO_PIEZAS)->count());
        $this->assertFalse($pedido->fresh()->solicitud_preparacion_json['revision_disponibilidad']['aceptacion_vigente']);
    }

    public function test_el_auxiliar_aplica_una_vez_el_retiro_de_catalogo_y_rechaza_lo_ajeno(): void
    {
        $this->permitirRevision();
        $tarea = $this->solicitarTienda();
        $this->actingAs($this->usuario)->postJson(
            '/control-pedidos/tienda/tareas/'.$tarea->id.'/tomar',
            ['version' => $tarea->version]
        )->assertOk();
        $tarea->productos()->create([
            'sku' => 'SKU-REAL',
            'descripcion_snapshot' => 'Crema comprometida',
            'cantidad_solicitada' => 4,
            'cantidad_encontrada' => 4,
            'estado_fisico' => 'bueno',
            'orden' => 1,
        ]);
        $pedido = $tarea->pedido->fresh();
        $json = $pedido->solicitud_preparacion_json;
        $json['revision_disponibilidad'] = ['estado' => 'sin_excepciones', 'confirmada' => true];
        $pedido->update(['solicitud_preparacion_json' => $json]);

        $this->actingAs($this->usuario)->postJson("/control-pedidos/{$pedido->id}/retiro-piezas-tienda", [
            'entrada' => 'catalogo',
            'motivo_opcion' => 'Otro',
            'motivo' => 'El cliente pidió la mitad',
            'piezas' => [[
                'sku' => 'SKU-AJENA',
                'descripcion' => 'Pieza que no está',
                'cantidad' => 1,
            ]],
        ])->assertOk();

        $this->actingAs($this->usuario)->postJson(
            '/control-pedidos/tienda/tareas/'.$tarea->id.'/retiro-piezas/atender',
            ['version' => 1, 'decision' => 'aplicar']
        )->assertStatus(422);

        $pedido->refresh();
        $json = $pedido->solicitud_preparacion_json;
        $json['retiro_piezas'][0]['piezas'] = [[
            'sku' => 'SKU-REAL',
            'descripcion' => 'Crema comprometida',
            'cantidad' => 2,
        ]];
        $pedido->update(['solicitud_preparacion_json' => $json]);

        $this->actingAs($this->usuario)->postJson(
            '/control-pedidos/tienda/tareas/'.$tarea->id.'/retiro-piezas/atender',
            ['version' => 1, 'decision' => 'aplicar']
        )->assertOk();
        $this->actingAs($this->usuario)->postJson(
            '/control-pedidos/tienda/tareas/'.$tarea->id.'/retiro-piezas/atender',
            ['version' => 1, 'decision' => 'aplicar']
        )->assertStatus(422);

        $this->assertSame(2, (int) $tarea->productos()->where('sku', 'SKU-REAL')->value('cantidad_encontrada'));
        $this->assertSame('aplicada', $pedido->fresh()->solicitud_preparacion_json['retiro_piezas'][0]['estado']);
        $this->assertFalse($pedido->fresh()->solicitud_preparacion_json['revision_disponibilidad']['aceptacion_vigente']);
        $this->responderActualizacion($pedido);
        $this->assertFalse($pedido->fresh()->tieneAjusteTiendaPendiente());
    }

    public function test_el_motivo_otro_exige_texto_y_la_peticion_puede_ir_sin_evidencia(): void
    {
        $pedido = $this->pedido([
            'solicitud_preparacion_json' => [
                'version' => 1,
                'modalidad_entrega' => 'tienda',
                'area_revision' => 'preparacion_tienda',
                'revision_disponibilidad' => ['estado' => 'sin_excepciones', 'confirmada' => true],
            ],
        ]);

        $this->actingAs($this->usuario)->postJson("/control-pedidos/{$pedido->id}/retiro-piezas-tienda", [
            'entrada' => 'catalogo',
            'motivo_opcion' => 'Pieza duplicada',
            'motivo' => 'Pieza duplicada',
            'piezas' => [[
                'sku' => 'SKU-1',
                'descripcion' => 'Crema corporal',
                'cantidad' => 1,
            ]],
        ])->assertOk();
        $this->assertSame(0, $pedido->fresh()->documentos()->count());

        $this->actingAs($this->usuario)->postJson("/control-pedidos/{$pedido->id}/retiro-piezas-tienda", [
            'entrada' => 'catalogo',
            'motivo' => '',
            'piezas' => [[
                'sku' => 'SKU-2',
                'descripcion' => 'Loción',
                'cantidad' => 1,
            ]],
        ])->assertStatus(422);
    }

    public function test_las_excepciones_exigen_confirmacion_rapida_antes_de_cerrar_la_consulta(): void
    {
        Storage::fake('public');
        $this->permitirRevision();
        $tarea = $this->solicitarTienda();
        $pedido = $tarea->pedido;
        $this->actingAs($this->usuario)->postJson(
            '/control-pedidos/tienda/tareas/'.$tarea->id.'/tomar',
            ['version' => $tarea->version]
        )->assertOk();

        $this->actingAs($this->usuario)->post(
            '/control-pedidos/tienda/tareas/'.$tarea->id.'/disponibilidad',
            [
                'confirmacion_explicita' => 0,
                'excepciones' => [[
                    'descripcion' => 'Carolina 212',
                    'cantidad' => 1,
                    'condicion' => 'detalle',
                    'detalle' => 'Rayadura en el empaque',
                ]],
                'evidencias' => [UploadedFile::fake()->image('conjunto.jpg')],
            ],
            ['Accept' => 'application/json']
        )->assertOk()->assertJsonPath('estado', 'excepciones_pendientes');

        $fresco = $pedido->fresh();
        $this->assertNotNull($fresco->motivoBloqueoCierreConsultaTienda());
        $this->assertNotNull($fresco->motivoBloqueoSeparacion());
        $this->assertNull($fresco->revisionesProducto()->value('resolucion'));
        $this->assertSame(
            ResponderDisponibilidadInicialService::DECISION_EXCEPCIONES,
            app(ExpedienteRegistrarPedidos::class)->desdePedido($fresco)['decision_pendiente']
        );

        $this->actingAs($this->usuario)->postJson("/control-pedidos/{$pedido->id}/decision-tienda", [
            'revision_id' => $fresco->revisionesProducto()->value('id'),
            'accion' => 'aceptar_condicion',
        ])->assertNotFound();

        $this->postJson('/control-pedidos/'.$pedido->id.'/cerrar-consulta')->assertUnprocessable();
        $this->postJson('/control-pedidos/'.$pedido->id.'/disponibilidad-tienda', [
            'revision_id' => $fresco->revisionesProducto()->value('id'), 'accion' => 'confirmar',
            'solicitud_id' => (string) Str::uuid(),
        ])->assertOk();
        $this->postJson('/control-pedidos/'.$pedido->id.'/cerrar-consulta')->assertOk();
        $cerrado = $pedido->fresh();
        $this->assertNotNull($cerrado->consulta_cerrada_at);
        $this->assertSame('aceptar_condicion', $cerrado->revisionesProducto()->value('resolucion'));

        $this->actingAs($this->usuario)->postJson("/control-pedidos/{$pedido->id}/cobertura-tienda", [
            'cobertura' => 'apartado',
        ])->assertOk()->assertJsonPath('solicitud_preparacion.cobertura_tienda.tipo', 'apartado');
    }

    public function test_la_cobertura_y_el_cierre_esperan_una_consulta_vigente(): void
    {
        Storage::fake('public');
        $this->permitirRevision();
        $tarea = $this->solicitarTienda();
        $pedido = $tarea->pedido;
        $this->actingAs($this->usuario)->postJson(
            '/control-pedidos/tienda/tareas/'.$tarea->id.'/tomar',
            ['version' => $tarea->version]
        )->assertOk();

        $this->actingAs($this->usuario)->postJson("/control-pedidos/{$pedido->id}/cobertura-tienda", [
            'cobertura' => 'apartado',
        ])->assertStatus(422);

        $this->actingAs($this->usuario)->post(
            '/control-pedidos/tienda/tareas/'.$tarea->id.'/apartado/confirmar',
            [
                'version' => 1,
                'lineas' => [[
                    'sku' => 'SKU-1',
                    'descripcion' => 'Crema',
                    'cantidad' => 1,
                    'estado_fisico' => 'bueno',
                ]],
            ],
            ['Accept' => 'application/json']
        )->assertStatus(422);

        $this->actingAs($this->usuario)->post(
            '/control-pedidos/tienda/tareas/'.$tarea->id.'/disponibilidad',
            [
                'confirmacion_explicita' => 1,
                'evidencias' => [UploadedFile::fake()->image('conjunto.jpg')],
            ],
            ['Accept' => 'application/json']
        )->assertOk();

        $this->actingAs($this->usuario)->postJson("/control-pedidos/{$pedido->id}/cerrar-consulta")
            ->assertOk();
        $this->assertNotNull($pedido->fresh()->consulta_cerrada_at);
        $this->assertNull($pedido->fresh()->pago_validado_at);

        $this->actingAs($this->usuario)->post(
            '/control-pedidos/tienda/tareas/'.$tarea->id.'/apartado/guardar',
            [
                'version' => $tarea->fresh()->cumplimientoFisico->version,
                'evidencias' => [UploadedFile::fake()->image('avance.jpg')],
            ],
            ['Accept' => 'application/json']
        )->assertOk();
        $this->assertSame(0, $tarea->productos()->count());
        $this->assertNotSame(
            PedidoBmaCumplimientoFisico::ESTADO_APARTADO_CONFIRMADO,
            $tarea->fresh()->cumplimientoFisico->estado
        );
    }

    public function test_el_auxiliar_espera_el_cierre_y_un_reintento_no_modifica_las_piezas(): void
    {
        Storage::fake('public');
        $this->permitirRevision();
        $tarea = $this->solicitarTienda();
        $this->postJson('/control-pedidos/tienda/tareas/'.$tarea->id.'/tomar', ['version' => $tarea->version])->assertOk();
        $url = '/control-pedidos/tienda/tareas/'.$tarea->id;
        $this->post($url.'/disponibilidad', ['confirmacion_explicita' => 1, 'evidencias' => [UploadedFile::fake()->image('conjunto.jpg')]], ['Accept' => 'application/json'])->assertOk();
        $this->postJson($url.'/apartado/guardar', ['version' => 1, 'lineas' => [['sku' => 'INVENTADO', 'descripcion' => 'No solicitada', 'cantidad' => 1, 'estado_fisico' => 'bueno']]])->assertUnprocessable();
        $this->postJson($url.'/disponibilidad', ['confirmacion_explicita' => 1, 'lineas' => [['sku' => 'INVENTADO', 'descripcion' => 'No solicitada', 'cantidad' => 1, 'estado_fisico' => 'bueno']]])->assertOk()->assertJsonPath('idempotente', true);
        $this->assertSame(0, $tarea->productos()->count());
        $this->getJson($url)->assertOk()->assertJsonPath('tarea.pedido.consulta_cerrada', false);
    }

    public function test_el_auxiliar_responde_un_anexo_documental_despues_del_apartado_y_no_lo_separa_por_responder(): void
    {
        Storage::fake('public');
        $this->permitirRevision();
        $tarea = $this->solicitarTienda();
        $tarea->update(['estado' => 'RESPONDIDA', 'asignada_a_id' => $this->usuario->id]);
        $pedido = $tarea->pedido;
        $json = $pedido->solicitud_preparacion_json;
        $json['revision_disponibilidad'] = ['estado' => 'sin_excepciones'];
        $pedido->update(['solicitud_preparacion_json' => $json, 'consulta_cerrada_at' => now()]);
        $this->post('/control-pedidos/'.$pedido->id.'/anexo-tienda', ['entrada' => 'documento', 'evidencia' => UploadedFile::fake()->create('anexo.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])->assertOk();
        $this->assertNull($pedido->fresh()->consulta_cerrada_at);
        $url = '/control-pedidos/tienda/tareas/'.$tarea->id;
        $this->getJson($url)->assertOk()->assertJsonPath('tarea.pedido.anexos_pendientes.0.indice', 0)->assertJsonStructure(['tarea' => ['pedido' => ['anexos_pendientes' => [['documento' => ['url']]]]]]);
        $this->postJson($url.'/anexo/atender', ['indice' => 0, 'decision' => 'rechazar', 'nota' => 'No hay piezas de este aroma.'])->assertOk();
        $this->getJson($url)->assertOk()->assertJsonCount(0, 'tarea.pedido.anexos_pendientes');
        $anexo = $pedido->fresh()->solicitud_preparacion_json['anexo_sin_remision'][0];
        $this->assertSame('con_detalle', $anexo['estado']);
        $this->assertSame($this->usuario->id, $anexo['respondida_por_id']);
        $this->assertSame('RESPONDIDA', $tarea->fresh()->estado);
        $this->assertSame(0, $tarea->productos()->count());
        $this->postJson($url.'/anexo/atender', ['indice' => 0, 'decision' => 'rechazar', 'nota' => 'Reintento'])->assertUnprocessable();
        $this->getJson('/control-pedidos/'.$pedido->id.'/pagos')->assertOk()->assertJsonPath('consulta_cerrada', false)->assertJsonPath('solicitud_preparacion.anexo_sin_remision.0.estado', 'con_detalle');
    }

    public function test_un_auxiliar_ajeno_no_responde_el_anexo_y_un_pendiente_bloquea_cobertura(): void
    {
        Storage::fake('public');
        $this->permitirRevision();
        $tarea = $this->solicitarTienda();
        $tarea->update(['estado' => 'EN_ATENCION', 'asignada_a_id' => User::factory()->create()->id]);
        $pedido = $tarea->pedido;
        $json = $pedido->solicitud_preparacion_json;
        $json['revision_disponibilidad'] = ['estado' => 'sin_excepciones'];
        $pedido->update(['solicitud_preparacion_json' => $json]);
        $this->post('/control-pedidos/'.$pedido->id.'/anexo-tienda', ['entrada' => 'documento', 'evidencia' => UploadedFile::fake()->create('anexo.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])->assertOk();
        $this->postJson('/control-pedidos/tienda/tareas/'.$tarea->id.'/anexo/atender', ['indice' => 0, 'decision' => 'rechazar', 'nota' => 'Ajeno'])->assertUnprocessable();
        $this->postJson('/control-pedidos/'.$pedido->id.'/cerrar-consulta')->assertUnprocessable();
        $this->postJson('/control-pedidos/'.$pedido->id.'/cobertura-tienda', ['cobertura' => 'transferencia'])->assertUnprocessable();
    }

    public function test_el_anexo_disponible_se_separa_solo_despues_del_cierre_y_una_sola_vez(): void
    {
        $this->travelTo(Carbon::parse('2026-11-04 10:00:00', 'America/Mexico_City'));
        Storage::fake('public');
        $this->permitirRevision();
        $tarea = $this->solicitarTienda();
        $tarea->update(['estado' => 'RESPONDIDA', 'asignada_a_id' => $this->usuario->id]);
        $pedido = $tarea->pedido;
        $json = $pedido->solicitud_preparacion_json;
        $json['revision_disponibilidad'] = ['estado' => 'sin_excepciones'];
        $pedido->update(['solicitud_preparacion_json' => $json]);
        $producto = Producto::create(['folio' => 98731, 'sku' => 'ANEXO-REAL', 'descripcion' => 'Aroma anexo', 'activo' => true]);
        $inventario = Inventario::create(['producto_id' => $producto->id, 'almacen_id' => $tarea->almacen_id, 'existencia' => 10, 'apartado' => 0]);
        $this->post('/control-pedidos/'.$pedido->id.'/anexo-tienda', ['entrada' => 'documento', 'evidencia' => UploadedFile::fake()->create('anexo.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])->assertOk();
        $url = '/control-pedidos/tienda/tareas/'.$tarea->id.'/anexo/atender';
        $this->post($url, ['indice' => 0, 'decision' => 'disponible', 'piezas' => [['producto_id' => $producto->id, 'cantidad' => 3]], 'evidencia' => UploadedFile::fake()->image('anexo.jpg')], ['Accept' => 'application/json'])->assertOk();
        $this->assertSame(0, $tarea->productos()->count());
        $this->assertEquals(0, $inventario->fresh()->apartado);
        $this->assertNotNull($pedido->fresh()->motivoBloqueoEntregaTienda());
        $this->postJson($url, ['indice' => 0, 'decision' => 'separar'])->assertUnprocessable();
        $this->responderActualizacion($pedido);
        $this->postJson('/control-pedidos/'.$pedido->id.'/cerrar-consulta')->assertOk();
        $this->postJson($url, ['indice' => 0, 'decision' => 'separar'])->assertUnprocessable();
        $this->postJson('/control-pedidos/'.$pedido->id.'/cobertura-tienda', ['cobertura' => 'apartado'])->assertOk();
        $this->postJson('/control-pedidos/'.$pedido->id.'/confirmar-pedido-tienda')->assertOk();
        $this->postJson($url, ['indice' => 0, 'decision' => 'separar'])->assertOk();
        $this->postJson($url, ['indice' => 0, 'decision' => 'separar'])->assertOk();
        $this->assertSame(1, $tarea->productos()->count());
        $this->assertNull($pedido->fresh()->motivoBloqueoEntregaTienda());
        $this->assertEquals(3, $inventario->fresh()->apartado);
        $this->assertNotEmpty($pedido->fresh()->solicitud_preparacion_json['anexo_sin_remision'][0]['separada_at']);
        $this->getJson('/control-pedidos/tienda/tareas/'.$tarea->id)->assertOk()->assertJsonCount(0, 'tarea.pedido.anexos_por_separar');
    }

    public function test_retiro_parcial_libera_solo_la_cantidad_solicitada_y_conserva_el_resto(): void
    {
        Storage::fake('public');
        $this->permitirRevision();
        $tarea = $this->solicitarTienda();
        $tarea->update(['estado' => 'RESPONDIDA', 'asignada_a_id' => $this->usuario->id]);
        $pedido = $tarea->pedido;
        $producto = Producto::create(['folio' => 98732, 'sku' => 'RETIRO-PARCIAL', 'descripcion' => 'Aroma', 'activo' => true]);
        $inventario = Inventario::create(['producto_id' => $producto->id, 'almacen_id' => $tarea->almacen_id, 'existencia' => 10, 'apartado' => 0]);
        $tarea->productos()->create(['pedido_bma_origen_id' => $pedido->id, 'producto_id' => $producto->id, 'sku' => $producto->sku, 'descripcion_snapshot' => 'Aroma', 'cantidad_solicitada' => 3, 'cantidad_encontrada' => 3, 'estado_fisico' => 'bueno', 'orden' => 1]);
        app(CompromisoInventarioTienda::class)->comprometer($pedido, $tarea->almacen_id, $producto->id, '3', 'reserva-prueba', $producto->sku);
        $json = $pedido->fresh()->solicitud_preparacion_json;
        $json['retiro_piezas'] = [['version' => 1, 'entrada' => 'catalogo', 'estado' => 'pendiente', 'piezas' => [['sku' => $producto->sku, 'descripcion' => 'Aroma', 'cantidad' => 1]]]];
        $pedido->update(['solicitud_preparacion_json' => $json]);
        $this->postJson('/control-pedidos/tienda/tareas/'.$tarea->id.'/retiro-piezas/atender', ['version' => 1, 'decision' => 'aplicar'])->assertOk();
        $this->assertEquals(2, $inventario->fresh()->apartado);
        $this->assertEquals(2, $tarea->productos()->sum('cantidad_encontrada'));
        $this->assertEquals(2, $pedido->fresh()->solicitud_preparacion_json['compromisos_inventario']['reserva-prueba']['cantidad']);
    }

    public function test_confirmar_la_separacion_no_reabre_la_consulta_ya_cerrada(): void
    {
        $this->travelTo(Carbon::parse('2026-11-04 10:00:00', 'America/Mexico_City'));
        Storage::fake('public');
        $this->permitirRevision();
        $tarea = $this->solicitarTienda();
        $url = '/control-pedidos/tienda/tareas/'.$tarea->id;
        $this->postJson($url.'/tomar', ['version' => $tarea->version])->assertOk();
        $this->post($url.'/disponibilidad', ['confirmacion_explicita' => 1, 'evidencias' => [UploadedFile::fake()->image('conjunto.jpg')]], ['Accept' => 'application/json'])->assertOk();
        $pedido = $tarea->pedido;
        $this->postJson('/control-pedidos/'.$pedido->id.'/cerrar-consulta')->assertOk();
        $this->postJson('/control-pedidos/'.$pedido->id.'/cobertura-tienda', ['cobertura' => 'apartado'])->assertOk();
        $this->postJson('/control-pedidos/'.$pedido->id.'/confirmar-pedido-tienda')->assertOk();
        $producto = Producto::create(['folio' => 98733, 'sku' => 'SEPARAR-REAL', 'descripcion' => 'Aroma', 'activo' => true]);
        Inventario::create(['producto_id' => $producto->id, 'almacen_id' => $tarea->almacen_id, 'existencia' => 10, 'apartado' => 0]);
        $this->getJson($url)->assertOk();
        $tarea->refresh();
        $this->postJson($url.'/apartado/confirmar', ['version' => $tarea->cumplimientoFisico->version, 'lineas' => [['producto_id' => $producto->id, 'cantidad' => 1, 'estado_fisico' => 'bueno']]])->assertOk();
        $this->assertNotNull($pedido->fresh()->consulta_cerrada_at);
        $this->getJson('/control-pedidos/'.$pedido->id.'/pagos')->assertOk()->assertJsonPath('consulta_cerrada', true);
    }

    public function test_t01_confirma_el_conjunto_sin_transcribir_piezas_y_no_duplica(): void
    {
        $tarea = $this->t01Cerrado();
        $url = '/control-pedidos/tienda/tareas/'.$tarea->id;
        $version = $tarea->cumplimientoFisico->version;
        $this->postJson($url.'/apartado/confirmar', ['version' => $version])->assertOk();
        $this->postJson($url.'/apartado/confirmar', ['version' => $version])->assertOk();
        $this->assertSame('APARTADO_CONFIRMADO', $tarea->cumplimientoFisico()->first()->estado);
        $this->assertSame(0, $tarea->productos()->count());
        $this->assertSame(CatalogoEstatusPedido::FASE_PESAJE_RESPONDIDO, $tarea->pedido->fresh()->estatus->fase_ciclo);
        $this->assertNull($tarea->pedido->fresh()->cantidad_piezas);
        $this->assertSame(1, $tarea->documentos()->where('tipo_evidencia', PedidoBmaTareaDocumento::TIPO_CARATULA_RECOGE)->count());
    }

    public function test_t01_la_modalidad_inicia_vigencia_sin_reiniciarla_al_guardar(): void
    {
        ConfiguracionSistema::updateOrCreate(['clave' => \App\Services\ControlPedidos\ConfiguracionOperativaTienda::CLAVE], ['valor' => json_encode(['horarios_activos' => true]), 'tipo' => 'json']);
        $tarea = $this->t01Cerrado();
        $pedido = $tarea->pedido;
        $primera = $pedido->fresh()->solicitud_preparacion_json['cobertura_tienda'];
        $this->assertSame('17:00', Carbon::parse($primera['vence_at'])->timezone('America/Mexico_City')->format('H:i'));
        $this->travel(30)->minutes();
        $this->postJson('/control-pedidos/'.$pedido->id.'/cobertura-tienda', ['cobertura' => 'apartado'])->assertOk();
        $actual = $pedido->fresh()->solicitud_preparacion_json['cobertura_tienda'];
        $this->assertSame($primera['registrada_at'], $actual['registrada_at']);
        $this->assertSame($primera['vence_at'], $actual['vence_at']);
        $this->assertSame($primera['vence_at'], $tarea->fresh()->fecha_limite->toIso8601String());
    }

    public function test_t01_entrega_con_pago_externo_remision_y_foto_sin_exhibicion(): void
    {
        ConfiguracionSistema::updateOrCreate(['clave' => \App\Services\ControlPedidos\ConfiguracionOperativaTienda::CLAVE], ['valor' => json_encode(['seguimiento_entrega_pdv' => true]), 'tipo' => 'json']);
        $tarea = $this->t01Cerrado(true);
        CatalogoEstatusPedido::query()->firstOrCreate(['fase_ciclo' => CatalogoEstatusPedido::FASE_ENTREGADO], ['codigo_interno' => 'ENTREGADO', 'nombre_visual' => 'Entregado', 'color_hex' => '#999999', 'orden' => 20, 'activo' => true]);
        $url = '/control-pedidos/tienda/tareas/'.$tarea->id.'/apartado/entregar-pdv';
        $datos = ['version' => $tarea->cumplimientoFisico->version, 'pago_confirmado' => 1, 'numero_remision' => 'PDV-123', 'receptor' => 'Cliente'];
        $this->postJson($url, $datos)->assertUnprocessable();
        $this->post($url, [...$datos, 'foto' => UploadedFile::fake()->image('entrega.jpg')], ['Accept' => 'application/json'])->assertOk();
        $this->post($url, [...$datos, 'foto' => UploadedFile::fake()->image('reintento.jpg')], ['Accept' => 'application/json'])->assertOk();
        $this->assertSame('ENTREGADA', $tarea->cumplimientoFisico()->first()->estado);
        $pedido = $tarea->pedido->fresh();
        $this->assertSame('PDV-123', $pedido->numero_remision);
        $this->assertSame(CatalogoEstatusPedido::FASE_ENTREGADO, $pedido->estatus->fase_ciclo);
        $this->assertSame(0, $pedido->pagosExhibicion()->count());
        $this->assertSame(1, $tarea->documentos()->where('tipo_evidencia', PedidoBmaTareaDocumento::TIPO_EVIDENCIA_ENTREGA)->count());
    }

    public function test_t01_libera_impago_y_no_permite_entregar_o_liberar_pago_registrado(): void
    {
        $tarea = $this->t01Cerrado(true);
        $url = '/control-pedidos/tienda/tareas/'.$tarea->id.'/apartado/liberar-pdv';
        $datos = ['version' => $tarea->cumplimientoFisico->version, 'motivo' => 'El cliente no acudió'];
        $tarea->cumplimientoFisico()->update(['pago_confirmado_at' => now()]);
        $this->postJson($url, $datos)->assertUnprocessable();
        $tarea->cumplimientoFisico()->update(['pago_confirmado_at' => null]);
        $this->postJson($url, $datos)->assertOk();
        $this->postJson($url, $datos)->assertOk();
        $this->assertSame('DEVUELTA_ANAQUEL', $tarea->cumplimientoFisico()->first()->estado);
        $this->assertSame('apartado_liberado', $tarea->pedido->fresh()->estatus_envio);
    }

    public function test_t01_solo_el_auxiliar_asignado_entrega_o_libera(): void
    {
        $tarea = $this->t01Cerrado(true);
        $otro = User::factory()->create(['departamento_id' => $this->departamento->id]);
        $otro->givePermissionTo(Permission::findOrCreate('control_pedidos.tienda.apartado.separar', 'web'));
        $this->actingAs($otro)->postJson('/control-pedidos/tienda/tareas/'.$tarea->id.'/apartado/liberar-pdv', ['version' => $tarea->cumplimientoFisico->version, 'motivo' => 'No acudió'])->assertForbidden();
        $this->assertSame('APARTADO_CONFIRMADO', $tarea->cumplimientoFisico()->first()->estado);
    }

    public function test_t01_avisa_al_auxiliar_al_cerrar_y_a_ventas_al_liberar(): void
    {
        Notification::fake();
        $tarea = $this->t01Cerrado(true);
        Notification::assertSentTo($this->usuario, AlertaPedidoBma::class, fn ($alerta) => $alerta->tipoAlerta === 'pedido_separacion_aprobada');
        $vendedora = User::factory()->create(['departamento_id' => $this->departamento->id]);
        $tarea->pedido->update(['vendedor_id' => $vendedora->id]);
        $this->postJson('/control-pedidos/tienda/tareas/'.$tarea->id.'/apartado/liberar-pdv', ['version' => $tarea->cumplimientoFisico->version, 'motivo' => 'El cliente no acudió'])->assertOk();
        Notification::assertSentTo($vendedora, AlertaPedidoBma::class, fn ($alerta) => $alerta->tipoAlerta === 'pedido_apartado_liberado');
    }

    public function test_t01_entregar_aplica_saf_reservado_una_vez_sin_crear_cobro(): void
    {
        ConfiguracionSistema::updateOrCreate(['clave' => \App\Services\ControlPedidos\ConfiguracionOperativaTienda::CLAVE], ['valor' => json_encode(['seguimiento_entrega_pdv' => true]), 'tipo' => 'json']);
        $tarea = $this->t01Cerrado(true);
        $credito = $this->reservarSafT01($tarea->pedido);
        $url = '/control-pedidos/tienda/tareas/'.$tarea->id.'/apartado/entregar-pdv';
        $datos = ['version' => $tarea->cumplimientoFisico->version, 'pago_confirmado' => 1, 'numero_remision' => 'PDV-SAF', 'receptor' => 'Cliente'];
        $this->post($url, [...$datos, 'foto' => UploadedFile::fake()->image('entrega.jpg')], ['Accept' => 'application/json'])->assertOk();
        $this->post($url, [...$datos, 'foto' => UploadedFile::fake()->image('reintento.jpg')], ['Accept' => 'application/json'])->assertOk();
        $this->assertEquals(50, $credito->fresh()->monto_aplicado);
        $this->assertEquals(0, $credito->fresh()->monto_reservado);
        $this->assertSame('aplicado', $tarea->pedido->safAplicaciones()->first()->estado);
        $this->assertSame(0, $tarea->pedido->pagosExhibicion()->count());
    }

    public function test_t01_liberar_devuelve_saf_disponible_y_deja_el_pedido_terminal(): void
    {
        $tarea = $this->t01Cerrado(true);
        $credito = $this->reservarSafT01($tarea->pedido);
        $this->postJson('/control-pedidos/tienda/tareas/'.$tarea->id.'/apartado/liberar-pdv', ['version' => $tarea->cumplimientoFisico->version, 'motivo' => 'No acudió'])->assertOk();
        $this->assertEquals(100, $credito->fresh()->monto_disponible);
        $this->assertEquals(0, $credito->fresh()->monto_reservado);
        $this->assertSame('liberado', $tarea->pedido->safAplicaciones()->first()->estado);
        $pedido = $tarea->pedido->fresh();
        $this->assertEquals(0, $pedido->saldo_a_favor);
        $this->assertEquals(100, $pedido->total_a_cobrar);
        $this->assertFalse($pedido->esEditablePorVendedora());
    }

    public function test_bitacora_cedis_se_excluye_del_listado_y_consulta_hasta_tener_permiso(): void
    {
        $pedido = $this->consultaConExcepcion();
        $this->assertGreaterThan(0, $pedido->historial()->count());
        $listado = '/control-pedidos/cedis/listado?tab=TODOS';
        $consulta = '/control-pedidos/'.$pedido->id.'/disponibilidad-tienda';
        $this->getJson($listado)->assertOk()->assertJsonPath('pedidos.data.0.puede_ver_bitacora', false)->assertJsonMissingPath('pedidos.data.0.historial');
        $this->getJson($consulta)->assertOk()->assertJsonPath('puede_ver_bitacora', false)->assertJsonMissingPath('historial');
        $rol = \Spatie\Permission\Models\Role::findOrCreate('Gerente', 'web');
        $this->usuario->assignRole($rol);
        $this->actingAs($this->usuario->fresh());
        $this->getJson($consulta)->assertOk()->assertJsonMissingPath('historial');
        $rol->givePermissionTo(Permission::findOrCreate(\App\Support\ControlPedidos\BitacoraCedis::PERMISO, 'web'));
        $this->actingAs($this->usuario->fresh());
        $this->getJson($listado)->assertOk()->assertJsonPath('pedidos.data.0.puede_ver_bitacora', true)->assertJsonStructure(['pedidos' => ['data' => [['historial']]]]);
        $this->getJson($consulta)->assertOk()->assertJsonPath('puede_ver_bitacora', true)->assertJsonStructure(['historial']);
    }

    public function test_bitacora_cedis_oculta_relaciones_anidadas_sin_borrar_los_registros(): void
    {
        $pedido = $this->pedido();
        $tarea = new PedidoBmaTareaPreparacion;
        $tarea->setRelation('historial', collect([new \App\Models\ControlPedidos\PedidoBmaHistorialEstado]));
        $pedido->setRelation('historial', collect([new \App\Models\ControlPedidos\PedidoBmaHistorialEstado]));
        $pedido->setRelation('tareaPreparacionVigente', $tarea);
        $presentado = \App\Support\ControlPedidos\BitacoraCedis::presentar($pedido, $this->usuario)->toArray();
        $this->assertArrayNotHasKey('historial', $presentado);
        $this->assertArrayNotHasKey('historial', $presentado['tarea_preparacion_vigente']);
        $this->assertCount(1, $pedido->historial);
        $this->assertCount(1, $tarea->historial);
    }

    public function test_alertas_de_consulta_distinguen_nueva_actualizada_y_respondida(): void
    {
        $pedido = $this->pedido();
        foreach (['pedido_consulta_nueva' => 'Nueva consulta', 'pedido_consulta_actualizada' => 'Actualización de consulta', 'pedido_disponibilidad_respondida' => 'Consulta respondida'] as $tipo => $titulo) {
            $alerta = new AlertaPedidoBma($pedido, $tipo, 'Revise el pedido', ['url' => '/control-pedidos/tienda?tarea=1']);
            $this->assertStringStartsWith($titulo, $alerta->toDatabase($this->usuario)['titulo']);
            $this->assertSame('/control-pedidos/tienda?tarea=1', $alerta->toDatabase($this->usuario)['url']);
            $this->assertStringNotContainsString('tienes una notificación', $alerta->toBroadcast($this->usuario)->data['mensaje_voz']);
        }
    }

    public function test_actualizacion_cedis_responde_el_conjunto_con_nueva_captura_sin_recapturar_sku(): void
    {
        $pedido = $this->consultaConExcepcion();
        $revision = $pedido->revisionesProducto()->firstOrFail();
        $url = '/control-pedidos/'.$pedido->id.'/disponibilidad-tienda';
        $this->post($url, ['revision_id' => $revision->id, 'accion' => 'sustituir', 'entrada' => 'documento',
            'solicitud_id' => (string) Str::uuid(), 'evidencia' => UploadedFile::fake()->image('alternativa.jpg')], ['Accept' => 'application/json'])->assertOk();
        $json = $pedido->fresh()->solicitud_preparacion_json;
        $anteriores = $pedido->documentos()->pluck('id')->all();
        $datos = ['actualizacion_id' => $json['revision_disponibilidad']['actualizacion_id'],
            'cambios_revisados' => [['tipo' => 'anexo', 'indice' => 0, 'resultado' => 'correcto']]];
        $respuesta = '/control-pedidos/cedis/'.$pedido->id.'/disponibilidad';
        $this->post($respuesta, $datos, ['Accept' => 'application/json'])->assertUnprocessable();
        $datos['evidencias'] = [UploadedFile::fake()->image('nueva.jpg')];
        $invalida = $datos;
        $invalida['cambios_revisados'][0]['indice'] = 99;
        $this->post($respuesta, $invalida, ['Accept' => 'application/json'])->assertUnprocessable();
        $invalida['cambios_revisados'][0] = ['tipo' => 'anexo', 'indice' => 0, 'resultado' => 'incidencia'];
        $this->post($respuesta, $invalida, ['Accept' => 'application/json'])->assertUnprocessable();
        $this->post($respuesta, $datos, ['Accept' => 'application/json'])->assertOk();
        $pedido->refresh();
        $revisionNueva = $pedido->solicitud_preparacion_json['revision_disponibilidad'];
        $this->assertFalse($pedido->tieneAjusteTiendaPendiente());
        $this->assertCount(1, $revisionNueva['historial_respuestas']);
        $this->assertCount(1, $revisionNueva['evidencia_actual_ids']);
        $this->assertEmpty(array_intersect($anteriores, $revisionNueva['evidencia_actual_ids']));
        $this->assertSame(count($anteriores) + 1, $pedido->documentos()->count());
        $this->assertSame('revisado_conjunto', $pedido->solicitud_preparacion_json['anexo_sin_remision'][0]['estado']);
        $this->assertSame('correcto', $pedido->solicitud_preparacion_json['anexo_sin_remision'][0]['respuesta_cambio']);
        $this->assertSame(['anexo:0' => 'correcto'], $revisionNueva['cambios_revisados']);
        $this->assertSame(0, $pedido->revisionesProducto()->whereNull('resolucion')->count());
        $this->post($respuesta, $datos, ['Accept' => 'application/json'])->assertOk();
        $this->assertSame(count($anteriores) + 1, $pedido->documentos()->count());
    }

    public function test_actualizacion_tienda_admite_excepciones_sin_recapturar_el_conjunto_y_rechaza_respuesta_obsoleta(): void
    {
        Storage::fake('public');
        $this->permitirRevision();
        $tarea = $this->solicitarTienda();
        $tarea->update(['asignada_a_id' => $this->usuario->id]);
        $pedido = $tarea->pedido;
        $json = $pedido->solicitud_preparacion_json;
        $json['revision_disponibilidad'] = ['estado' => 'sin_excepciones', 'respondida_at' => now()->toIso8601String()];
        $json['anexo_sin_remision'] = [['entrada' => 'documento', 'pendiente_revision' => true]];
        ActualizacionConsulta::solicitar($json);
        $pedido->update(['solicitud_preparacion_json' => $json, 'consulta_cerrada_at' => null]);
        $original = UploadedFile::fake()->image('original-tienda.jpg');
        $pedido->documentos()->create(['tipo' => PedidoBmaDocumento::TIPO_EVIDENCIA_CONDICION,
            'ruta_archivo' => $original->store('consulta', 'public'), 'nombre_original' => 'original-tienda.jpg',
            'mime_type' => 'image/jpeg', 'activo' => true, 'relacion_tipo' => PedidoBmaDocumento::RELACION_REVISION_GENERAL, 'relacion_id' => $tarea->id]);
        $tarea->update(['estado' => 'RESPONDIDA']);
        $url = '/control-pedidos/tienda/tareas/'.$tarea->id.'/disponibilidad';
        $datos = ['confirmacion_explicita' => 0, 'actualizacion_id' => (string) Str::uuid(),
            'excepciones' => [['descripcion' => 'Pieza manual agotada', 'cantidad' => 1, 'condicion' => 'faltante']],
            'evidencias' => [UploadedFile::fake()->image('actualizada.jpg')]];
        $this->post($url, $datos, ['Accept' => 'application/json'])->assertUnprocessable();
        $datos['actualizacion_id'] = $json['revision_disponibilidad']['actualizacion_id'];
        $this->post($url, $datos, ['Accept' => 'application/json'])->assertOk();
        $this->assertTrue($pedido->fresh()->tieneSinExistenciaAbierta());
        $this->assertSame(1, $pedido->revisionesProducto()->count());
        $this->assertFalse($pedido->fresh()->tieneAjusteTiendaPendiente());
        $presentada = RespuestaPreparacionTienda::datos($tarea->fresh());
        $this->assertCount(1, array_filter($presentada['documentos'], fn ($d) => ! ($d['anterior'] ?? false)));
        $this->assertCount(1, array_filter($presentada['documentos'], fn ($d) => $d['anterior'] ?? false));
    }

    public function test_sustitucion_por_captura_se_guarda_una_vez_y_un_rechazo_no_habilita_cierre(): void
    {
        $pedido = $this->consultaConExcepcion();
        $pieza = $pedido->revisionesProducto()->firstOrFail();
        $url = '/control-pedidos/'.$pedido->id.'/disponibilidad-tienda';
        $datos = ['revision_id' => $pieza->id, 'accion' => 'sustituir', 'entrada' => 'documento',
            'solicitud_id' => (string) Str::uuid(), 'evidencia' => UploadedFile::fake()->image('alternativa.jpg')];
        $this->post($url, $datos, ['Accept' => 'application/json'])->assertOk();
        $documentos = $pedido->documentos()->count();
        $this->post($url, $datos, ['Accept' => 'application/json'])->assertOk();
        $this->assertSame($documentos, $pedido->documentos()->count());
        $this->assertCount(1, $pedido->fresh()->solicitud_preparacion_json['anexo_sin_remision']);
        $this->postJson($url.'/alternativa', ['indice' => 0, 'decision' => 'faltante', 'nota' => 'La alternativa tampoco está disponible'])->assertOk();
        $this->assertTrue($pedido->fresh()->tieneSinExistenciaAbierta());
        $this->postJson('/control-pedidos/'.$pedido->id.'/cerrar-consulta')->assertUnprocessable();
    }

    public function test_tienda_muestra_la_sustitucion_y_responde_sin_liberar_inventario_no_apartado(): void
    {
        Storage::fake('public');
        Notification::fake();
        $this->permitirRevision();
        $tarea = $this->solicitarTienda();
        $tarea->update(['estado' => 'RESPONDIDA', 'asignada_a_id' => $this->usuario->id]);
        $pedido = $tarea->pedido;
        $json = $pedido->solicitud_preparacion_json;
        $json['revision_disponibilidad'] = ['estado' => 'excepciones_pendientes'];
        $pedido->update(['solicitud_preparacion_json' => $json]);
        $pieza = $pedido->revisionesProducto()->create(['descripcion_producto' => 'Pieza con detalle sin SKU', 'cantidad_solicitada' => 1, 'estado_fisico' => 'regular', 'orden' => 1]);
        $this->actingAs($this->usuario)->post('/control-pedidos/'.$pedido->id.'/disponibilidad-tienda', [
            'revision_id' => $pieza->id, 'accion' => 'sustituir', 'entrada' => 'documento', 'solicitud_id' => (string) Str::uuid(),
            'evidencia' => UploadedFile::fake()->image('alternativa.jpg'),
        ], ['Accept' => 'application/json'])->assertOk();
        $this->getJson('/control-pedidos/tienda/tareas/'.$tarea->id)->assertOk()->assertJsonPath('tarea.pedido.acciones_disponibilidad.'.$pieza->id.'.accion', 'sustituir')->assertJsonCount(1, 'tarea.pedido.anexos_pendientes');
        $producto = Producto::create(['folio' => 98991, 'sku' => 'NUEVA-TIENDA', 'descripcion' => 'Nueva pieza en tienda', 'activo' => true]);
        $inventario = Inventario::create(['producto_id' => $producto->id, 'almacen_id' => $tarea->almacen_id, 'existencia' => 10, 'apartado' => 0]);
        $this->post('/control-pedidos/tienda/tareas/'.$tarea->id.'/anexo/atender', [
            'indice' => 0, 'decision' => 'disponible', 'piezas' => [['producto_id' => $producto->id, 'cantidad' => 1]], 'evidencia' => UploadedFile::fake()->image('respuesta-alternativa.jpg'),
        ], ['Accept' => 'application/json'])->assertOk();
        $this->assertSame('sustituir', $pieza->fresh()->resolucion);
        $this->assertSame(2, $pedido->revisionesProducto()->count());
        $this->assertTrue($pedido->fresh()->tieneAjusteTiendaPendiente());
        $this->responderActualizacion($pedido);
        $this->assertFalse($pedido->fresh()->tieneAjusteTiendaPendiente());
        $this->assertEquals(0, $inventario->fresh()->apartado);
        $this->assertNull($pedido->fresh()->consulta_cerrada_at);
    }

    private function responderActualizacion(PedidoBma $pedido): void
    {
        $pedido->refresh();
        $json = $pedido->solicitud_preparacion_json;
        $url = ($json['area_revision'] ?? '') === 'control_pedidos'
            ? '/control-pedidos/cedis/'.$pedido->id.'/disponibilidad'
            : '/control-pedidos/tienda/tareas/'.$pedido->tareasPreparacion()->latest('id')->value('id').'/disponibilidad';
        $this->post($url, ['confirmacion_explicita' => 1,
            'actualizacion_id' => $json['revision_disponibilidad']['actualizacion_id'] ?? null,
            'evidencias' => [UploadedFile::fake()->image('actualizacion-conjunto.jpg')]], ['Accept' => 'application/json'])->assertOk();
    }

    private function consultaConExcepcion(string $condicion = 'faltante'): PedidoBma
    {
        Storage::fake('public');
        Notification::fake();
        $this->estatusRespondido();
        $pedido = $this->pedido();
        $this->documento($pedido);
        $this->actingAs($this->usuario)->postJson('/control-pedidos/'.$pedido->id.'/solicitud-tienda', $this->solicitud($pedido, 'control_pedidos'))->assertOk();
        $this->post('/control-pedidos/cedis/'.$pedido->id.'/disponibilidad', [
            'confirmacion_explicita' => 0, 'excepciones' => [['descripcion' => 'Aroma de prueba', 'sku' => 'SKU-ANTERIOR', 'cantidad' => 1, 'condicion' => $condicion, 'detalle' => 'Resultado para revisar con cliente', 'evidencias' => $condicion === 'detalle' ? [UploadedFile::fake()->image('defecto.jpg')] : []]],
            'evidencias' => [UploadedFile::fake()->image('respuesta.jpg')],
        ], ['Accept' => 'application/json'])->assertOk();

        return $pedido->fresh();
    }

    public function test_accion_rapida_no_confirma_faltante_y_retiro_es_idempotente(): void
    {
        $pedido = $this->consultaConExcepcion();
        $pieza = $pedido->revisionesProducto()->firstOrFail();
        $url = '/control-pedidos/'.$pedido->id.'/disponibilidad-tienda';
        $datos = ['revision_id' => $pieza->id, 'solicitud_id' => (string) Str::uuid()];
        $this->postJson('/control-pedidos/'.$pedido->id.'/cerrar-consulta')->assertUnprocessable();
        $this->postJson($url, $datos + ['accion' => 'confirmar'])->assertUnprocessable();
        $this->postJson($url, $datos + ['accion' => 'retirar'])->assertOk();
        $historial = $pedido->historial()->count();
        $this->postJson($url, $datos + ['accion' => 'retirar'])->assertOk();
        $this->assertSame($historial, $pedido->historial()->count());
        $this->assertSame('retirar', $pieza->fresh()->resolucion);
        $this->assertFalse($pedido->fresh()->tieneSinExistenciaAbierta());
        $this->assertFalse($pedido->fresh()->puedeCerrarConsulta());
        $this->responderActualizacion($pedido);
        $this->postJson('/control-pedidos/'.$pedido->id.'/cerrar-consulta')->assertOk();
    }

    public function test_sustitucion_por_sku_espera_respuesta_cedis_y_conserva_el_cambio_al_leer(): void
    {
        $pedido = $this->consultaConExcepcion();
        $pieza = $pedido->revisionesProducto()->firstOrFail();
        $producto = Producto::create(['folio' => 98111, 'sku' => 'ALTERNATIVA', 'descripcion' => 'Aroma alternativo', 'activo' => true]);
        $this->postJson('/control-pedidos/'.$pedido->id.'/disponibilidad-tienda', ['revision_id' => $pieza->id, 'accion' => 'sustituir', 'entrada' => 'catalogo', 'producto_id' => $producto->id, 'solicitud_id' => (string) Str::uuid()])->assertOk();
        $this->assertFalse($pedido->fresh()->puedeCerrarConsulta());
        $this->getJson('/control-pedidos/'.$pedido->id.'/disponibilidad-tienda')->assertOk()->assertJsonPath('solicitud_preparacion.anexo_sin_remision.0.sku', 'ALTERNATIVA');
        $this->postJson('/control-pedidos/'.$pedido->id.'/disponibilidad-tienda/alternativa', ['indice' => 0, 'decision' => 'disponible', 'nota' => 'Pieza disponible, verificada por CEDIS'])->assertOk();
        $this->responderActualizacion($pedido);
        $this->postJson('/control-pedidos/'.$pedido->id.'/cerrar-consulta')->assertOk();
        $this->assertSame(2, $pedido->revisionesProducto()->count());
        $this->assertSame(0, ResguardoPdv::query()->count());
    }

    public function test_detalle_requiere_confirmar_estado_y_recupera_consulta_md_sin_borrar_reporte(): void
    {
        $pedido = $this->consultaConExcepcion('detalle');
        $pieza = $pedido->revisionesProducto()->firstOrFail();
        $this->assertFalse($pedido->puedeCerrarConsulta());
        $this->postJson('/control-pedidos/'.$pedido->id.'/disponibilidad-tienda', ['revision_id' => $pieza->id, 'accion' => 'confirmar', 'solicitud_id' => (string) Str::uuid()])->assertOk();
        $this->postJson('/control-pedidos/'.$pedido->id.'/cerrar-consulta')->assertOk();
        $json = $pedido->fresh()->solicitud_preparacion_json;
        $json['tienda_md_reporte'] = ['primer_pago_at' => '2026-11-05T10:00:00-06:00', 'oportuno' => true];
        $pedido->update(['solicitud_preparacion_json' => $json]);
        $pieza->update(['estado_fisico' => 'sin_existencia', 'resolucion' => null]);
        $this->postJson('/control-pedidos/'.$pedido->id.'/reabrir-consulta')->assertOk();
        $this->assertNull($pedido->fresh()->consulta_cerrada_at);
        $this->assertSame($json['tienda_md_reporte'], $pedido->fresh()->solicitud_preparacion_json['tienda_md_reporte']);
        $this->assertNotEmpty($pedido->fresh()->solicitud_preparacion_json['reapertura_disponibilidad']);
        $this->assertCount(1, $pedido->revisionesProducto()->get());
    }

    public function test_anexo_avanzado_reintentado_no_duplica_documentos_y_devuelve_solicitud_guardada(): void
    {
        $pedido = $this->consultaConExcepcion();
        $clave = (string) Str::uuid();
        $url = '/control-pedidos/'.$pedido->id.'/anexo-tienda';
        $datos = ['entrada' => 'documento', 'solicitud_id' => $clave, 'evidencia' => UploadedFile::fake()->image('anexo.jpg')];
        $this->post($url, $datos, ['Accept' => 'application/json'])->assertOk()->assertJsonCount(1, 'solicitud_preparacion.anexo_sin_remision');
        $count = $pedido->documentos()->count();
        $this->post($url, $datos, ['Accept' => 'application/json'])->assertOk();
        $this->assertSame($count, $pedido->documentos()->count());
        $this->assertCount(1, $pedido->fresh()->solicitud_preparacion_json['anexo_sin_remision']);
    }

    private function reservarSafT01(PedidoBma $pedido): SafCredito
    {
        $cuenta = SafCuenta::create(['cliente_id' => $pedido->cliente_id, 'moneda' => 'MXN']);
        $credito = SafCredito::create([
            'folio' => 'SAF-T01-'.$pedido->id, 'saf_cuenta_id' => $cuenta->id, 'cliente_id' => $pedido->cliente_id,
            'monto_original' => 100, 'monto_aplicado' => 0, 'monto_reservado' => 50, 'monto_disponible' => 50,
            'fecha_generacion' => now(), 'fecha_vencimiento' => now()->addYear(),
        ]);
        $pedido->safAplicaciones()->create(['saf_credito_id' => $credito->id, 'monto' => 50, 'estado' => 'reservado', 'reservado_por_id' => $this->usuario->id, 'reservado_at' => now()]);
        $pedido->update(['total_mercancia' => 100, 'saldo_a_favor' => 50, 'total_a_cobrar' => 50]);

        return $credito;
    }

    public function test_t01_guarda_modalidad_y_envia_confirmacion_al_auxiliar_sin_cobro_ni_duplicados(): void
    {
        Notification::fake();
        $tarea = $this->t01Cerrado(false, false);
        $pedido = $tarea->pedido;
        $cobertura = $pedido->solicitud_preparacion_json['cobertura_tienda'];
        $this->assertTrue($cobertura['requiere_confirmacion_pedido']);
        $this->assertArrayNotHasKey('confirmacion_enviada_at', $cobertura);
        $this->postJson('/control-pedidos/tienda/tareas/'.$tarea->id.'/apartado/confirmar', ['version' => $tarea->cumplimientoFisico->version])->assertUnprocessable();
        $url = '/control-pedidos/'.$pedido->id.'/confirmar-pedido-tienda';
        $this->postJson($url)->assertOk()->assertJsonPath('solicitud_preparacion.cobertura_tienda.confirmacion_enviada_por_id', $this->usuario->id);
        $this->postJson($url)->assertOk();
        Notification::assertSentTo($this->usuario, AlertaPedidoBma::class,
            fn ($alerta) => $alerta->tipoAlerta === 'pedido_confirmado_tienda');
        $confirmaciones = Notification::sent($this->usuario, AlertaPedidoBma::class)
            ->filter(fn ($alerta) => $alerta->tipoAlerta === 'pedido_confirmado_tienda');
        $this->assertCount(1, $confirmaciones);
        $this->postJson('/control-pedidos/'.$pedido->id.'/cobertura-tienda', ['cobertura' => 'apartado'])->assertOk();
        $this->assertNotEmpty($pedido->fresh()->solicitud_preparacion_json['cobertura_tienda']['confirmacion_enviada_at']);
        $this->assertSame($cobertura['vence_at'], $pedido->fresh()->solicitud_preparacion_json['cobertura_tienda']['vence_at']);
        $this->assertSame(0, $pedido->pagosExhibicion()->count());
        $this->assertNull($pedido->fresh()->pago_validado_at);
        $this->postJson('/control-pedidos/tienda/tareas/'.$tarea->id.'/apartado/confirmar', ['version' => $tarea->cumplimientoFisico->version])->assertOk();
    }

    public function test_t01_fuera_de_horario_guarda_la_modalidad_pero_no_confirma_una_vigencia_vencida(): void
    {
        ConfiguracionSistema::updateOrCreate(['clave' => \App\Services\ControlPedidos\ConfiguracionOperativaTienda::CLAVE], ['valor' => json_encode(['horarios_activos' => true]), 'tipo' => 'json']);
        $tarea = $this->t01Cerrado(false, false);
        $pedido = $tarea->pedido;
        $json = $pedido->solicitud_preparacion_json;
        unset($json['cobertura_tienda']);
        $pedido->update(['solicitud_preparacion_json' => $json]);
        $this->travelTo(Carbon::parse('2026-11-04 18:00:00', 'America/Mexico_City'));
        $this->postJson('/control-pedidos/'.$pedido->id.'/cobertura-tienda', ['cobertura' => 'apartado'])->assertOk()
            ->assertJsonPath('solicitud_preparacion.cobertura_tienda.tipo', 'apartado');
        $guardada = $pedido->fresh()->solicitud_preparacion_json['cobertura_tienda'];
        $this->assertNotEmpty($guardada['motivo_vigencia']);
        $this->assertSame('17:00', Carbon::parse($guardada['vence_at'])->format('H:i'));
        $this->postJson('/control-pedidos/'.$pedido->id.'/confirmar-pedido-tienda')->assertUnprocessable();
        $this->assertArrayNotHasKey('confirmacion_enviada_at', $pedido->fresh()->solicitud_preparacion_json['cobertura_tienda']);
    }

    public function test_horarios_desactivados_permiten_confirmar_y_separar_en_domingo_sin_borrar_el_limite_anterior(): void
    {
        Notification::fake();
        $tarea = $this->t01Cerrado(false, false);
        $pedido = $tarea->pedido;
        $json = $pedido->solicitud_preparacion_json;
        $vence = '2026-11-04T17:00:00-06:00';
        $json['cobertura_tienda']['vence_at'] = $vence;
        $json['cobertura_tienda']['motivo_vigencia'] = 'Bloqueo anterior';
        $pedido->update(['solicitud_preparacion_json' => $json]);
        $tarea->update(['fecha_limite' => Carbon::parse($vence)]);
        $this->travelTo(Carbon::parse('2026-11-08 23:00', 'America/Mexico_City'));
        $this->assertFalse($pedido->fresh()->solicitud_preparacion['horarios_operativos']['activos']);
        $this->assertNull($pedido->fresh()->solicitud_preparacion['cobertura_tienda']['motivo_vigencia']);
        $this->postJson('/control-pedidos/'.$pedido->id.'/confirmar-pedido-tienda')->assertOk();
        $this->assertSame($vence, $pedido->fresh()->solicitud_preparacion_json['cobertura_tienda']['vence_at']);
        $this->postJson('/control-pedidos/tienda/tareas/'.$tarea->id.'/apartado/confirmar', ['version' => $tarea->cumplimientoFisico->version])->assertOk();
        $this->assertSame('APARTADO_CONFIRMADO', $tarea->fresh()->cumplimientoFisico->estado);
        app(\App\Services\ControlPedidos\EvaluarVencimientoApartadoFisicoService::class)->ejecutar();
        Notification::assertNotSentTo($this->usuario, AlertaPedidoBma::class, fn ($alerta) => $alerta->tipoAlerta === 'pedido_apartado_vencido');
    }

    private function t01Cerrado(bool $separar = false, bool $confirmar = true): PedidoBmaTareaPreparacion
    {
        $this->travelTo(Carbon::parse('2026-11-04 10:00:00', 'America/Mexico_City'));
        Storage::fake('public');
        Storage::fake('local');
        CatalogoEstatusPedido::query()->firstOrCreate(['fase_ciclo' => CatalogoEstatusPedido::FASE_CANCELADO], ['codigo_interno' => 'CANCELADO', 'nombre_visual' => 'Cancelado', 'color_hex' => '#999999', 'orden' => 21, 'activo' => true]);
        CatalogoEstatusPedido::query()->firstOrCreate(['fase_ciclo' => CatalogoEstatusPedido::FASE_ENTREGADO], ['codigo_interno' => 'ENTREGADO', 'nombre_visual' => 'Entregado', 'color_hex' => '#999999', 'orden' => 20, 'activo' => true]);
        $this->permitirRevision();
        $tarea = $this->solicitarTienda();
        $url = '/control-pedidos/tienda/tareas/'.$tarea->id;
        $this->postJson($url.'/tomar', ['version' => $tarea->version])->assertOk();
        $this->post($url.'/disponibilidad', ['confirmacion_explicita' => 1, 'evidencias' => [UploadedFile::fake()->image('conjunto.jpg')]], ['Accept' => 'application/json'])->assertOk();
        $this->postJson('/control-pedidos/'.$tarea->pedido_bma_id.'/cerrar-consulta')->assertOk();
        $this->postJson('/control-pedidos/'.$tarea->pedido_bma_id.'/cobertura-tienda', ['cobertura' => 'apartado'])->assertOk();
        if ($confirmar) {
            $this->postJson('/control-pedidos/'.$tarea->pedido_bma_id.'/confirmar-pedido-tienda')->assertOk();
        }
        $this->getJson($url)->assertOk()->assertJsonPath('tarea.pedido.es_t01_documental', true);
        $tarea->refresh();
        if ($separar) {
            $this->postJson($url.'/apartado/confirmar', ['version' => $tarea->cumplimientoFisico->version])->assertOk();
        }

        return $tarea->fresh(['cumplimientoFisico', 'pedido']);
    }

    public function test_confirmar_el_conjunto_incluye_anexos_revisados_sin_confirmaciones_duplicadas(): void
    {
        $tarea = $this->t01Cerrado(false, false);
        $pedido = $tarea->pedido;
        $this->postJson('/control-pedidos/tienda/tareas/'.$tarea->id.'/apartado/separar', ['cantidad' => 1])->assertUnprocessable()->assertJsonPath('message', 'Este pedido se separa con Confirmar pedido separado, después del cierre y la confirmación de Ventas.');
        $this->assertSame('POR_SEPARAR', $tarea->fresh()->cumplimientoFisico->estado);
        $producto = Producto::create(['folio' => 99881, 'sku' => 'ANEXO-CONJUNTO', 'descripcion' => 'Anexo disponible', 'activo' => true]);
        $inventario = Inventario::create(['producto_id' => $producto->id, 'almacen_id' => $tarea->almacen_id, 'existencia' => 10, 'apartado' => 0]);
        $json = $pedido->solicitud_preparacion_json;
        $json['anexo_sin_remision'] = [
            ['entrada' => 'documento', 'descripcion' => 'Anexo aprobado', 'estado' => 'revisado_conjunto', 'respondida_at' => now()->toIso8601String()],
            ['entrada' => 'catalogo', 'estado' => 'disponible', 'respondida_at' => now()->toIso8601String(), 'piezas_respuesta' => [['producto_id' => $producto->id, 'sku' => $producto->sku, 'descripcion' => $producto->descripcion, 'cantidad' => 2]]],
            ['entrada' => 'catalogo', 'descripcion' => 'Anexo agotado', 'estado' => 'sin_existencia', 'respondida_at' => now()->toIso8601String()],
        ];
        $pedido->update(['solicitud_preparacion_json' => $json]);
        $url = '/control-pedidos/tienda/tareas/'.$tarea->id.'/apartado/confirmar';
        $datos = ['version' => $tarea->cumplimientoFisico->version, 'lineas' => []];
        $this->postJson($url, $datos)->assertUnprocessable();
        $this->assertArrayNotHasKey('separada_at', $pedido->fresh()->solicitud_preparacion_json['anexo_sin_remision'][0]);
        $this->postJson('/control-pedidos/'.$pedido->id.'/confirmar-pedido-tienda')->assertOk();
        $this->postJson($url, $datos)->assertOk();
        $this->assertSame('APARTADO_CONFIRMADO', $tarea->fresh()->cumplimientoFisico->estado);
        $anexos = $pedido->fresh()->solicitud_preparacion_json['anexo_sin_remision'];
        $this->assertNotEmpty($anexos[0]['separada_at']);
        $this->assertSame($this->usuario->id, $anexos[0]['separada_por_id']);
        $this->assertNotEmpty($anexos[1]['separada_at']);
        $this->assertEquals(2, $inventario->fresh()->apartado);
        $this->assertArrayNotHasKey('separada_at', $anexos[2]);
        $eventos = $tarea->fresh()->cumplimientoFisico->eventos()->count();
        $this->postJson($url, $datos)->assertOk();
        $this->assertSame($anexos, $pedido->fresh()->solicitud_preparacion_json['anexo_sin_remision']);
        $this->assertEquals(2, $inventario->fresh()->apartado);
        $this->assertSame($eventos, $tarea->fresh()->cumplimientoFisico->eventos()->count());
        $this->getJson('/control-pedidos/tienda/tareas/'.$tarea->id)->assertOk()
            ->assertJsonCount(0, 'tarea.pedido.anexos_por_separar')
            ->assertJsonPath('tarea.respuesta.apartado_confirmado', true);
        $this->assertNull($pedido->fresh()->motivoBloqueoEntregaTienda());
    }

    public function test_tienda_reporta_error_en_json_mientras_espera_a_ventas(): void
    {
        $tarea = $this->t01Cerrado(false, false);
        $this->usuario->givePermissionTo(Permission::findOrCreate('control_pedidos.tienda.reportar_error', 'web'));
        $this->postJson('/control-pedidos/tienda/tareas/'.$tarea->id.'/reportar-incidencia', [
            'version' => $tarea->version, 'tipo_incidencia' => 'datos_incorrectos',
            'motivo' => 'Revisar los datos de la solicitud', 'almacen_solicitado_id' => $tarea->almacen_id,
        ])->assertOk()->assertJsonPath('message', 'Error reportado. Ventas fue notificada.');
        $this->assertSame('CON_INCIDENCIA', $tarea->fresh()->estado);
        $this->assertSame('POR_SEPARAR', $tarea->fresh()->cumplimientoFisico->estado);
    }

    public function test_tienda_recibe_unidades_con_atributos_sin_observacion_como_cedis(): void
    {
        Storage::fake('public');
        $this->permitirRevision();
        $tarea = $this->solicitarTienda();
        $this->postJson('/control-pedidos/tienda/tareas/'.$tarea->id.'/tomar', ['version' => $tarea->version])->assertOk();
        $url = '/control-pedidos/tienda/tareas/'.$tarea->id.'/disponibilidad';
        $this->post($url, ['confirmacion_explicita' => 0, 'excepciones' => [
            ['descripcion' => 'Unidad de ejemplo', 'sku' => 'MISMO-SKU', 'cantidad' => 1, 'condicion' => 'detalle', 'unica_pieza' => 1, 'evidencias' => [UploadedFile::fake()->image('unidad.jpg')]],
            ['descripcion' => 'Unidad de ejemplo', 'sku' => 'MISMO-SKU', 'cantidad' => 1, 'condicion' => 'faltante'],
        ], 'evidencias' => [UploadedFile::fake()->image('conjunto.jpg')]], ['Accept' => 'application/json'])->assertOk();
        $this->assertSame(2, $tarea->pedido->revisionesProducto()->count());
        $this->assertSame('POR_SEPARAR', $tarea->fresh()->cumplimientoFisico->estado);
        $this->getJson('/control-pedidos/tienda/tareas/'.$tarea->id)->assertOk()
            ->assertJsonCount(2, 'tarea.respuesta.excepciones')
            ->assertJsonPath('tarea.respuesta.excepciones.0.unica_pieza', true)
            ->assertJsonCount(1, 'tarea.respuesta.excepciones.0.documentos')
            ->assertJsonPath('tarea.respuesta.excepciones.1.estado_fisico', 'sin_existencia');
    }

    public function test_el_paso_posterior_apagado_no_bloquea_confirmar_apartado_y_rechaza_nuevas_entregas(): void
    {
        $tarea = $this->t01Cerrado(true);
        $this->getJson('/control-pedidos/tienda/tareas/'.$tarea->id)->assertOk()
            ->assertJsonPath('tarea.pedido.seguimiento_entrega_pdv_habilitado', false)
            ->assertJsonPath('tarea.respuesta.seguimiento_entrega_pdv_habilitado', false)
            ->assertJsonPath('tarea.respuesta.apartado_confirmado', true);
        $this->post('/control-pedidos/tienda/tareas/'.$tarea->id.'/apartado/entregar-pdv', [
            'version' => $tarea->cumplimientoFisico->version, 'pago_confirmado' => 1, 'numero_remision' => 'PDV-OPCIONAL',
            'receptor' => 'Cliente ficticio', 'foto' => UploadedFile::fake()->image('foto.jpg'),
        ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('operacion');
        $this->assertSame('APARTADO_CONFIRMADO', $tarea->fresh()->cumplimientoFisico->estado);
        $this->assertNull($tarea->fresh()->cumplimientoFisico->pago_confirmado_at);
        $this->assertNull($tarea->pedido->fresh()->numero_remision);
        $this->assertSame(0, $tarea->documentos()->where('tipo_evidencia', PedidoBmaTareaDocumento::TIPO_EVIDENCIA_ENTREGA)->count());
    }

    private function permitirRevision(): void
    {
        foreach ([
            'control_pedidos.tienda.tomar',
            'control_pedidos.tienda.apartado.separar',
        ] as $permiso) {
            $this->usuario->givePermissionTo(Permission::findOrCreate($permiso, 'web'));
        }
    }

    private function pedidoConModalidadConfirmada(): PedidoBma
    {
        return $this->pedido([
            'catalogo_estatus_pedido_id' => CatalogoEstatusPedido::porFase(CatalogoEstatusPedido::FASE_PESAJE_RESPONDIDO)->id,
            'consulta_cerrada_at' => now(),
            'solicitud_preparacion_json' => [
                'modalidad_entrega' => 'tienda', 'area_revision' => 'preparacion_tienda',
                'caso_tienda' => 'revisar_apartar', 'codigo_modalidad' => 'RECOGE_TIENDA', 'atencion_tienda' => 'no',
                'revision_disponibilidad' => ['estado' => 'sin_excepciones'],
                'cobertura_tienda' => ['tipo' => 'apartado', 'registrada_at' => now()->toIso8601String(), 'registrada_por_id' => $this->usuario->id, 'confirmacion_enviada_at' => now()->toIso8601String(), 'vence_at' => now()->addHour()->toIso8601String()],
            ],
        ]);
    }

    private function solicitarCambioCobertura(PedidoBma $pedido, string $cobertura = 'credito'): string
    {
        $id = (string) Str::uuid();
        $version = $this->actingAs($this->usuario)->getJson("/control-pedidos/{$pedido->id}/cambio-cobertura-tienda")->assertOk()->json('version');
        $this->postJson("/control-pedidos/{$pedido->id}/cambio-cobertura-tienda", ['accion' => 'solicitar', 'solicitud_id' => $id, 'version' => $version, 'cobertura' => $cobertura, 'motivo' => 'Corrección solicitada por el cliente'])->assertOk()->assertJsonPath('estado_cobertura.solicitud.estado', 'pendiente');
        return $id;
    }

    private function autorizadorCobertura(): User
    {
        $actor = User::factory()->create(['departamento_id' => $this->departamento->id]);
        $actor->assignRole(\Spatie\Permission\Models\Role::findOrCreate('Gerente', 'web'));
        $actor->givePermissionTo(Permission::findOrCreate(\App\Support\ControlPedidos\CambioCoberturaTienda::PERMISO, 'web'));
        return $actor;
    }

    public function test_cobertura_guardada_no_se_sustituye_y_reintento_conserva_confirmacion_y_vigencia(): void
    {
        $pedido = $this->pedidoConModalidadConfirmada();
        $antes = $pedido->solicitud_preparacion_json;
        $url = "/control-pedidos/{$pedido->id}/cobertura-tienda";
        $this->actingAs($this->usuario)->postJson($url, ['cobertura' => 'transferencia'])->assertUnprocessable();
        $this->postJson($url, ['cobertura' => 'apartado'])->assertOk();
        $this->assertSame($antes, $pedido->fresh()->solicitud_preparacion_json);
        $this->assertSame(0, $pedido->historial()->count());
        $this->postJson($url, ['cobertura' => 'apartado', 'mismo_dia' => true])->assertUnprocessable();
    }

    public function test_cambio_cobertura_exige_otro_autorizador_y_aplica_una_sola_vez(): void
    {
        Notification::fake();
        $pedido = $this->pedidoConModalidadConfirmada();
        $anterior = $pedido->solicitud_preparacion_json['cobertura_tienda'];
        $id = $this->solicitarCambioCobertura($pedido);
        $url = "/control-pedidos/{$pedido->id}/cambio-cobertura-tienda";
        $this->assertSame('apartado', $pedido->fresh()->solicitud_preparacion_json['cobertura_tienda']['tipo']);
        $decision = ['accion' => 'autorizar', 'solicitud_id' => $id, 'motivo' => 'Revisado sin movimientos ni pagos'];
        $this->postJson($url, $decision)->assertForbidden();
        $this->usuario->givePermissionTo(Permission::findOrCreate(\App\Support\ControlPedidos\CambioCoberturaTienda::PERMISO, 'web'));
        $this->postJson($url, $decision)->assertUnprocessable();
        $gerente = $this->autorizadorCobertura();
        $this->actingAs($gerente)->postJson($url, $decision)->assertOk()->assertJsonPath('solicitud_preparacion.cobertura_tienda.tipo', 'credito')->assertJsonPath('estado_cobertura.solicitud.estado', 'aplicada');
        $this->postJson($url, $decision)->assertOk();
        $json = $pedido->fresh()->solicitud_preparacion_json;
        $this->assertCount(1, $json['historial_cambios_cobertura']);
        $this->assertSame($anterior, $json['historial_cambios_cobertura'][0]['anterior']);
        $this->assertSame($gerente->id, $json['cambio_cobertura_tienda']['resuelta_por_id']);
        $this->assertArrayNotHasKey('confirmacion_enviada_at', $json['cobertura_tienda']);
        $this->assertSame(2, $pedido->historial()->count());
    }

    public function test_cambio_cobertura_obsoleto_no_se_aplica_y_el_rechazo_conserva_modalidad(): void
    {
        Notification::fake();
        $pedido = $this->pedidoConModalidadConfirmada();
        $id = $this->solicitarCambioCobertura($pedido);
        $pedido->update(['total_mercancia' => 120]);
        $url = "/control-pedidos/{$pedido->id}/cambio-cobertura-tienda";
        $gerente = $this->autorizadorCobertura();
        $this->actingAs($gerente)->postJson($url, ['accion' => 'autorizar', 'solicitud_id' => $id, 'motivo' => 'Se revisó la solicitud'])->assertUnprocessable();
        $this->postJson($url, ['accion' => 'rechazar', 'solicitud_id' => $id, 'motivo' => 'Se requiere una solicitud actualizada'])->assertOk();
        $this->assertSame('apartado', $pedido->fresh()->solicitud_preparacion_json['cobertura_tienda']['tipo']);
        $this->assertSame('rechazada', $pedido->fresh()->solicitud_preparacion_json['cambio_cobertura_tienda']['estado']);
    }

    public function test_cambio_cobertura_no_borra_remision_ni_apartado_fisico(): void
    {
        Notification::fake();
        $pedido = $this->pedidoConModalidadConfirmada();
        $pedido->update(['numero_remision' => 'REM-PROTEGIDA']);
        $id = $this->solicitarCambioCobertura($pedido);
        $gerente = $this->autorizadorCobertura();
        $this->actingAs($gerente)->postJson("/control-pedidos/{$pedido->id}/cambio-cobertura-tienda", ['accion' => 'autorizar', 'solicitud_id' => $id, 'motivo' => 'Solicitan otra forma de pago'])->assertUnprocessable();
        $this->assertSame('REM-PROTEGIDA', $pedido->fresh()->numero_remision);
        $this->assertSame('apartado', $pedido->fresh()->solicitud_preparacion_json['cobertura_tienda']['tipo']);
        $tarea = $this->t01Cerrado();
        $this->postJson('/control-pedidos/tienda/tareas/'.$tarea->id.'/apartado/confirmar', ['version' => $tarea->cumplimientoFisico->version])->assertOk();
        $pedido = $tarea->pedido->fresh();
        $id = $this->solicitarCambioCobertura($pedido);
        $this->actingAs($gerente)->postJson("/control-pedidos/{$pedido->id}/cambio-cobertura-tienda", ['accion' => 'autorizar', 'solicitud_id' => $id, 'motivo' => 'Revisar cambio después del apartado'])->assertUnprocessable();
        $this->assertSame('APARTADO_CONFIRMADO', $tarea->cumplimientoFisico()->first()->estado);
    }

    public function test_cambio_cobertura_conserva_metadatos_al_autoguardar_y_no_admite_solicitudes_simultaneas(): void
    {
        Notification::fake();
        $pedido = $this->pedidoConModalidadConfirmada();
        $id = $this->solicitarCambioCobertura($pedido);
        $json = $pedido->fresh()->solicitud_preparacion_json;
        $version = \App\Support\ControlPedidos\CambioCoberturaTienda::version($pedido->fresh());
        $datos = ['accion' => 'solicitar', 'solicitud_id' => $id, 'version' => $version, 'cobertura' => 'credito', 'motivo' => 'Corrección solicitada por el cliente'];
        $url = "/control-pedidos/{$pedido->id}/cambio-cobertura-tienda";
        $this->postJson($url, $datos)->assertOk();
        $datos['solicitud_id'] = (string) Str::uuid();
        $this->postJson($url, $datos)->assertUnprocessable();
        $fusion = app(\App\Services\ControlPedidos\FusionarSolicitudPreparacionPedido::class);
        $alterada = $fusion->ejecutar($json, ['atencion_tienda' => 'prevista', 'codigo_modalidad' => 'RECOGE_TIENDA_CREDITO', 'cobertura_tienda' => ['tipo' => 'credito'], 'cambio_cobertura_tienda' => ['estado' => 'aplicada']]);
        $this->assertSame($json['cambio_cobertura_tienda'], $alterada['cambio_cobertura_tienda']);
        $this->assertSame($json['cobertura_tienda'], $alterada['cobertura_tienda']);
        $this->assertNotSame('prevista', $alterada['atencion_tienda']);
        $this->assertSame('RECOGE_TIENDA', $alterada['codigo_modalidad']);
        $this->assertSame($json['cobertura_tienda'], $fusion->ejecutar($json, null)['cobertura_tienda']);
    }

    public function test_cambio_cobertura_no_convierte_pagos_existentes_y_conserva_reporte_md(): void
    {
        Notification::fake();
        $pedido = $this->pedidoConModalidadConfirmada();
        $json = $pedido->solicitud_preparacion_json;
        $json['cobertura_tienda']['tipo'] = 'transferencia';
        $json['codigo_modalidad'] = 'RECOGE_TIENDA_TRANSFERENCIA';
        $json['atencion_tienda'] = 'prevista';
        $json['tienda_md_reporte'] = ['primer_pago_at' => now()->toIso8601String(), 'estado' => 'oportuno'];
        $pedido->update(['solicitud_preparacion_json' => $json]);
        $pago = $pedido->pagosExhibicion()->create(['monto' => 100, 'forma_pago' => 'transferencia', 'fecha_pago' => now(), 'registrado_por_id' => $this->usuario->id, 'activo_para_cobertura' => true, 'estado_revision' => 'pendiente', 'ruta_archivo' => 'comprobante-conservado.jpg']);
        $this->actingAs($this->usuario)->postJson("/control-pedidos/{$pedido->id}/cobertura-tienda", ['cobertura' => 'transferencia', 'mismo_dia' => false])->assertUnprocessable();
        $id = $this->solicitarCambioCobertura($pedido);
        $this->actingAs($this->autorizadorCobertura())->postJson("/control-pedidos/{$pedido->id}/cambio-cobertura-tienda", ['accion' => 'autorizar', 'solicitud_id' => $id, 'motivo' => 'El cliente solicita crédito'])->assertUnprocessable();
        $this->assertSame('transferencia', $pedido->fresh()->solicitud_preparacion_json['cobertura_tienda']['tipo']);
        $this->assertSame($json['tienda_md_reporte'], $pedido->fresh()->solicitud_preparacion_json['tienda_md_reporte']);
        $this->assertSame('comprobante-conservado.jpg', $pago->fresh()->ruta_archivo);
        $this->assertSame(1, $pedido->pagosExhibicion()->count());
    }

    public function test_cambio_cobertura_de_regreso_al_apartado_conserva_el_primer_limite(): void
    {
        Notification::fake();
        $pedido = $this->pedidoConModalidadConfirmada();
        $limite = $pedido->solicitud_preparacion_json['cobertura_tienda']['vence_at'];
        $gerente = $this->autorizadorCobertura();
        foreach (['credito', 'apartado'] as $tipo) {
            $id = $this->solicitarCambioCobertura($pedido->fresh(), $tipo);
            $this->actingAs($gerente)->postJson("/control-pedidos/{$pedido->id}/cambio-cobertura-tienda", ['accion' => 'autorizar', 'solicitud_id' => $id, 'motivo' => 'Corregir modalidad sin alterar documentos'])->assertOk();
        }
        $json = $pedido->fresh()->solicitud_preparacion_json;
        $this->assertCount(2, $json['historial_cambios_cobertura']);
        $this->assertSame($limite, $json['cobertura_tienda']['vence_at']);
        $this->assertArrayNotHasKey('confirmacion_enviada_at', $json['cobertura_tienda']);
    }

    public function test_cambio_cobertura_sin_compromiso_actualiza_la_instruccion_real_y_conserva_consulta(): void
    {
        Notification::fake();
        $tarea = $this->t01Cerrado();
        $pedido = $tarea->pedido->fresh();
        $consulta = $pedido->consulta_cerrada_at->toIso8601String();
        $versionTarea = $tarea->version;
        $versionFisica = $tarea->cumplimientoFisico->version;
        $modalidad = CatalogoModalidadPreparacionPedido::query()->firstOrCreate(['codigo' => 'RECOGE_TIENDA_CREDITO'], ['nombre' => 'Recoge con crédito', 'activo' => true]);
        $id = $this->solicitarCambioCobertura($pedido);
        $this->actingAs($this->autorizadorCobertura())->postJson("/control-pedidos/{$pedido->id}/cambio-cobertura-tienda", ['accion' => 'autorizar', 'solicitud_id' => $id, 'motivo' => 'Corregir antes de separar físicamente'])->assertOk();
        $this->assertSame($modalidad->id, $tarea->fresh()->catalogo_modalidad_preparacion_id);
        $this->assertSame($versionTarea + 1, $tarea->fresh()->version);
        $this->assertSame($versionFisica + 1, $tarea->cumplimientoFisico()->first()->version);
        $this->assertNull($tarea->fresh()->fecha_limite);
        $this->assertNull($tarea->cumplimientoFisico()->first()->vence_at);
        $this->assertSame('POR_SEPARAR', $tarea->cumplimientoFisico()->first()->estado);
        $this->assertSame($consulta, $pedido->fresh()->consulta_cerrada_at->toIso8601String());
    }

    public function test_primera_cobertura_admite_saf_sin_sobrescribir_modalidad_ya_guardada(): void
    {
        $pedido = $this->pedidoConModalidadConfirmada();
        $json = $pedido->solicitud_preparacion_json;
        unset($json['cobertura_tienda'], $json['codigo_modalidad']);
        $pedido->update(['solicitud_preparacion_json' => $json, 'saldo_a_favor' => 20]);
        $this->actingAs($this->usuario)->postJson("/control-pedidos/{$pedido->id}/cobertura-tienda", ['cobertura' => 'transferencia'])->assertOk();
        $this->assertEquals(20, $pedido->fresh()->saldo_a_favor);
        $this->assertSame('transferencia', $pedido->fresh()->solicitud_preparacion_json['cobertura_tienda']['tipo']);
    }

    public function test_el_cierre_y_la_modalidad_entregan_la_base_vigente_para_autoguardar_importes(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $this->permitirRevision();
        $this->actingAs($this->usuario);
        foreach (['apartado', 'credito', 'transferencia'] as $cobertura) {
            $pedido = $this->pedido();
            $this->documento($pedido);
            $this->postJson("/control-pedidos/{$pedido->id}/solicitud-tienda", $this->solicitud($pedido, 'preparacion_tienda', $this->pdv->id))->assertOk();
            $tarea = $pedido->tareasPreparacion()->sole();
            $url = '/control-pedidos/tienda/tareas/'.$tarea->id;
            $this->postJson($url.'/tomar', ['version' => $tarea->version])->assertOk();
            $this->post($url.'/disponibilidad', [
                'confirmacion_explicita' => 1, 'evidencias' => [UploadedFile::fake()->image('conjunto.jpg')],
            ], ['Accept' => 'application/json'])->assertOk();
            $baseInicial = RevisionCapturaPedido::base($pedido->fresh());

            $cierre = $this->postJson("/control-pedidos/{$pedido->id}/cerrar-consulta")->assertOk();
            $baseCierre = $cierre->json('base_captura');
            $this->assertSame(RevisionCapturaPedido::base($pedido->fresh()), $baseCierre);
            $this->assertNotSame($baseInicial, $baseCierre);

            $modalidad = $this->postJson("/control-pedidos/{$pedido->id}/cobertura-tienda", ['cobertura' => $cobertura])
                ->assertOk()->assertJsonPath('solicitud_preparacion.cobertura_tienda.tipo', $cobertura);
            $baseModalidad = $modalidad->json('base_captura');
            $this->assertNotSame($baseCierre, $baseModalidad);
            $this->assertSame($cierre->json('revision_registro'), $modalidad->json('revision_registro'));
            $datos = [
                'pedido_id' => $pedido->id, 'total_mercancia' => 200,
                'revision_esperada' => $modalidad->json('revision_registro'),
            ];
            $this->postJson('/control-pedidos/autoguardar', $datos + ['base_captura' => $baseCierre])
                ->assertConflict()->assertJsonPath('codigo', 'conflicto_captura');
            $this->postJson('/control-pedidos/autoguardar', $datos + ['base_captura' => $baseModalidad])->assertOk();
            $this->assertEquals(200, $pedido->fresh()->total_mercancia);
        }
    }

    private function estatusRespondido(): void
    {
        CatalogoEstatusPedido::query()->firstOrCreate(
            ['fase_ciclo' => CatalogoEstatusPedido::FASE_PESAJE_RESPONDIDO],
            ['codigo_interno' => 'PESAJE_OK', 'nombre_visual' => 'Consulta respondida', 'color_hex' => '#999999', 'orden' => 2, 'activo' => true]
        );
    }

    private function solicitarTienda(): PedidoBmaTareaPreparacion
    {
        $pedido = $this->pedido();
        $this->documento($pedido);
        $this->actingAs($this->usuario)->postJson(
            "/control-pedidos/{$pedido->id}/solicitud-tienda",
            $this->solicitud($pedido, 'preparacion_tienda', $this->pdv->id)
        )->assertOk();

        return PedidoBmaTareaPreparacion::query()->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function pedido(array $extra = []): PedidoBma
    {
        $listaId = DB::table('catalogo_listas_descuento')->insertGetId([
            'nombre' => 'Lista 211 '.uniqid(),
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $cliente = Cliente::query()->create([
            'numero_cliente' => (string) random_int(10000, 99999),
            'nombre' => 'Cliente ficticio',
            'lista_actual_id' => $listaId,
            'vendedor_id' => $this->usuario->id,
        ]);
        $origen = CatalogoOrigenPedido::query()->create([
            'codigo' => 'ORI-'.uniqid(),
            'nombre' => 'Mostrador '.uniqid(),
            'requiere_logistica' => true,
            'activo' => true,
        ]);
        $salida = Almacen::query()->create([
            'codigo' => 'CED-'.uniqid(),
            'nombre' => 'Salida general',
            'activo' => true,
            'visible_en_pedidos' => true,
            'permite_salida_pedidos' => true,
        ]);

        return PedidoBma::query()->create(array_merge([
            'folio' => 'INT-'.uniqid(),
            'folio_remision' => 'FOL-211',
            'fecha' => now()->toDateString(),
            'vendedor_id' => $this->usuario->id,
            'cliente_id' => $cliente->id,
            'departamento_operacion_id' => $this->departamento->id,
            'origen_id' => $origen->id,
            'almacen_id' => $salida->id,
            'sucursal_destino_id' => Sucursal::factory()->create(['activo' => true])->id,
            'catalogo_estatus_pedido_id' => CatalogoEstatusPedido::porFase(CatalogoEstatusPedido::FASE_BORRADOR)->id,
            'total_mercancia' => 100,
            'costo_envio' => 0,
            'saldo_a_favor' => 0,
            'costo_seguro' => 0,
            'total_a_cobrar' => 100,
            'aplica_seguro' => false,
            'es_resguardo' => false,
            'prioridad_md' => false,
            'solicitud_preparacion_json' => ['version' => 1, 'modalidad_entrega' => 'tienda'],
        ], $extra));
    }

    private function documento(PedidoBma $pedido): void
    {
        PedidoBmaDocumento::query()->create([
            'pedido_bma_id' => $pedido->id,
            'tipo' => PedidoBmaDocumento::TIPO_PDF_PEDIDO,
            'ruta_archivo' => 'pedidos_bma/pedido-ficticio.pdf',
            'nombre_original' => 'pedido-ficticio.pdf',
            'mime_type' => 'application/pdf',
            'tamano_bytes' => 1200,
            'orden' => 1,
            'activo' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function captura(PedidoBma $pedido, array $extra = []): array
    {
        $pedido = $pedido->fresh();

        return array_merge([
            'pedido_id' => $pedido->id,
            'revision_esperada' => (int) $pedido->revision_registro,
            'base_captura' => RevisionCapturaPedido::base($pedido),
        ], $extra);
    }

    /**
     * @return array<string, mixed>
     */
    private function solicitud(PedidoBma $pedido, string $area, ?int $almacenId = null): array
    {
        $pedido = $pedido->fresh();

        return [
            'area_revision' => $area,
            'almacen_id' => $almacenId,
            'revision_esperada' => (int) $pedido->revision_registro,
            'base_captura' => RevisionCapturaPedido::base($pedido),
        ];
    }
}
