<?php

namespace Tests\Feature\ControlPedidos;

use App\Exceptions\ControlPedidos\ConflictoCapturaPedido;
use App\Models\Almacen;
use App\Models\CatalogoTipoAlmacen;
use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\CatalogoOrigenPedido;
use App\Models\ControlPedidos\CatalogoPaqueteriaPedido;
use App\Models\ControlPedidos\CatalogoTipoCajaPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\User;
use App\Services\ControlPedidos\CerrarConsultaPedidoBmaService;
use App\Services\ControlPedidos\Nacional\RegistrarAjusteConsultaNacional;
use App\Services\ControlPedidos\NotificarPedidoBmaService;
use App\Services\ControlPedidos\ResponderPesajePedidoBmaService;
use App\Services\ControlPedidos\SolicitarPesajePedidoBmaService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class NacionalAltaConsultaTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->withoutMiddleware([ValidateCsrfToken::class, PreventRequestForgery::class]);
        $this->mock(NotificarPedidoBmaService::class)->shouldReceive('ejecutar')->zeroOrMoreTimes();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->usuario = User::factory()->create();
        $this->usuario->givePermissionTo(Permission::findOrCreate('control_pedidos.crear', 'web'));
        foreach ([
            CatalogoEstatusPedido::FASE_BORRADOR => ['BORRADOR_NAC', 'Borrador', 1],
            CatalogoEstatusPedido::FASE_PESAJE_PENDIENTE => ['PESAJE_NAC', 'Pesaje', 2],
            CatalogoEstatusPedido::FASE_PESAJE_RESPONDIDO => ['PESAJE_OK_NAC', 'Pesaje listo', 3],
        ] as $fase => [$codigo, $nombre, $orden]) {
            CatalogoEstatusPedido::query()->firstOrCreate(
                ['fase_ciclo' => $fase],
                ['codigo_interno' => $codigo, 'nombre_visual' => $nombre, 'color_hex' => '#999999', 'orden' => $orden, 'activo' => true]
            );
        }
    }

    public function test_alta_consulta_ajuste_y_cierre_conservan_el_mismo_pedido(): void
    {
        Storage::fake('public');
        $pedido = $this->pedidoNacional();
        $this->assertFalse($pedido->consultaCedisModoRevisionMercancia());
        $this->assertNull($pedido->sucursal_destino_id);

        $consultado = app(SolicitarPesajePedidoBmaService::class)->ejecutar($pedido, $this->usuario->id);
        $this->assertSame($pedido->id, $consultado->id);
        $this->assertSame(PedidoBma::ESTATUS_ENVIO_PENDIENTE_PESAJE, $consultado->estatus_envio);
        $this->assertNull($consultado->consulta_cerrada_at);
        $this->assertNotSame('control_pedidos', $consultado->solicitud_preparacion_json['area_revision'] ?? null);
        app(SolicitarPesajePedidoBmaService::class)->ejecutar($consultado->fresh(), $this->usuario->id);
        $this->assertSame(1, PedidoBma::query()->count());

        $tipo = CatalogoTipoCajaPedido::query()->create(['nombre' => 'Caja nacional', 'activo' => true]);
        $uuid = (string) Str::uuid();
        $respondido = app(ResponderPesajePedidoBmaService::class)->ejecutar($consultado->fresh(), $this->usuario->id, [[
            'catalogo_tipo_caja_id' => $tipo->id,
            'largo' => 20,
            'ancho' => 15,
            'alto' => 10,
            'peso_real_kg' => 1.25,
            'peso_volumetrico_kg' => 0.6,
            'uuid_operativo' => $uuid,
            'client_uuid' => $uuid,
        ]], [
            'evidencias_envios' => [[UploadedFile::fake()->image('caja.jpg')]],
            'revisiones' => [[
                'descripcion_producto' => 'Aroma de prueba',
                'sku' => 'SKU-NAC',
                'cantidad_solicitada' => 1,
                'cantidad_encontrada' => 1,
                'estado_fisico' => 'bueno',
                'client_uuid' => (string) Str::uuid(),
            ]],
        ]);

        $this->assertSame($pedido->id, $respondido->id);
        $this->assertNull($respondido->consulta_cerrada_at);
        $this->assertTrue($respondido->puedeCerrarConsulta());
        $this->assertSame(1, $respondido->revisionesProducto()->count());
        $this->assertSame('SKU-NAC', $respondido->revisionesProducto()->first()->sku);
        $this->assertSame(0, PedidoBmaTareaPreparacion::query()->count());

        $base = ['revision_esperada' => (int) $respondido->revision_registro, 'base_captura' => $respondido->base_captura];
        $solicitud = (string) Str::uuid();
        $ajustado = app(RegistrarAjusteConsultaNacional::class)->ejecutar($respondido->fresh(), $this->usuario, [
            'tipo' => 'anexo',
            'solicitud_id' => $solicitud,
            'piezas' => [['sku' => 'SKU-2', 'descripcion' => 'Pieza anexa', 'cantidad' => 1]],
            ...$base,
        ], [UploadedFile::fake()->create('anexo.pdf', 20, 'application/pdf')]);

        $this->assertFalse($ajustado['idempotente']);
        $this->assertSame($pedido->id, $ajustado['pedido']->id);
        $this->assertTrue((bool) $ajustado['pedido']->consulta_actualizacion_pendiente);
        $this->assertFalse($ajustado['pedido']->puedeCerrarConsulta());
        $this->assertSame('nacional', $ajustado['pedido']->solicitud_preparacion_json['modalidad_entrega']);
        $this->assertSame(1, PedidoBma::query()->count());
        $this->assertSame(0, PedidoBmaTareaPreparacion::query()->count());

        $reintento = app(RegistrarAjusteConsultaNacional::class)->ejecutar($ajustado['pedido']->fresh(), $this->usuario, [
            'tipo' => 'anexo',
            'solicitud_id' => $solicitud,
            'revision_esperada' => 0,
            'base_captura' => str_repeat('a', 64),
        ], [UploadedFile::fake()->create('otro.pdf', 20, 'application/pdf')]);
        $this->assertTrue($reintento['idempotente']);
        $this->assertSame(1, $ajustado['pedido']->documentos()->where('tipo', PedidoBmaDocumento::TIPO_ANEXO_PIEZAS)->count());

        $fresco = $ajustado['pedido']->fresh();
        try {
            app(RegistrarAjusteConsultaNacional::class)->ejecutar($fresco, $this->usuario, [
                'tipo' => 'retiro',
                'solicitud_id' => (string) Str::uuid(),
                'revision_esperada' => (int) $respondido->revision_registro,
                'base_captura' => $base['base_captura'],
            ], [UploadedFile::fake()->image('retiro.jpg')]);
            $this->fail('Una captura obsoleta debe rechazarse.');
        } catch (ConflictoCapturaPedido) {
            $this->assertSame(1, $fresco->fresh()->documentos()->where('tipo', PedidoBmaDocumento::TIPO_ANEXO_PIEZAS)->count());
        }

        $otra = app(ResponderPesajePedidoBmaService::class)->ejecutar($fresco->fresh(), $this->usuario->id, [[
            'catalogo_tipo_caja_id' => $tipo->id,
            'largo' => 20,
            'ancho' => 15,
            'alto' => 10,
            'peso_real_kg' => 2.5,
            'peso_volumetrico_kg' => 0.6,
            'uuid_operativo' => $uuid,
            'client_uuid' => $uuid,
        ]], [
            'evidencias_envios' => [[UploadedFile::fake()->image('caja-2.jpg')]],
            'revisiones' => [[
                'descripcion_producto' => 'Aroma de prueba',
                'sku' => 'SKU-NAC',
                'cantidad_solicitada' => 2,
                'cantidad_encontrada' => 2,
                'estado_fisico' => 'bueno',
            ], [
                'descripcion_producto' => 'Pieza anexa',
                'sku' => 'SKU-2',
                'cantidad_solicitada' => 1,
                'cantidad_encontrada' => 1,
                'estado_fisico' => 'bueno',
                'client_uuid' => $fresco->solicitud_preparacion_json['piezas_nacionales_vigentes'][0]['id'],
            ]],
        ]);
        $this->assertFalse((bool) $otra->consulta_actualizacion_pendiente);
        $this->assertNull($otra->consulta_cerrada_at);
        $this->assertEquals(2.5, (float) $otra->peso_real_kg);

        $cerrado = app(CerrarConsultaPedidoBmaService::class)->ejecutar($otra->fresh(), $this->usuario->id);
        $this->assertNotNull($cerrado->consulta_cerrada_at);
        $this->assertSame($pedido->id, $cerrado->id);
    }

    public function test_alta_mixta_con_paqueteria_pendiente_y_retiro_de_una_sola_solicitud(): void
    {
        Storage::fake('public');
        $this->actingAs($this->usuario);
        $this->usuario->givePermissionTo(Permission::findOrCreate('control_pedidos.cedis', 'web'));
        $pendiente = CatalogoPaqueteriaPedido::query()->updateOrCreate(['nombre' => 'PAQ. PENDIENTE'], [
            'categoria' => 'local_regional',
            'sin_logistica' => true,
            'requiere_peso' => false,
            'permite_costo_diferido' => true,
            'modalidad_tarifa' => 'fija',
            'tarifa_monto' => 999,
            'activo' => true,
        ]);
        $cedis = Almacen::query()->create([
            'codigo' => 'CEDIS-NAC', 'nombre' => 'CEDIS nacional', 'activo' => true,
            'tipo_almacen_id' => CatalogoTipoAlmacen::query()->firstOrCreate(['nombre' => 'CEDIS'])->id,
            'permite_salida_pedidos' => true,
        ]);
        CatalogoOrigenPedido::query()->firstOrCreate(['nombre' => 'Envío Foráneo'], [
            'requiere_logistica' => true, 'activo' => true,
        ]);

        $idSku = (string) Str::uuid();
        $idFoto = (string) Str::uuid();
        $idPdf = (string) Str::uuid();
        $piezas = [
            ['id' => $idSku, 'sku' => 'SKU-1', 'descripcion' => 'Aroma', 'cantidad' => 2],
            ['id' => $idFoto, 'descripcion' => 'Aroma edición especial', 'cantidad' => 1, 'evidencia' => UploadedFile::fake()->image('pieza.jpg')],
            ['id' => $idPdf, 'descripcion' => 'Aroma edición especial', 'cantidad' => 1, 'evidencia' => UploadedFile::fake()->create('pieza.pdf', 10, 'application/pdf')],
        ];
        $alta = [
            'folio_remision' => 'WIZ-MIXTO',
            'total_mercancia' => 100,
            'catalogo_paqueteria_id' => $pendiente->id,
            'guardar_mercancia_nacional' => '1',
            'solicitud_preparacion' => ['modalidad_entrega' => 'nacional', 'captura_nacional' => 'compartida'],
            'captura_clave' => (string) Str::uuid(),
            'piezas_nacionales' => $piezas,
        ];
        $lectura = $this->post('/control-pedidos/autoguardar', $alta, ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('solicitud_preparacion.piezas_nacionales.0.sku', 'SKU-1')
            ->assertJsonPath('solicitud_preparacion.piezas_nacionales.1.sku', null)
            ->assertJsonPath('costo_envio', null)->json();
        $pedido = PedidoBma::query()->findOrFail($lectura['id']);
        $this->post('/control-pedidos/autoguardar', $alta, ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('id', $pedido->id);

        $soporte = $pedido->documentos()->get();
        $this->assertCount(2, $soporte);
        foreach ($soporte as $documento) {
            Storage::disk('public')->assertExists($documento->ruta_archivo);
        }
        $this->assertTrue($pedido->tieneSoporteSolicitud());
        $this->post('/control-pedidos/'.$pedido->id.'/pdf-pedido', [
            'pdf_pedido' => UploadedFile::fake()->create('wizerp.pdf', 20, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertOk();
        $pedido->refresh();
        $this->getJson('/control-pedidos/'.$pedido->id.'/consulta-nacional')
            ->assertOk()->assertJsonCount(3, 'documentos')->assertJsonCount(3, 'solicitud_preparacion.piezas_nacionales');
        $this->assertFalse($pedido->esMunicipioDiferido());
        $this->assertFalse($pedido->consultaCedisModoRevisionMercancia());

        $this->postJson('/control-pedidos/'.$pedido->id.'/solicitar-pesaje', [
            'revision_esperada' => (int) $pedido->revision_registro, 'base_captura' => $pedido->base_captura,
        ])->assertOk()->assertJsonPath('almacen_id', $cedis->id);
        $pedido->refresh();
        $servicio = app(ResponderPesajePedidoBmaService::class);
        try {
            $servicio->ejecutar($pedido, $this->usuario->id, []);
            $this->fail('La consulta pendiente debe exigir peso real por caja.');
        } catch (\InvalidArgumentException $error) {
            $this->assertStringContainsString('pesos', $error->getMessage());
        }

        $tipo = CatalogoTipoCajaPedido::query()->create(['nombre' => 'Caja mixta', 'activo' => true]);
        $caja = [
            'catalogo_tipo_caja_id' => $tipo->id, 'largo' => 20, 'ancho' => 15, 'alto' => 10,
            'peso_real_kg' => 1.6, 'peso_volumetrico_kg' => 0.6, 'client_uuid' => (string) Str::uuid(),
        ];
        $respuestas = [[
            'descripcion_producto' => 'Aroma', 'sku' => 'SKU-1', 'cantidad_solicitada' => 2,
            'cantidad_encontrada' => 2, 'estado_fisico' => 'bueno', 'client_uuid' => $idSku,
        ], [
            'descripcion_producto' => 'Aroma edición especial', 'cantidad_solicitada' => 1,
            'cantidad_encontrada' => 1, 'estado_fisico' => 'bueno', 'client_uuid' => $idFoto,
        ], [
            'descripcion_producto' => 'Aroma edición especial', 'cantidad_solicitada' => 1,
            'cantidad_encontrada' => 0, 'estado_fisico' => 'sin_existencia', 'comentario' => 'Pieza de la captura PDF agotada.',
            'client_uuid' => $idPdf,
        ]];
        try {
            $servicio->ejecutar($pedido, $this->usuario->id, [$caja], ['revisiones' => array_slice($respuestas, 0, 2)]);
            $this->fail('La respuesta no puede saltar una solicitud individual.');
        } catch (\InvalidArgumentException $error) {
            $this->assertStringContainsString('cada pieza', $error->getMessage());
        }

        $respondido = $servicio->ejecutar($pedido, $this->usuario->id, [$caja], [
            'revisiones' => $respuestas, 'evidencias_envios' => [[UploadedFile::fake()->image('contenido.jpg')]],
        ]);
        $mapa = $respondido->solicitud_preparacion_json['respuestas_piezas_nacionales'];
        $this->assertCount(3, array_unique($mapa));
        $this->assertEquals(1, $respondido->revisionesProducto()->findOrFail($mapa[$idFoto])->cantidad_encontrada);
        $this->assertEquals(0, $respondido->revisionesProducto()->findOrFail($mapa[$idPdf])->cantidad_encontrada);

        $this->postJson('/control-pedidos/'.$pedido->id.'/ajuste-nacional', [
            'tipo' => 'retiro', 'motivo' => 'Agotado', 'solicitud_id' => (string) Str::uuid(),
            'revision_esperada' => (int) $respondido->revision_registro, 'base_captura' => $respondido->base_captura,
            'piezas' => [['revision_id' => $mapa[$idPdf], 'descripcion' => 'Aroma edición especial', 'cantidad' => 1]],
        ])->assertOk()->assertJsonPath('consulta_actualizacion_pendiente', true)
            ->assertJsonPath('puede_cerrar_consulta', false);

        $caja['peso_real_kg'] = 1.2;
        $final = $servicio->ejecutar($pedido->fresh(), $this->usuario->id, [$caja], [
            'revisiones' => array_slice($respuestas, 0, 2),
            'evidencias_envios' => [[UploadedFile::fake()->image('contenido-actualizado.jpg')]],
        ]);
        $this->assertSame([$idSku, $idFoto], array_column($final->solicitud_preparacion_json['piezas_nacionales_vigentes'], 'id'));
        $this->assertNotNull($final->revisionesProducto()->findOrFail($mapa[$idPdf])->retirada_at);
        $this->assertEquals(1.2, $final->peso_real_kg);
        $this->assertTrue($final->puedeCerrarConsulta());
        $this->assertNull($final->costo_envio);
    }

    public function test_abrir_o_guardar_un_historico_no_escribe_la_marca(): void
    {
        $historico = $this->pedidoNacional(marcado: false);
        $this->actingAs($this->usuario)
            ->getJson('/control-pedidos/'.$historico->id.'/consulta-nacional')
            ->assertNotFound();
        $this->assertArrayNotHasKey('captura_nacional', $historico->fresh()->solicitud_preparacion_json);

        $respuesta = $this->actingAs($this->usuario)->postJson('/control-pedidos/autoguardar', [
            'pedido_id' => $historico->id,
            'revision_esperada' => (int) $historico->revision_registro,
            'base_captura' => $historico->base_captura,
            'solicitud_preparacion' => [
                'modalidad_entrega' => 'nacional',
                'captura_nacional' => 'compartida',
                'area_revision' => 'control_pedidos',
            ],
        ]);
        $respuesta->assertOk();
        $json = $historico->fresh()->solicitud_preparacion_json;
        $this->assertArrayNotHasKey('captura_nacional', $json);
    }

    public function test_la_consulta_nacional_no_acepta_revision_sin_pesaje_y_la_captura_obsoleta_responde_409(): void
    {
        $pedido = $this->pedidoNacional();
        $pedido->update(['solicitud_preparacion_json' => [
            'modalidad_entrega' => 'nacional',
            'captura_nacional' => 'compartida',
            'area_revision' => 'control_pedidos',
        ]]);
        try {
            app(SolicitarPesajePedidoBmaService::class)->ejecutar($pedido->fresh(), $this->usuario->id);
            $this->fail('La revisión sin pesaje no envía la consulta nacional.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('pesaje', $e->getMessage());
        }

        $vigente = $this->pedidoNacional();
        $this->actingAs($this->usuario)->post('/control-pedidos/'.$vigente->id.'/ajuste-nacional', [
            'tipo' => 'anexo',
            'solicitud_id' => (string) Str::uuid(),
            'revision_esperada' => 99,
            'base_captura' => str_repeat('b', 64),
            'evidencia' => UploadedFile::fake()->create('anexo.pdf', 10, 'application/pdf'),
        ])->assertStatus(409);
        $this->assertSame(0, $vigente->documentos()->where('tipo', PedidoBmaDocumento::TIPO_ANEXO_PIEZAS)->count());
    }

    public function test_el_retiro_permanece_en_el_pedido_y_la_respuesta_anterior_no_cierra(): void
    {
        Storage::fake('public');
        $pedido = $this->pedidoConRespuesta();
        $this->assertTrue($pedido->puedeCerrarConsulta());
        $this->assertSame(1, $pedido->revisionesProducto()->count());
        $evidenciasPrevias = $pedido->documentos()->where('tipo', PedidoBmaDocumento::TIPO_EVIDENCIA_CONDICION)->count();

        $this->actingAs($this->usuario)->post('/control-pedidos/'.$pedido->id.'/ajuste-nacional', [
            'tipo' => 'retiro',
            'solicitud_id' => (string) Str::uuid(),
            'motivo' => 'El cliente ya no lo quiere',
            'piezas' => [['sku' => 'SKU-NAC', 'descripcion' => 'Aroma de prueba', 'cantidad' => 1]],
            'revision_esperada' => (int) $pedido->revision_registro,
            'base_captura' => $pedido->base_captura,
            'evidencia' => UploadedFile::fake()->image('retiro.jpg'),
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('pedido_id', $pedido->id)
            ->assertJsonPath('principal_id', $pedido->id)
            ->assertJsonPath('idempotente', false);

        $fresco = $pedido->fresh();
        $this->assertSame($pedido->id, $fresco->id);
        $this->assertSame(1, PedidoBma::query()->count());
        $this->assertSame('retiro', $fresco->solicitud_preparacion_json['ajustes_nacionales'][0]['tipo']);
        $this->assertSame('SKU-NAC', $fresco->solicitud_preparacion_json['ajustes_nacionales'][0]['piezas'][0]['sku']);
        $documentoRetiro = PedidoBmaDocumento::query()->findOrFail($fresco->solicitud_preparacion_json['ajustes_nacionales'][0]['documento_ids'][0]);
        $this->assertSame(PedidoBmaDocumento::TIPO_EVIDENCIA_CONDICION, $documentoRetiro->tipo);
        $this->assertSame($evidenciasPrevias + 1, $fresco->documentos()->where('tipo', PedidoBmaDocumento::TIPO_EVIDENCIA_CONDICION)->count());
        $this->assertTrue((bool) $fresco->consulta_actualizacion_pendiente);
        $this->assertFalse($fresco->puedeCerrarConsulta());
        $this->assertNull($fresco->consulta_cerrada_at);
        $this->assertSame('SKU-NAC', $fresco->revisionesProducto()->first()->sku);

        $this->actingAs($this->usuario)->postJson('/control-pedidos/'.$pedido->id.'/cerrar-consulta', [
            'modalidad_esperada' => 'nacional',
        ])->assertStatus(422);
        $this->assertNull($pedido->fresh()->consulta_cerrada_at);
        $this->assertSame(1, PedidoBma::query()->count());
    }

    public function test_cerrar_consulta_http_exige_permiso_y_rechaza_el_contexto_obsoleto(): void
    {
        Storage::fake('public');
        $pedido = $this->pedidoConRespuesta();
        $version = (int) $pedido->historial()->max('id');
        $this->assertGreaterThan(0, $version);
        $cuerpo = ['modalidad_esperada' => 'nacional', 'version_consulta' => $version];

        $this->actingAs(User::factory()->create())
            ->postJson('/control-pedidos/'.$pedido->id.'/cerrar-consulta', $cuerpo)
            ->assertForbidden();
        $this->assertNull($pedido->fresh()->consulta_cerrada_at);

        $this->actingAs($this->usuario)->postJson('/control-pedidos/'.$pedido->id.'/cerrar-consulta', [
            'modalidad_esperada' => 'municipal',
            'version_consulta' => $version,
        ])->assertStatus(409)->assertJsonPath('codigo', 'contexto_obsoleto');

        $this->actingAs($this->usuario)->postJson('/control-pedidos/'.$pedido->id.'/cerrar-consulta', [
            'modalidad_esperada' => 'nacional',
            'version_consulta' => $version + 40,
        ])->assertStatus(409)->assertJsonPath('codigo', 'vista_atrasada');
        $this->assertNull($pedido->fresh()->consulta_cerrada_at);

        $this->actingAs($this->usuario)->postJson('/control-pedidos/'.$pedido->id.'/cerrar-consulta', $cuerpo)
            ->assertOk()
            ->assertJsonPath('consulta_cerrada', true);
        $this->assertNotNull($pedido->fresh()->consulta_cerrada_at);
        $this->assertSame($pedido->id, (int) $pedido->fresh()->id);
    }

    public function test_el_candado_de_ajuste_es_remision_o_pago_validado(): void
    {
        Storage::fake('public');
        $mensaje = 'La mercancía ya tiene remisión o pago. El complemento se registra aparte y no modifica este pedido.';

        $pagado = $this->pedidoNacional();
        $pagado->update(['pago_validado_at' => now(), 'numero_remision' => null]);
        $this->actingAs($this->usuario)->post('/control-pedidos/'.$pagado->id.'/ajuste-nacional', $this->cuerpoAjuste($pagado), ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonPath('message', $mensaje);
        $this->assertSame([], $pagado->fresh()->solicitud_preparacion_json['ajustes_nacionales'] ?? []);

        $remisionado = $this->pedidoNacional();
        $remisionado->update(['numero_remision' => 'REM-9', 'pago_validado_at' => null]);
        $this->actingAs($this->usuario)->post('/control-pedidos/'.$remisionado->id.'/ajuste-nacional', $this->cuerpoAjuste($remisionado), ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonPath('message', $mensaje);

        $conCosto = $this->pedidoNacional();
        $conCosto->update(['costo_envio' => 80, 'numero_remision' => null, 'pago_validado_at' => null]);
        $otro = $this->actingAs($this->usuario)->post('/control-pedidos/'.$conCosto->id.'/ajuste-nacional', $this->cuerpoAjuste($conCosto), ['Accept' => 'application/json']);
        $otro->assertStatus(422);
        $this->assertStringNotContainsString('remisión o pago', (string) $otro->json('message'));
    }

    private function pedidoConRespuesta(): PedidoBma
    {
        $pedido = $this->pedidoNacional();
        $consultado = app(SolicitarPesajePedidoBmaService::class)->ejecutar($pedido, $this->usuario->id);
        $tipo = CatalogoTipoCajaPedido::query()->create(['nombre' => 'Caja cierre '.uniqid(), 'activo' => true]);
        $uuid = (string) Str::uuid();

        return app(ResponderPesajePedidoBmaService::class)->ejecutar($consultado, $this->usuario->id, [[
            'catalogo_tipo_caja_id' => $tipo->id,
            'largo' => 20,
            'ancho' => 15,
            'alto' => 10,
            'peso_real_kg' => 1.25,
            'peso_volumetrico_kg' => 0.6,
            'uuid_operativo' => $uuid,
            'client_uuid' => $uuid,
        ]], [
            'evidencias_envios' => [[UploadedFile::fake()->image('caja.jpg')]],
            'revisiones' => [[
                'descripcion_producto' => 'Aroma de prueba',
                'sku' => 'SKU-NAC',
                'cantidad_solicitada' => 1,
                'cantidad_encontrada' => 1,
                'estado_fisico' => 'bueno',
                'client_uuid' => (string) Str::uuid(),
            ]],
        ])->fresh();
    }

    /** @return array<string, mixed> */
    private function cuerpoAjuste(PedidoBma $pedido): array
    {
        return [
            'tipo' => 'retiro',
            'solicitud_id' => (string) Str::uuid(),
            'piezas' => [['sku' => 'SKU-1', 'descripcion' => 'Pieza', 'cantidad' => 1]],
            'revision_esperada' => (int) $pedido->revision_registro,
            'base_captura' => $pedido->base_captura,
            'evidencia' => UploadedFile::fake()->image('retiro.jpg'),
        ];
    }

    private function pedidoNacional(bool $marcado = true): PedidoBma
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
        $json = ['modalidad_entrega' => 'nacional', 'codigo_modalidad' => 'ENVIO_BODEGA_NORMAL'];
        if ($marcado) {
            $json['captura_nacional'] = 'compartida';
        }
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
            'solicitud_preparacion_json' => $json,
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
