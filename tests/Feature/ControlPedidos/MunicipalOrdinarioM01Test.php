<?php

namespace Tests\Feature\ControlPedidos;

use App\Models\CatalogoListaDescuento;
use App\Models\Cliente;
use App\Models\ClienteDocumento;
use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\CatalogoPaqueteriaPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\User;
use App\Services\ControlPedidos\NotificarPedidoBmaService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MunicipalOrdinarioM01Test extends TestCase
{
    use RefreshDatabase;

    private User $ventas;

    private Cliente $cliente;

    private PedidoBma $pedido;

    private CatalogoPaqueteriaPedido $jaguar;

    public function createApplication()
    {
        $app = parent::createApplication();
        $driver = $app['config']->get('database.default');
        $db = $app['config']->get("database.connections.{$driver}.database");
        if (! (($driver === 'sqlite' && $db === ':memory:') || ($driver === 'mysql' && $db === 'codex_m01_audit_20261114'))) {
            throw new \RuntimeException('Auditoría bloqueada fuera de SQLite en memoria o MySQL exclusivo.');
        }
        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        if (config('database.default') === 'mysql') {
            $this->assertStringStartsWith('codex_m01_audit_', config('database.connections.mysql.database'));
        } else {
            $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        }
        $this->withoutMiddleware([ValidateCsrfToken::class, PreventRequestForgery::class]);
        Storage::fake('local');
        Storage::fake('public');
        $this->mock(NotificarPedidoBmaService::class)->shouldReceive('ejecutar')->zeroOrMoreTimes();

        $this->ventas = User::factory()->create();
        foreach ([
            'control_pedidos.crear',
            'clientes.expediente.ver',
            'clientes.expediente.editar',
            'clientes.expediente.documentos',
            'clientes.expediente.ver_identificacion',
            'control_pedidos.cedis',
            'control_pedidos.cedis.ver_todos',
        ] as $permiso) {
            $this->ventas->givePermissionTo(Permission::findOrCreate($permiso, 'web'));
        }

        foreach (['BORRADOR', 'PESAJE_PENDIENTE', 'PESAJE_RESPONDIDO'] as $orden => $fase) {
            CatalogoEstatusPedido::query()->create([
                'codigo_interno' => 'M01_'.$fase,
                'nombre_visual' => $fase,
                'color_hex' => '#64748B',
                'fase_ciclo' => $fase,
                'orden' => $orden + 1,
                'activo' => true,
            ]);
        }

        $lista = CatalogoListaDescuento::query()->firstOrCreate(
            ['nombre' => 'PUBLICO GENERAL'],
            ['monto_requerido' => 0, 'activo' => true]
        );
        $this->cliente = Cliente::query()->create([
            'numero_cliente' => 'M01-001',
            'nombre' => 'Cliente municipal',
            'lista_actual_id' => $lista->id,
            'vendedor_id' => $this->ventas->id,
            'monto_venta_actual' => 0,
        ]);
        $estatus = CatalogoEstatusPedido::porFase('BORRADOR');
        $this->pedido = PedidoBma::query()->create([
            'folio' => 'M01-001',
            'fecha' => now()->toDateString(),
            'vendedor_id' => $this->ventas->id,
            'cliente_id' => $this->cliente->id,
            'catalogo_estatus_pedido_id' => $estatus->id,
            'total_mercancia' => 100,
            'solicitud_preparacion_json' => ['modalidad_entrega' => 'municipal'],
        ]);
        $departamento = \App\Models\Departamento::create(['nombre' => 'Departamento ficticio']);
        $this->ventas->update(['departamento_id' => $departamento->id]);
        $this->pedido->update(['departamento_operacion_id' => $departamento->id, 'folio_remision' => 'WIZ-FICTICIO']);
        Storage::disk('public')->put('qa/pedido.png', 'soporte ficticio');
        $this->pedido->documentos()->create(['tipo' => 'pdf_pedido', 'ruta_archivo' => 'qa/pedido.png',
            'nombre_original' => 'pedido.png', 'mime_type' => 'image/png', 'tamano_bytes' => 16, 'orden' => 0]);
        $this->jaguar = CatalogoPaqueteriaPedido::query()->create([
            'nombre' => 'Transporte Jaguar',
            'sin_logistica' => false,
            'categoria' => CatalogoPaqueteriaPedido::CATEGORIA_LOCAL_REGIONAL,
            'activo' => true,
            'permite_costo_diferido' => true,
            'habilitado_envio_municipio' => true,
            'permite_por_cobrar' => false,
            'reglas_municipio_pendientes' => false,
        ]);
    }

    public function test_primer_envio_exige_soporte_y_datos_comunes_sin_exigir_pago(): void
    {
        $this->actingAs($this->ventas);
        $consignatario = $this->postJson(route('control_pedidos.municipal.expediente.store', $this->cliente), [
            'nombre' => 'Persona ficticia', 'telefono' => '3311111111', 'pedido_id' => $this->pedido->id,
        ])->assertCreated()->json('id');
        $this->postJson(route('control_pedidos.municipal.solicitud', $this->pedido), [
            'catalogo_paqueteria_id' => $this->jaguar->id, 'consignatario_id' => $consignatario,
            'leyenda_terminal' => 'PAGADO', 'costo_transporte' => 'diferido',
        ])->assertOk();
        $this->pedido->documentos()->update(['activo' => false]);
        $this->postJson(route('control_pedidos.municipal.consulta', $this->pedido), [])
            ->assertUnprocessable()->assertJsonValidationErrors('pdf_pedido');
        $this->pedido->documentos()->delete();
        $this->postJson(route('control_pedidos.municipal.consulta', $this->pedido), [])
            ->assertUnprocessable()->assertJsonValidationErrors('pdf_pedido');
        $this->pedido->update(['folio_remision' => null]);
        $this->postJson(route('control_pedidos.municipal.consulta', $this->pedido), [])
            ->assertUnprocessable()->assertJsonValidationErrors('pedido');
        $this->assertArrayNotHasKey('consulta_municipal', $this->pedido->fresh()->solicitud_preparacion_json);
    }

    public function test_autoguardado_conserva_expediente_y_transporte_sin_aceptar_estado_falsificado(): void
    {
        $this->actingAs($this->ventas);
        $consignatario = $this->postJson(route('control_pedidos.municipal.expediente.store', $this->cliente), [
            'nombre' => 'Persona ficticia', 'telefono' => '3311111111', 'pedido_id' => $this->pedido->id,
        ])->assertCreated()->json('id');
        $this->postJson(route('control_pedidos.municipal.solicitud', $this->pedido), [
            'catalogo_paqueteria_id' => $this->jaguar->id, 'consignatario_id' => $consignatario,
            'leyenda_terminal' => 'PAGADO', 'costo_transporte' => 'diferido',
        ])->assertOk();
        $base = $this->pedido->fresh();
        $previa = $base->solicitud_preparacion_json;
        $this->postJson('/control-pedidos/autoguardar', ['pedido_id' => $this->pedido->id,
            'revision_esperada' => $base->revision_registro, 'base_captura' => \App\Support\ControlPedidos\RevisionCapturaPedido::base($base),
            'folio_remision' => 'WIZ-CORREGIDO', 'solicitud_preparacion' => ['modalidad_entrega' => 'municipal',
                'consulta_municipal' => ['estado' => 'cerrada'], 'expediente' => ['consignatario_id' => 99999]],
        ])->assertOk();
        $guardado = $this->pedido->fresh();
        $this->assertSame('WIZ-CORREGIDO', $guardado->folio_remision);
        $this->assertSame($previa['expediente'], $guardado->solicitud_preparacion_json['expediente']);
        $this->assertSame($previa['finanzas_municipales'], $guardado->solicitud_preparacion_json['finanzas_municipales']);
        $this->assertArrayNotHasKey('consulta_municipal', $guardado->solicitud_preparacion_json);
        $this->assertNull($guardado->costo_envio);
        $this->assertFalse($guardado->puedeAnexarPagoEnvio());
        $this->postJson(route('control_pedidos.municipal.consulta', $guardado), [])->assertOk();
        $this->postJson('/control-pedidos/autoguardar', ['pedido_id' => $guardado->id, 'folio_remision' => 'ALTERADO',
            'revision_esperada' => $guardado->fresh()->revision_registro, 'base_captura' => \App\Support\ControlPedidos\RevisionCapturaPedido::base($guardado->fresh())])
            ->assertUnprocessable()->assertJsonValidationErrors('folio_remision');
    }

    public function test_terminal_local_regional_guarda_sin_municipio_y_reabre_desde_catalogo(): void
    {
        $this->actingAs($this->ventas);
        // Las banderas municipales antiguas no definen la captura vigente.
        $this->jaguar->update(['habilitado_envio_municipio' => false, 'reglas_municipio_pendientes' => true]);
        $consignatario = $this->postJson(route('control_pedidos.municipal.expediente.store', $this->cliente), [
            'nombre' => 'Consignatario ficticio', 'telefono' => '3311111111', 'pedido_id' => $this->pedido->id,
        ])->assertCreated()->json('id');
        $almacen = \App\Models\Almacen::create(['codigo' => 'M01-ORIGEN', 'nombre' => 'Origen ficticio', 'activo' => true, 'visible_en_pedidos' => true]);
        $this->postJson(route('control_pedidos.municipal.solicitud', $this->pedido), [
            'catalogo_paqueteria_id' => $this->jaguar->id, 'consignatario_id' => $consignatario,
            'almacen_id' => $almacen->id,
            'leyenda_terminal' => 'PAGADO', 'costo_transporte' => 'diferido',
        ])->assertOk()->assertJsonPath('catalogo_paqueteria_id', $this->jaguar->id)
            ->assertJsonPath('paqueteria.nombre', 'Transporte Jaguar');
        $reabierto = $this->pedido->fresh();
        $this->assertArrayNotHasKey('municipio_destino', $reabierto->solicitud_preparacion_json);
        $this->assertSame($consignatario, $reabierto->solicitud_preparacion['expediente']['consignatario_id']);
        $this->assertSame('diferido', $reabierto->solicitud_preparacion['finanzas_municipales']['costo_transporte']);
        $this->assertSame($almacen->id, $reabierto->solicitud_preparacion['almacen_revision_id']);
        $this->assertArrayNotHasKey('consulta_municipal', $reabierto->solicitud_preparacion);
        $catalogos = app(\App\Services\ControlPedidos\ObtenerCatalogosPedidoBmaService::class)->ejecutar();
        $terminal = $catalogos['paqueterias']->firstWhere('id', $this->jaguar->id);
        $this->assertFalse($terminal->sin_logistica);
        $this->assertTrue($terminal->permite_costo_diferido);
        $this->assertFalse($terminal->permite_por_cobrar);
        $this->jaguar->update(['nombre' => 'Terminal renombrada']);
        $this->assertSame('Terminal renombrada', $this->pedido->fresh()->paqueteria->nombre);
        $this->postJson(route('control_pedidos.municipal.consulta', $this->pedido), [])->assertOk()
            ->assertJsonPath('paqueteria.nombre', 'Terminal renombrada');
        $this->assertSame('cedis', $this->pedido->fresh()->solicitud_preparacion['consulta_municipal']['area']);
    }

    public function test_terminal_inactiva_comercial_o_inexistente_no_se_guarda(): void
    {
        $this->actingAs($this->ventas);
        $datos = ['catalogo_paqueteria_id' => $this->jaguar->id, 'consignatario_id' => 1,
            'leyenda_terminal' => 'PAGADO', 'costo_transporte' => 'diferido'];
        $this->jaguar->update(['categoria' => 'comercial', 'sin_logistica' => true]);
        $this->postJson(route('control_pedidos.municipal.solicitud', $this->pedido), $datos)
            ->assertUnprocessable()->assertJsonValidationErrors('catalogo_paqueteria_id');
        $this->jaguar->update(['categoria' => 'local_regional', 'activo' => false]);
        $this->postJson(route('control_pedidos.municipal.solicitud', $this->pedido), $datos)
            ->assertUnprocessable()->assertJsonValidationErrors('catalogo_paqueteria_id');
        $datos['catalogo_paqueteria_id'] = 999999;
        $this->postJson(route('control_pedidos.municipal.solicitud', $this->pedido), $datos)
            ->assertUnprocessable()->assertJsonValidationErrors('catalogo_paqueteria_id');
    }

    public function test_destino_historico_y_atributos_de_transporte_se_conservan(): void
    {
        $this->actingAs($this->ventas);
        $this->pedido->update(['solicitud_preparacion_json' => ['modalidad_entrega' => 'municipal', 'municipio_destino' => 'Municipio histórico']]);
        $consignatario = $this->postJson(route('control_pedidos.municipal.expediente.store', $this->cliente), [
            'nombre' => 'Consignatario ficticio', 'telefono' => '3311111111', 'pedido_id' => $this->pedido->id,
        ])->assertCreated()->json('id');
        $datos = ['catalogo_paqueteria_id' => $this->jaguar->id, 'consignatario_id' => $consignatario,
            'leyenda_terminal' => 'POR_COBRAR', 'costo_transporte' => 'diferido'];
        $this->postJson(route('control_pedidos.municipal.solicitud', $this->pedido), $datos)
            ->assertUnprocessable()->assertJsonValidationErrors('leyenda_terminal');
        $this->jaguar->update(['permite_por_cobrar' => true, 'permite_costo_diferido' => false]);
        $this->postJson(route('control_pedidos.municipal.solicitud', $this->pedido), $datos)
            ->assertUnprocessable()->assertJsonValidationErrors('costo_transporte');
        $datos['costo_transporte'] = 'conocido';
        $datos['costo_envio'] = 35;
        $this->postJson(route('control_pedidos.municipal.solicitud', $this->pedido), $datos)->assertOk()
            ->assertJsonPath('solicitud_preparacion.municipio_destino', 'Municipio histórico')
            ->assertJsonPath('solicitud_preparacion.finanzas_municipales.leyenda_terminal', 'POR_COBRAR');
        $this->jaguar->update(['activo' => false]);
        $this->postJson(route('control_pedidos.municipal.consulta', $this->pedido), [])->assertUnprocessable()
            ->assertJsonValidationErrors('catalogo_paqueteria_id');
    }

    public function test_fallo_de_escritura_no_expone_ruta_ni_sustituye_identificacion_vigente(): void
    {
        $servicio = app(\App\Services\Clientes\Expediente\GestionarExpedienteClienteService::class);
        $consignatario = $servicio->crear($this->cliente, $this->ventas, 'Persona ficticia', '3311111111', $this->pedido);
        $anterior = $servicio->guardarDocumento($consignatario, $this->ventas, UploadedFile::fake()->image('vigente.png'), $this->pedido);
        $imagen = UploadedFile::fake()->image('sustituta.png');
        $archivo = \Mockery::mock(UploadedFile::class, [$imagen->getRealPath(), 'sustituta.png', 'image/png', null, true])->makePartial();
        $archivo->shouldReceive('store')->once()->andThrow(\League\Flysystem\UnableToCreateDirectory::atLocation('/ruta/privada/expediente'));
        try {
            $servicio->guardarDocumento($consignatario, $this->ventas, $archivo, $this->pedido);
            $this->fail('El fallo de escritura debe mostrarse como validación.');
        } catch (\Illuminate\Validation\ValidationException $error) {
            $mensaje = $error->errors()['archivo'][0];
            $this->assertStringContainsString('No se pudo guardar la identificación', $mensaje);
            $this->assertStringNotContainsString('/ruta/privada', $mensaje);
        }
        $this->assertTrue($anterior->fresh()->vigente);
        $this->assertSame(1, $consignatario->documentos()->count());
    }

    public function test_jaguar_conserva_costo_desconocido_y_el_expediente_su_identidad(): void
    {
        $this->actingAs($this->ventas);
        $alta = $this->postJson(route('control_pedidos.municipal.expediente.store', $this->cliente), [
            'nombre' => 'María',
            'telefono' => '3311111111',
            'pedido_id' => $this->pedido->id,
        ])->assertCreated();
        $consignatarioId = $alta->json('id');
        $documento = $this->postJson(route('control_pedidos.municipal.expediente.documento', [$this->cliente, $consignatarioId]), [
            'archivo' => UploadedFile::fake()->create('ine.pdf', 20, 'application/pdf'),
            'pedido_id' => $this->pedido->id,
        ])->assertCreated();
        $documentoId = $documento->json('id');

        $this->postJson(route('control_pedidos.municipal.solicitud', $this->pedido), [
            'municipio_destino' => 'Lagos de Moreno',
            'catalogo_paqueteria_id' => $this->jaguar->id,
            'consignatario_id' => $consignatarioId,
            'leyenda_terminal' => 'PAGADO',
            'costo_transporte' => 'diferido',
        ])->assertOk()
            ->assertJsonPath('estatus_envio', PedidoBma::ESTATUS_ENVIO_PENDIENTE_REGULARIZACION)
            ->assertJsonPath('costo_envio', null);

        $this->from('/control-pedidos')->post(route('control_pedidos.pagos.store', $this->pedido), [
            'monto' => 100,
            'forma_pago' => 'efectivo',
        ])->assertRedirect()->assertSessionHas('error');

        $this->postJson(route('control_pedidos.municipal.consulta', $this->pedido), [])->assertOk();
        $this->pedido->refresh();
        $this->assertSame(PedidoBma::ESTATUS_ENVIO_PENDIENTE_REGULARIZACION, $this->pedido->estatus_envio);
        $this->assertNull($this->pedido->costo_envio);

        $this->post(route('control_pedidos.municipal.disponibilidad', $this->pedido), [
            'confirmacion_explicita' => 1,
            'evidencias' => [UploadedFile::fake()->image('conjunto.jpg')],
        ])->assertOk();
        $this->pedido->refresh();
        $this->assertSame(PedidoBma::ESTATUS_ENVIO_PENDIENTE_REGULARIZACION, $this->pedido->estatus_envio);
        $this->assertNull($this->pedido->costo_envio);
        $this->assertSame('respondida', $this->pedido->solicitud_preparacion_json['consulta_municipal']['estado']);
        $this->assertNotSame('pesaje_listo', $this->pedido->estatus_envio);

        $this->postJson(route('control_pedidos.cerrar_consulta', $this->pedido))->assertOk();
        $this->pedido->refresh();
        $this->assertSame('cerrada', $this->pedido->solicitud_preparacion_json['consulta_municipal']['estado']);
        $this->assertSame(PedidoBma::ESTATUS_ENVIO_PENDIENTE_REGULARIZACION, $this->pedido->estatus_envio);
        $this->assertSame($documentoId, $this->pedido->solicitud_preparacion_json['expediente']['documento_id']);

        $this->putJson(route('control_pedidos.municipal.expediente.update', [$this->cliente, $consignatarioId]), [
            'nombre' => 'María',
            'telefono' => '3322222222',
            'pedido_id' => $this->pedido->id,
        ])->assertOk();
        $this->assertSame($documentoId, ClienteDocumento::query()->where('vigente', true)->value('id'));
        $this->pedido->refresh();
        $this->assertSame('3311111111', $this->pedido->solicitud_preparacion_json['expediente']['telefono']);

        $this->postJson(route('control_pedidos.municipal.expediente.documento', [$this->cliente, $consignatarioId]), [
            'archivo' => UploadedFile::fake()->create('ine-nueva.pdf', 20, 'application/pdf'),
            'pedido_id' => $this->pedido->id,
        ])->assertCreated();
        $this->pedido->refresh();
        $this->assertSame($documentoId, $this->pedido->solicitud_preparacion_json['expediente']['documento_id']);
        $this->assertNotSame($documentoId, ClienteDocumento::query()->where('vigente', true)->value('id'));

        $ajeno = User::factory()->create();
        $this->actingAs($ajeno);
        $this->getJson(route('control_pedidos.municipal.expediente.index', $this->cliente).'?pedido_id='.$this->pedido->id)
            ->assertStatus(422);
    }

    public function test_excepcion_pendiente_impide_cerrar_la_consulta(): void
    {
        $this->actingAs($this->ventas);
        $alta = $this->postJson(route('control_pedidos.municipal.expediente.store', $this->cliente), [
            'nombre' => 'María',
            'telefono' => '3311111111',
            'pedido_id' => $this->pedido->id,
        ])->assertCreated();
        $this->postJson(route('control_pedidos.municipal.solicitud', $this->pedido), [
            'municipio_destino' => 'Lagos de Moreno',
            'catalogo_paqueteria_id' => $this->jaguar->id,
            'consignatario_id' => $alta->json('id'),
            'leyenda_terminal' => 'PAGADO',
            'costo_transporte' => 'diferido',
        ])->assertOk();
        $this->postJson(route('control_pedidos.municipal.consulta', $this->pedido))->assertOk();
        $this->post(route('control_pedidos.municipal.disponibilidad', $this->pedido), [
            'confirmacion_explicita' => 0,
            'excepciones' => [[
                'descripcion' => 'Pieza agotada',
                'sku' => 'SKU-1',
                'cantidad' => 1,
                'condicion' => 'faltante',
                'detalle' => 'Sin existencias',
                'unica_pieza' => false,
                'mejor_ejemplar' => false,
            ]],
            'evidencias' => [UploadedFile::fake()->image('conjunto.jpg')],
        ])->assertOk();
        $this->pedido->refresh();
        $this->assertSame('excepciones_pendientes', $this->pedido->solicitud_preparacion_json['consulta_municipal']['estado']);
        $this->assertSame(PedidoBma::ESTATUS_ENVIO_PENDIENTE_REGULARIZACION, $this->pedido->estatus_envio);
        $this->postJson(route('control_pedidos.cerrar_consulta', $this->pedido))->assertStatus(422);
    }
    private function guardarSolicitud(): int
    {
        $this->actingAs($this->ventas);
        $id = $this->postJson(route('control_pedidos.municipal.expediente.store', $this->cliente), [
            'nombre' => 'Consignatario prueba', 'telefono' => '3311111111', 'pedido_id' => $this->pedido->id,
        ])->assertCreated()->json('id');
        $this->postJson(route('control_pedidos.municipal.solicitud', $this->pedido), [
            'municipio_destino' => 'Destino prueba', 'catalogo_paqueteria_id' => $this->jaguar->id,
            'consignatario_id' => $id, 'leyenda_terminal' => 'PAGADO', 'costo_transporte' => 'diferido',
        ])->assertOk();
        return $id;
    }

    private function responderConsulta(): void
    {
        $this->postJson(route('control_pedidos.municipal.consulta', $this->pedido))->assertOk();
        $this->post(route('control_pedidos.municipal.disponibilidad', $this->pedido), [
            'confirmacion_explicita' => 1, 'evidencias' => [UploadedFile::fake()->image('conjunto.jpg')],
        ])->assertOk();
        $this->pedido->refresh();
    }

    public function test_reenvio_no_borra_respuesta_ni_cierre_duplicado_crea_historial(): void
    {
        $this->guardarSolicitud();
        $this->responderConsulta();
        $revision = $this->pedido->solicitud_preparacion_json['revision_disponibilidad'];
        $this->postJson(route('control_pedidos.municipal.consulta', $this->pedido))->assertOk();
        $this->assertSame($revision, $this->pedido->fresh()->solicitud_preparacion_json['revision_disponibilidad'] ?? null);
        $this->postJson(route('control_pedidos.cerrar_consulta', $this->pedido))->assertOk();
        $primero = $this->pedido->fresh()->consulta_cerrada_at;
        $historial = $this->pedido->historial()->count();
        $this->postJson(route('control_pedidos.cerrar_consulta', $this->pedido))->assertOk();
        $this->assertEquals($primero, $this->pedido->fresh()->consulta_cerrada_at);
        $this->assertSame($historial, $this->pedido->historial()->count());
    }

    public function test_lector_cedis_no_puede_reescribir_solicitud_o_enviarla(): void
    {
        $id = $this->guardarSolicitud();
        $this->postJson(route('control_pedidos.municipal.consulta', $this->pedido))->assertOk();
        $lector = User::factory()->create();
        $lector->givePermissionTo(Permission::findOrCreate('control_pedidos.cedis', 'web'));
        $lector->givePermissionTo(Permission::findOrCreate('control_pedidos.cedis.ver_todos', 'web'));
        $this->assertTrue(\App\Support\ControlPedidos\VisibilidadPedidoBma::puedeConsultar($lector, $this->pedido->fresh()));
        $this->actingAs($lector);
        $this->postJson(route('control_pedidos.municipal.solicitud', $this->pedido), [
            'municipio_destino' => 'Otro destino', 'catalogo_paqueteria_id' => $this->jaguar->id,
            'consignatario_id' => $id, 'leyenda_terminal' => 'PAGADO', 'costo_transporte' => 'diferido',
        ])->assertStatus(422);
        $this->postJson(route('control_pedidos.municipal.consulta', $this->pedido))->assertStatus(422);
    }

    public function test_no_se_puede_responder_sin_enviar_o_en_otro_departamento(): void
    {
        $this->guardarSolicitud();
        $this->postJson(route('control_pedidos.municipal.consulta', $this->pedido))->assertOk();
        $otro = User::factory()->create();
        $otro->givePermissionTo(Permission::findOrCreate('control_pedidos.cedis', 'web'));
        $this->actingAs($otro)->post(route('control_pedidos.municipal.disponibilidad', $this->pedido), [
            'confirmacion_explicita' => 1, 'evidencias' => [UploadedFile::fake()->image('conjunto.jpg')],
        ])->assertForbidden();
    }

    public function test_identificaciones_historicas_solo_por_permiso_o_pedido_referenciado_incluso_tras_baja(): void
    {
        $id = $this->guardarSolicitud();
        $subir = fn () => $this->postJson(route('control_pedidos.municipal.expediente.documento', [$this->cliente, $id]), [
            'archivo' => UploadedFile::fake()->create('ine.pdf', 20, 'application/pdf'), 'pedido_id' => $this->pedido->id,
        ])->assertCreated()->json('id');
        $doc = $subir();
        $json = $this->pedido->fresh()->solicitud_preparacion_json;
        $json['expediente']['documento_id'] = $doc;
        $this->pedido->update(['solicitud_preparacion_json' => $json]);
        $subir();
        $url = route('control_pedidos.municipal.expediente.descargar', [$this->cliente, $doc]);
        $this->get($url)->assertForbidden();
        $this->get($url.'?pedido_id='.$this->pedido->id)->assertOk();
        $this->ventas->givePermissionTo(Permission::findOrCreate('clientes.ver', 'web'));
        $this->ventas->givePermissionTo(Permission::findOrCreate('clientes.expediente.eliminar', 'web'));
        $this->deleteJson('/admin/clientes/'.$this->cliente->id.'/expediente/documentos/'.$doc)->assertOk();
        $this->get($url.'?pedido_id='.$this->pedido->id)->assertOk();
        $this->deleteJson('/admin/clientes/'.$this->cliente->id.'/expediente/consignatarios/'.$id)->assertOk();
        $this->get($url.'?pedido_id='.$this->pedido->id)->assertOk();
        $this->get($url)->assertForbidden();
    }

    public function test_documento_tras_baja_continua_numeracion_y_no_expone_ruta(): void
    {
        $id = $this->guardarSolicitud();
        $servicio = app(\App\Services\Clientes\Expediente\GestionarExpedienteClienteService::class);
        $consignatario = \App\Models\ClienteConsignatario::findOrFail($id);
        $doc = $servicio->guardarDocumento($consignatario, $this->ventas, UploadedFile::fake()->create('ine.pdf', 20, 'application/pdf'), $this->pedido);
        $this->assertArrayNotHasKey('ruta', $doc->toArray());
        $this->ventas->givePermissionTo(Permission::findOrCreate('clientes.expediente.eliminar', 'web'));
        $servicio->eliminarDocumento($doc, $this->ventas, $this->pedido);
        $nuevo = $servicio->guardarDocumento($consignatario, $this->ventas, UploadedFile::fake()->create('ine.pdf', 20, 'application/pdf'), $this->pedido);
        $this->assertSame(2, $nuevo->version);
        $this->assertSame($doc->id, $nuevo->anterior_id);
    }

    public function test_archivo_ejecutable_no_se_publica_como_evidencia(): void
    {
        $this->guardarSolicitud();
        $this->postJson(route('control_pedidos.municipal.consulta', $this->pedido))->assertOk();
        $this->postJson(route('control_pedidos.municipal.disponibilidad', $this->pedido), [
            'confirmacion_explicita' => 1, 'evidencias' => [UploadedFile::fake()->create('script.html', 1, 'text/html')],
        ])->assertUnprocessable();
        $this->assertSame(0, $this->pedido->documentos()->where('tipo', 'evidencia_condicion')->count());
    }

    public function test_tienda_filtra_sucursal_y_rechaza_respuesta_directa_ajena(): void
    {
        $this->guardarSolicitud();
        $tipo = \App\Models\CatalogoTipoAlmacen::firstOrCreate(['nombre' => 'PDV']);
        $sucursal = \App\Models\Sucursal::create(['codigo' => 'M01-A', 'nombre' => 'Sucursal A', 'activo' => true]);
        $otra = \App\Models\Sucursal::create(['codigo' => 'M01-B', 'nombre' => 'Sucursal B', 'activo' => true]);
        $almacen = \App\Models\Almacen::create(['codigo' => 'M01-PDV', 'nombre' => 'PDV A', 'tipo_almacen_id' => $tipo->id,
            'sucursal_id' => $sucursal->id, 'activo' => true, 'visible_en_pedidos' => true]);
        $this->postJson(route('control_pedidos.municipal.consulta', $this->pedido), ['almacen_id' => $almacen->id])->assertOk();
        $operador = User::factory()->create();
        $operador->givePermissionTo(Permission::findOrCreate('control_pedidos.municipal.responder_consulta', 'web'));
        $operador->concederAccesoSucursal($otra);
        $this->actingAs($operador);
        $this->get(route('control_pedidos.municipal.consultas_tienda'))->assertOk()->assertInertia(fn ($page) => $page->has('pedidos', 0));
        $this->post(route('control_pedidos.municipal.disponibilidad', $this->pedido), [
            'confirmacion_explicita' => 1, 'evidencias' => [UploadedFile::fake()->image('foto.jpg')],
        ])->assertForbidden();
        $operador->concederAccesoSucursal($sucursal);
        $this->get(route('control_pedidos.municipal.consultas_tienda'))->assertOk()->assertInertia(fn ($page) => $page->has('pedidos', 1));
        $this->getJson('/control-pedidos/'.$this->pedido->id.'/disponibilidad-tienda')->assertOk();
        $this->post(route('control_pedidos.municipal.disponibilidad', $this->pedido), [
            'confirmacion_explicita' => 1, 'evidencias' => [UploadedFile::fake()->image('foto.jpg')],
        ])->assertOk();
        $this->assertSame(0, $this->pedido->tareasPreparacion()->count());
        $this->actingAs($this->ventas)->postJson(route('control_pedidos.cerrar_consulta', $this->pedido))->assertOk();
    }

    public function test_resolver_faltante_y_responder_actualizacion_permite_cierre_sin_pisar_flete(): void
    {
        $this->guardarSolicitud();
        $this->postJson(route('control_pedidos.municipal.consulta', $this->pedido))->assertOk();
        $this->post(route('control_pedidos.municipal.disponibilidad', $this->pedido), [
            'excepciones' => [['descripcion' => 'Sin SKU', 'cantidad' => 1, 'condicion' => 'faltante']],
            'evidencias' => [UploadedFile::fake()->image('foto.jpg')],
        ])->assertOk();
        $revision = $this->pedido->revisionesProducto()->firstOrFail();
        $this->postJson('/control-pedidos/'.$this->pedido->id.'/disponibilidad-tienda', [
            'revision_id' => $revision->id, 'accion' => 'confirmar', 'solicitud_id' => (string) \Illuminate\Support\Str::uuid(),
        ])->assertUnprocessable();
        $this->postJson('/control-pedidos/'.$this->pedido->id.'/disponibilidad-tienda', [
            'revision_id' => $revision->id, 'accion' => 'retirar', 'solicitud_id' => (string) \Illuminate\Support\Str::uuid(),
        ])->assertOk();
        $this->postJson(route('control_pedidos.cerrar_consulta', $this->pedido))->assertUnprocessable();
        $this->post(route('control_pedidos.municipal.disponibilidad', $this->pedido), [
            'confirmacion_explicita' => 1,
            'actualizacion_id' => data_get($this->pedido->fresh()->solicitud_preparacion_json, 'revision_disponibilidad.actualizacion_id'),
            'evidencias' => [UploadedFile::fake()->image('actualizada.jpg')],
        ])->assertOk();
        $this->postJson(route('control_pedidos.cerrar_consulta', $this->pedido))->assertOk();
        $this->assertNull($this->pedido->fresh()->costo_envio);
        $this->assertSame('pendiente_regularizacion', $this->pedido->fresh()->estatus_envio);
        $this->assertSame(0, $this->pedido->tareasPreparacion()->count());
    }

    private function pagoEtapa(float $monto): \App\Models\SaldosAFavor\PedidoBmaPago
    {
        return app(\App\Services\SaldosAFavor\RegistrarPagoPedidoBmaService::class)->handle($this->pedido->fresh(), [
            'monto' => $monto, 'forma_pago' => 'efectivo', 'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
        ], UploadedFile::fake()->image('pago.jpg'), $this->ventas->id);
    }

    private function pasarAAuxiliar(): void
    {
        $fase = CatalogoEstatusPedido::firstOrCreate(['codigo_interno' => 'M01_AUXILIAR'], [
            'nombre_visual' => 'Auxiliar', 'color_hex' => '#64748B', 'fase_ciclo' => 'PENDIENTE_AUXILIAR', 'orden' => 10, 'activo' => true,
        ]);
        $this->pedido->update(['catalogo_estatus_pedido_id' => $fase->id]);
        $this->pedido->refresh();
    }

    public function test_costo_conocido_insuficiente_no_valida_y_cubierto_si_valida_y_asocia_remision(): void
    {
        $id = $this->guardarSolicitud();
        $this->postJson(route('control_pedidos.municipal.solicitud', $this->pedido), [
            'municipio_destino' => 'Destino', 'catalogo_paqueteria_id' => $this->jaguar->id,
            'consignatario_id' => $id, 'leyenda_terminal' => 'PAGADO', 'costo_transporte' => 'conocido', 'costo_envio' => 30,
        ])->assertOk();
        $this->responderConsulta();
        $this->postJson(route('control_pedidos.cerrar_consulta', $this->pedido))->assertOk();
        $this->pagoEtapa(100);
        $this->pasarAAuxiliar();
        $validar = app(\App\Services\ControlPedidos\ValidarPagoPedidoBmaService::class);
        try { $validar->ejecutar($this->pedido->fresh(), $this->ventas->id); $this->fail('No debe validar un monto insuficiente.'); }
        catch (\InvalidArgumentException $e) { $this->assertStringContainsString('Falta', $e->getMessage()); }
        $this->assertNull($this->pedido->fresh()->pago_validado_at);
        $this->pagoEtapa(30);
        $validar->ejecutar($this->pedido->fresh(), $this->ventas->id);
        $this->assertNotNull($this->pedido->fresh()->pago_validado_at);
        $this->assertSame(2, $this->pedido->pagosExhibicion()->where('estado_revision', 'verificado')->count());
        $remision = app(\App\Services\ControlPedidos\GestionarRemisionPedidoBmaService::class);
        $remision->subir($this->pedido->fresh(), UploadedFile::fake()->create('remision.pdf', 20, 'application/pdf'), $this->ventas->id);
        $remision->actualizarNumeroRemision($this->pedido->fresh(), 'REM-M01', $this->ventas->id);
        $this->assertTrue($this->pedido->fresh()->tieneRemision());
        $this->assertSame('REM-M01', $this->pedido->fresh()->numero_remision);
    }

    public function test_jaguar_pago_validado_y_reporte_no_presentan_transporte_gratis(): void
    {
        $this->guardarSolicitud(); $this->responderConsulta();
        $this->postJson(route('control_pedidos.cerrar_consulta', $this->pedido))->assertOk();
        $this->pagoEtapa(100); $this->pasarAAuxiliar();
        app(\App\Services\ControlPedidos\ValidarPagoPedidoBmaService::class)->ejecutar($this->pedido->fresh(), $this->ventas->id);
        $cierre = \App\Models\Reportes\PedidoBmaCierrePago::where('pedido_bma_id', $this->pedido->id)->firstOrFail();
        $this->assertTrue(data_get($cierre->metadata_snapshot, 'transporte_municipal.desconocido'));
        $this->assertFalse(data_get($cierre->metadata_snapshot, 'transporte_municipal.cubierto'));
        $detalle = app(\App\Services\Reportes\PagosPedidos\ObtenerDetalleReportePagoPedidoService::class)->ejecutar($this->ventas, $cierre);
        $this->assertNull($detalle['financiero']['monto_envio']);
        $this->ventas->givePermissionTo(Permission::findOrCreate('reportes.pagos_pedidos.ver_todos', 'web'));
        $metricas = app(\App\Services\Reportes\PagosPedidos\CalcularMetricasReportePagosPedidosService::class)->ejecutar($this->ventas, []);
        $this->assertSame(1, $metricas['transportes_pendientes_determinar']);
        $this->assertSame('transporte_pendiente', $metricas['saldo_etiqueta']);
        $this->assertNull($this->pedido->fresh()->costo_envio);
        $this->assertSame('pendiente_regularizacion', $this->pedido->fresh()->estatus_envio);
        app(\App\Services\ControlPedidos\GestionarRemisionPedidoBmaService::class)->subir($this->pedido->fresh(), UploadedFile::fake()->create('remision.pdf', 20, 'application/pdf'), $this->ventas->id);
        $this->assertTrue($this->pedido->fresh()->tieneRemision());
    }

    public function test_numero_remision_y_validacion_no_eluden_consulta_abierta(): void
    {
        $this->guardarSolicitud(); $this->pasarAAuxiliar();
        try {
            app(\App\Services\ControlPedidos\GestionarRemisionPedidoBmaService::class)->actualizarNumeroRemision($this->pedido->fresh(), 'NO', $this->ventas->id);
            $this->fail('No debe asociar remisión sin consulta cerrada.');
        } catch (\Illuminate\Validation\ValidationException $e) { $this->assertArrayHasKey('consulta', $e->errors()); }
        try {
            app(\App\Services\ControlPedidos\ValidarPagoPedidoBmaService::class)->ejecutar($this->pedido->fresh(), $this->ventas->id);
            $this->fail('No debe validar sin consulta cerrada.');
        } catch (\Illuminate\Validation\ValidationException $e) { $this->assertArrayHasKey('consulta', $e->errors()); }
        $this->assertNull($this->pedido->fresh()->numero_remision);
    }

    public function test_historial_incluye_bajas_solo_con_permiso_y_no_rutas_privadas(): void
    {
        $id = $this->guardarSolicitud();
        $this->ventas->givePermissionTo(Permission::findOrCreate('clientes.expediente.eliminar', 'web'));
        $servicio = app(\App\Services\Clientes\Expediente\GestionarExpedienteClienteService::class);
        $consignatario = \App\Models\ClienteConsignatario::findOrFail($id);
        $doc = $servicio->guardarDocumento($consignatario, $this->ventas, UploadedFile::fake()->create('ine.pdf', 20, 'application/pdf'), $this->pedido);
        $servicio->eliminarDocumento($doc, $this->ventas, $this->pedido);
        $servicio->eliminarConsignatario($consignatario, $this->ventas, $this->pedido);
        $this->assertSame([], $servicio->listar($this->cliente, $this->ventas, true, $this->pedido));
        $this->ventas->givePermissionTo(Permission::findOrCreate('clientes.expediente.ver_historico', 'web'));
        $historial = $servicio->listar($this->cliente, $this->ventas, true, $this->pedido);
        $this->assertCount(1, $historial);
        $this->assertFalse($historial[0]['activo']);
        $this->assertSame($doc->id, $historial[0]['documentos_historicos'][0]['id']);
        $this->assertArrayNotHasKey('ruta', $historial[0]['documentos_historicos'][0]);
    }

    public function test_guardar_mismo_consignatario_conserva_snapshot_tras_editar_expediente(): void
    {
        $id = $this->guardarSolicitud();
        $snapshot = $this->pedido->fresh()->solicitud_preparacion_json['expediente'];
        $this->putJson(route('control_pedidos.municipal.expediente.update', [$this->cliente, $id]), [
            'nombre' => 'Nombre corregido', 'telefono' => '3322222222', 'pedido_id' => $this->pedido->id,
        ])->assertOk();
        $this->postJson(route('control_pedidos.municipal.solicitud', $this->pedido), [
            'municipio_destino' => 'Destino', 'catalogo_paqueteria_id' => $this->jaguar->id,
            'consignatario_id' => $id, 'leyenda_terminal' => 'PAGADO', 'costo_transporte' => 'conocido', 'costo_envio' => 30,
        ])->assertOk();
        $this->assertSame($snapshot, $this->pedido->fresh()->solicitud_preparacion_json['expediente']);
        $this->assertSame('130.00', $this->pedido->fresh()->total_a_cobrar);
    }

    public function test_reapertura_invalida_ambos_cierres_y_bloquea_pago_hasta_nueva_respuesta(): void
    {
        $this->guardarSolicitud(); $this->responderConsulta();
        $this->postJson(route('control_pedidos.cerrar_consulta', $this->pedido))->assertOk();
        app(\App\Services\ControlPedidos\ReabrirConsultaPedidoBmaService::class)->ejecutar($this->pedido->fresh(), $this->ventas->id);
        $this->pedido->refresh();
        $this->assertNull($this->pedido->consulta_cerrada_at);
        $this->assertSame('excepciones_pendientes', \App\Support\ControlPedidos\ConsultaMunicipal::estado($this->pedido));
        $this->assertSame('excepciones_pendientes', data_get($this->pedido->solicitud_preparacion_json, 'consulta_municipal.estado'));
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->pagoEtapa(100);
    }

    public function test_contexto_inexistente_no_falla_abierto_y_documento_de_otro_cliente_no_se_descarga(): void
    {
        $id = $this->guardarSolicitud();
        $this->getJson(route('control_pedidos.municipal.expediente.index', $this->cliente).'?pedido_id=999999')->assertNotFound();
        $documento = $this->postJson(route('control_pedidos.municipal.expediente.documento', [$this->cliente, $id]), [
            'archivo' => UploadedFile::fake()->create('ine.pdf', 20, 'application/pdf'), 'pedido_id' => $this->pedido->id,
        ])->assertCreated()->json('id');
        $otro = $this->cliente->replicate(); $otro->numero_cliente = 'M01-OTRO'; $otro->save();
        $this->get(route('control_pedidos.municipal.expediente.descargar', [$otro, $documento]).'?pedido_id='.$this->pedido->id)->assertNotFound();
        $this->ventas->revokePermissionTo('clientes.expediente.ver_identificacion');
        $this->get(route('control_pedidos.municipal.expediente.descargar', [$this->cliente, $documento]).'?pedido_id='.$this->pedido->id)->assertForbidden();
    }

    public function test_endpoints_auxiliar_denegan_sin_permiso_y_consulta_abierta_con_permiso(): void
    {
        $this->guardarSolicitud(); $this->pasarAAuxiliar();
        $this->postJson(route('control_pedidos.auditar.validar_pago', $this->pedido))->assertForbidden();
        $this->ventas->givePermissionTo(Permission::findOrCreate('control_pedidos.auditar', 'web'));
        $this->postJson(route('control_pedidos.auditar.validar_pago', $this->pedido))->assertUnprocessable();
        $this->putJson(route('control_pedidos.auditar.numero_remision.update', $this->pedido), ['numero_remision' => 'NO'])->assertUnprocessable();
        $this->assertNull($this->pedido->fresh()->pago_validado_at);
        $this->assertNull($this->pedido->fresh()->numero_remision);
    }

    public function test_disponibilidad_tienda_responde_a_ventas_y_niega_a_quien_no_tiene_alcance(): void
    {
        $this->guardarSolicitud();
        $this->postJson(route('control_pedidos.municipal.consulta', $this->pedido))->assertOk();
        $lectura = $this->actingAs($this->ventas)->getJson('/control-pedidos/'.$this->pedido->id.'/disponibilidad-tienda')
            ->assertOk()
            ->assertJsonPath('solicitud_preparacion.modalidad_entrega', 'municipal')
            ->assertJsonPath('solicitud_preparacion.consulta_municipal.estado', 'pendiente')
            ->json();
        $this->assertSame('Consulta pendiente: CEDIS', $lectura['etiqueta_consulta'] ?? null);
        $this->assertArrayHasKey('consulta_municipal', $lectura['solicitud_preparacion']);
        $this->assertArrayHasKey('version_consulta', $lectura);
        $this->assertSame('pendiente', $lectura['solicitud_preparacion']['consulta_municipal']['estado']);
        $rutas = json_encode($lectura);
        $this->assertStringNotContainsString('storage/app/private', $rutas);
        $ajeno = User::factory()->create();
        $ajeno->givePermissionTo(Permission::findOrCreate('control_pedidos.crear', 'web'));
        $this->actingAs($ajeno)->getJson('/control-pedidos/'.$this->pedido->id.'/disponibilidad-tienda')->assertForbidden();
    }

    public function test_lecturas_ordenan_dos_respuestas_y_reapertura_en_el_mismo_segundo_sin_borrar_documentos(): void
    {
        $this->freezeTime();
        $this->guardarSolicitud();
        $this->responderConsulta();
        $url = '/control-pedidos/'.$this->pedido->id.'/disponibilidad-tienda';
        $primera = $this->getJson($url)->assertOk()->json();
        $this->assertTrue($primera['puede_cerrar_consulta']);
        $this->postJson(route('control_pedidos.cerrar_consulta', $this->pedido))->assertOk();
        $this->postJson(route('control_pedidos.reabrir_consulta', $this->pedido))->assertOk();
        $reabierta = $this->getJson($url)->assertOk()->json();
        $this->assertFalse($reabierta['puede_cerrar_consulta']);
        $this->assertGreaterThan($primera['version_consulta'], $reabierta['version_consulta']);
        $this->assertSame($primera['solicitud_preparacion']['revision_disponibilidad']['respondida_at'], $reabierta['solicitud_preparacion']['revision_disponibilidad']['respondida_at']);
        $this->assertTrue($reabierta['solicitud_preparacion']['revision_disponibilidad']['actualizacion_pendiente']);
        $this->post(route('control_pedidos.cedis.disponibilidad', $this->pedido), [
            'confirmacion_explicita' => 1,
            'actualizacion_id' => $reabierta['solicitud_preparacion']['revision_disponibilidad']['actualizacion_id'],
            'evidencias' => [UploadedFile::fake()->image('segunda.jpg')],
        ])->assertOk();
        $segunda = $this->getJson($url)->assertOk()->json();
        $this->assertTrue($segunda['puede_cerrar_consulta']);
        $this->assertGreaterThan($reabierta['version_consulta'], $segunda['version_consulta']);
        $this->assertNotSame($primera['solicitud_preparacion']['revision_disponibilidad']['respuesta_id'], $segunda['solicitud_preparacion']['revision_disponibilidad']['respuesta_id']);
        $this->assertSame($primera['solicitud_preparacion']['revision_disponibilidad']['respondida_at'], $segunda['solicitud_preparacion']['revision_disponibilidad']['respondida_at']);
        $this->assertNotEmpty($segunda['solicitud_preparacion']['revision_disponibilidad']['historial_respuestas']);
        $this->assertGreaterThan(count($primera['documentos']), count($segunda['documentos']));
        $this->assertNull($this->pedido->fresh()->pago_validado_at);
        $this->assertNull($this->pedido->fresh()->numero_remision);
        $this->assertSame(0, $this->pedido->tareasPreparacion()->count());
    }

    public function test_listado_y_lectura_canonica_proyectan_el_almacen_consultado_sin_mover_el_pedido(): void
    {
        $this->guardarSolicitud();
        $this->postJson(route('control_pedidos.municipal.consulta', $this->pedido))->assertOk();
        $almacen = \App\Models\Almacen::create(['codigo' => 'QA-ORIGEN-REAL', 'nombre' => 'Origen real de consulta', 'activo' => true, 'visible_en_pedidos' => true]);
        $otro = \App\Models\Almacen::create(['codigo' => 'QA-OTRO', 'nombre' => 'Otro almacén', 'activo' => true, 'visible_en_pedidos' => true]);
        $json = $this->pedido->fresh()->solicitud_preparacion_json;
        $json['consulta_municipal']['almacen_id'] = $almacen->id;
        $this->pedido->update(['solicitud_preparacion_json' => $json, 'almacen_id' => $otro->id]);
        $this->getJson('/control-pedidos/'.$this->pedido->id.'/disponibilidad-tienda')->assertOk()->assertJsonPath('almacen.nombre', $almacen->nombre)->assertJsonPath('paqueteria.nombre', $this->jaguar->nombre);
        $this->getJson(route('control_pedidos.cedis.listado', ['q' => $this->pedido->folio]))->assertOk()->assertJsonPath('pedidos.data.0.almacen.nombre', $almacen->nombre);
        $this->assertSame($otro->id, $this->pedido->fresh()->almacen_id);
        $this->assertNull($this->pedido->fresh()->sucursal_destino_id);
        $this->assertSame(0, $this->pedido->tareasPreparacion()->count());
    }

    public function test_etiqueta_de_excepciones_y_cierre_bloqueado_si_la_revision_no_sigue_vigente(): void
    {
        $this->guardarSolicitud();
        $this->postJson(route('control_pedidos.municipal.consulta', $this->pedido))->assertOk();
        $this->post(route('control_pedidos.municipal.disponibilidad', $this->pedido), [
            'excepciones' => [['descripcion' => 'Pieza ficticia', 'cantidad' => 1, 'condicion' => 'faltante']],
            'evidencias' => [UploadedFile::fake()->image('foto.jpg')],
        ])->assertOk();
        $pedido = $this->pedido->fresh();
        $this->assertSame('Excepciones pendientes', \App\Services\ControlPedidos\Municipal\EtiquetaConsultaMunicipal::de($pedido));
        $this->assertSame('Hay piezas sin existencias: retírelas o solicite su sustitución antes de cerrar la consulta.', \App\Support\ControlPedidos\ConsultaMunicipal::motivoBloqueo($pedido));
        $this->postJson(route('control_pedidos.cerrar_consulta', $pedido))->assertUnprocessable();
        $json = $pedido->solicitud_preparacion_json;
        $json['revision_disponibilidad']['aceptacion_vigente'] = false;
        $json['revision_disponibilidad']['respondida_at'] = now()->subMinute()->toIso8601String();
        $pedido->update(['solicitud_preparacion_json' => $json]);
        $lectura = $this->getJson('/control-pedidos/'.$pedido->id.'/disponibilidad-tienda')->assertOk()->json();
        $this->assertSame('Excepciones pendientes', $lectura['etiqueta_consulta']);
        $this->assertNotEmpty($lectura['motivo_bloqueo_cierre']);
        $primera = data_get($lectura, 'solicitud_preparacion.revision_disponibilidad.respondida_at');
        $json['revision_disponibilidad']['respondida_at'] = now()->toIso8601String();
        $json['revision_disponibilidad']['respuesta_id'] = (string) \Illuminate\Support\Str::uuid();
        $pedido->update(['solicitud_preparacion_json' => $json]);
        $segunda = $this->getJson('/control-pedidos/'.$pedido->id.'/disponibilidad-tienda')->assertOk()->json();
        $this->assertNotEquals($primera, data_get($segunda, 'solicitud_preparacion.revision_disponibilidad.respondida_at'));
        $this->assertTrue(strtotime(data_get($segunda, 'solicitud_preparacion.revision_disponibilidad.respondida_at')) >= strtotime($primera));
    }

    public function test_envio_al_auxiliar_usa_consulta_municipal_sin_exigir_pesaje_nacional(): void
    {
        $this->guardarSolicitud(); $this->responderConsulta();
        $this->postJson(route('control_pedidos.cerrar_consulta', $this->pedido))->assertOk();
        $this->pagoEtapa(100);
        CatalogoEstatusPedido::firstOrCreate(['codigo_interno' => 'M01_AUXILIAR'], ['nombre_visual' => 'Auxiliar', 'color_hex' => '#64748B', 'fase_ciclo' => 'PENDIENTE_AUXILIAR', 'orden' => 10, 'activo' => true]);
        $origen = \App\Models\ControlPedidos\CatalogoOrigenPedido::create(['nombre' => 'Envío municipal', 'requiere_logistica' => true, 'activo' => true]);
        $almacen = \App\Models\Almacen::create(['codigo' => 'M01-CEDIS', 'nombre' => 'CEDIS', 'activo' => true, 'visible_en_pedidos' => true]);
        $this->pedido->update(['origen_id' => $origen->id, 'almacen_id' => $almacen->id, 'folio_remision' => 'WIZ-M01']);
        $this->pedido->documentos()->create(['tipo' => 'pdf_pedido', 'ruta_archivo' => 'pedido.pdf', 'nombre_original' => 'pedido.pdf', 'mime_type' => 'application/pdf', 'tamano_bytes' => 20, 'orden' => 1, 'activo' => true]);
        $this->putJson(route('control_pedidos.enviar', $this->pedido))->assertOk()->assertJsonPath('estatus.fase_ciclo', 'PENDIENTE_AUXILIAR');
        $this->assertNull($this->pedido->fresh()->costo_envio);
        $this->assertSame('pendiente_regularizacion', $this->pedido->fresh()->estatus_envio);
    }

}
