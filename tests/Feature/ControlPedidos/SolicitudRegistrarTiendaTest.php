<?php

namespace Tests\Feature\ControlPedidos;

use App\Models\Almacen;
use App\Models\CatalogoTipoAlmacen;
use App\Models\Cliente;
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
use App\Models\Sucursal;
use App\Models\User;
use App\Services\ControlPedidos\ResponderDisponibilidadInicialService;
use App\Support\ControlPedidos\RegistrarPedidos\ExpedienteRegistrarPedidos;
use App\Support\ControlPedidos\RespuestaPreparacionTienda;
use App\Support\ControlPedidos\RevisionCapturaPedido;
use Illuminate\Http\UploadedFile;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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

    public function test_mismo_dia_no_se_envia_como_apartado_ni_consulta(): void
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
        $this->assertTrue($pedido->fresh()->solicitud_preparacion_json['revision_disponibilidad']['aceptacion_vigente']);
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

    private function permitirRevision(): void
    {
        foreach ([
            'control_pedidos.tienda.tomar',
            'control_pedidos.tienda.apartado.separar',
        ] as $permiso) {
            $this->usuario->givePermissionTo(Permission::findOrCreate($permiso, 'web'));
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
