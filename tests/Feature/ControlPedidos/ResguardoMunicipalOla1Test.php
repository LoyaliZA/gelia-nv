<?php

namespace Tests\Feature\ControlPedidos;

use App\Models\Almacen;
use App\Models\CatalogoListaDescuento;
use App\Models\Cliente;
use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\CatalogoModalidadPreparacionPedido;
use App\Models\ControlPedidos\CatalogoOrigenPedido;
use App\Models\ControlPedidos\CatalogoPaqueteriaPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaEnvioResguardo;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\SaldosAFavor\PedidoBmaPago;
use App\Models\User;
use App\Services\ControlPedidos\FusionarSolicitudPreparacionPedido;
use App\Services\ControlPedidos\LiberarTareaPreparacionService;
use App\Services\ControlPedidos\Municipal\LiberarResguardoMunicipalService;
use App\Services\ControlPedidos\NotificarPedidoBmaService;
use App\Services\ControlPedidos\ResguardosEnvioPedido;
use App\Services\SaldosAFavor\CoberturaPagoPedidoBmaService;
use App\Services\SaldosAFavor\RegistrarPagoPedidoBmaService;
use App\Support\ControlPedidos\ExclusividadRemisionEnvio;
use App\Support\ControlPedidos\PoliticaResguardoEnvio;
use App\Support\ControlPedidos\RevisionCapturaPedido;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ResguardoMunicipalOla1Test extends TestCase
{
    use RefreshDatabase;

    private User $ventas;

    private User $auxiliar;

    private Cliente $cliente;

    private PedidoBma $pedido;

    private CatalogoPaqueteriaPedido $jaguar;

    public function createApplication()
    {
        $app = parent::createApplication();
        $driver = $app['config']->get('database.default');
        $db = $app['config']->get("database.connections.{$driver}.database");
        if (! (($driver === 'sqlite' && $db === ':memory:') || ($driver === 'mysql' && str_starts_with((string) $db, 'codex_')))) {
            throw new \RuntimeException('Auditoría bloqueada fuera de SQLite en memoria o MySQL exclusivo.');
        }

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([ValidateCsrfToken::class, PreventRequestForgery::class]);
        Storage::fake('local');
        Storage::fake('public');
        $this->mock(NotificarPedidoBmaService::class)->shouldReceive('ejecutar')->zeroOrMoreTimes();

        $this->ventas = User::factory()->create();
        $this->auxiliar = User::factory()->create();
        foreach ([
            'control_pedidos.crear',
            'clientes.expediente.ver',
            'clientes.expediente.editar',
            'clientes.expediente.documentos',
            'control_pedidos.cedis',
            'control_pedidos.cedis.ver_todos',
        ] as $permiso) {
            $this->ventas->givePermissionTo(Permission::findOrCreate($permiso, 'web'));
        }
        foreach (['control_pedidos.auditar', 'control_pedidos.liberar_resguardo', 'control_pedidos.tienda.liberar'] as $permiso) {
            $this->auxiliar->givePermissionTo(Permission::findOrCreate($permiso, 'web'));
        }

        foreach (['BORRADOR', 'PESAJE_PENDIENTE', 'PESAJE_RESPONDIDO', 'PENDIENTE_AUXILIAR', 'EN_CEDIS'] as $orden => $fase) {
            CatalogoEstatusPedido::query()->create([
                'codigo_interno' => 'RG_'.$fase,
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
            'numero_cliente' => 'RG-001',
            'nombre' => 'Cliente resguardo',
            'lista_actual_id' => $lista->id,
            'vendedor_id' => $this->ventas->id,
            'monto_venta_actual' => 0,
        ]);
        $estatus = CatalogoEstatusPedido::porFase('BORRADOR');
        $this->pedido = PedidoBma::query()->create([
            'folio' => 'RG-001',
            'fecha' => now()->toDateString(),
            'vendedor_id' => $this->ventas->id,
            'cliente_id' => $this->cliente->id,
            'catalogo_estatus_pedido_id' => $estatus->id,
            'total_mercancia' => 100,
            'solicitud_preparacion_json' => ['modalidad_entrega' => 'municipal'],
        ]);
        $departamento = \App\Models\Departamento::create(['nombre' => 'Departamento ficticio']);
        $this->ventas->update(['departamento_id' => $departamento->id]);
        $this->auxiliar->update(['departamento_id' => $departamento->id]);
        $this->pedido->update(['departamento_operacion_id' => $departamento->id, 'folio_remision' => 'WIZ-RG']);
        Storage::disk('public')->put('qa/pedido.png', 'soporte ficticio');
        $this->pedido->documentos()->create([
            'tipo' => 'pdf_pedido', 'ruta_archivo' => 'qa/pedido.png',
            'nombre_original' => 'pedido.png', 'mime_type' => 'image/png', 'tamano_bytes' => 16, 'orden' => 0,
        ]);
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
        $this->actingAs($this->ventas);
    }

    public function test_activar_sin_pago_registra_intencion_y_bloquea_auditoria(): void
    {
        $this->cerrarConsultaConocida(30);
        $this->postJson(route('control_pedidos.municipal.resguardo', $this->pedido), $this->capturaResguardo())->assertOk();
        $this->assertTrue(PoliticaResguardoEnvio::intencionActiva($this->pedido->fresh()));
        $this->assertTrue((bool) $this->pedido->fresh()->es_resguardo);
        $origen = CatalogoOrigenPedido::query()->create(['nombre' => 'Envío municipal', 'requiere_logistica' => true, 'activo' => true]);
        $almacen = Almacen::create(['codigo' => 'RG-SINPAGO', 'nombre' => 'CEDIS', 'activo' => true, 'visible_en_pedidos' => true]);
        $this->pedido->update(['origen_id' => $origen->id, 'almacen_id' => $almacen->id]);
        $this->putJson(route('control_pedidos.enviar', $this->pedido))
            ->assertStatus(422)
            ->assertJsonFragment(['message' => PoliticaResguardoEnvio::MENSAJE_SIN_PAGO]);
        $this->assertNull($this->pedido->fresh()->pago_validado_at);
    }

    public function test_m10_valida_mercancia_pagada_con_transporte_conocido_pendiente(): void
    {
        $this->cerrarConsultaConocida(30);
        $this->pagoEtapa(100);
        $this->postJson(route('control_pedidos.municipal.resguardo', $this->pedido), $this->capturaResguardo())->assertOk();
        $pedido = $this->pedido->fresh();
        $this->assertTrue($pedido->es_resguardo);
        $this->assertTrue(PoliticaResguardoEnvio::intencionActiva($pedido));
        $this->pasarAAuxiliar();
        app(\App\Services\ControlPedidos\ValidarPagoPedidoBmaService::class)->ejecutar($pedido->fresh(), $this->auxiliar->id);
        $fresco = $this->pedido->fresh();
        $this->assertNotNull($fresco->pago_validado_at);
        $eval = app(\App\Services\ControlPedidos\Municipal\PoliticaFinancieraMunicipal::class)->evaluar($fresco);
        $this->assertTrue($eval['mercancia_cubierta']);
        $this->assertFalse($eval['transporte_cubierto']);
        $resumen = app(CoberturaPagoPedidoBmaService::class)->calcular($fresco);
        $this->assertGreaterThan(0, (float) $resumen['pendiente']);
        $this->assertFalse($resumen['cubierto']);
        $this->assertSame([], $resumen['bloqueos']);
    }

    public function test_ordinario_municipal_sigue_exigiendo_transporte_conocido(): void
    {
        $this->cerrarConsultaConocida(30);
        $this->pagoEtapa(100);
        $this->pasarAAuxiliar();
        try {
            app(\App\Services\ControlPedidos\ValidarPagoPedidoBmaService::class)->ejecutar($this->pedido->fresh(), $this->auxiliar->id);
            $this->fail('Sin resguardo M-10 el transporte conocido debe exigirse.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('Falta', $e->getMessage());
        }
        $this->assertNull($this->pedido->fresh()->pago_validado_at);
    }

    public function test_comprobante_rechazado_sigue_bloqueando_en_m10(): void
    {
        $this->cerrarConsultaConocida(30);
        $pago = $this->pagoEtapa(100);
        $pago->update([
            'estado_revision' => PedidoBmaPago::REVISION_RECHAZADO,
            'rechazado_at' => now(),
        ]);
        $this->postJson(route('control_pedidos.municipal.resguardo', $this->pedido), $this->capturaResguardo())->assertOk();
        $this->pasarAAuxiliar();
        try {
            app(RegistrarPagoPedidoBmaService::class)->assertPagoListoParaAvanzar(
                $this->pedido->fresh(),
                RegistrarPagoPedidoBmaService::FASE_VALIDAR
            );
            $this->fail('El comprobante rechazado debe bloquear.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('rechazados', $e->getMessage());
        }
    }

    public function test_credito_wizerp_no_habilita_el_resguardo(): void
    {
        $this->cerrarConsultaConocida(30);
        $this->pagoEtapa(100);
        $json = $this->pedido->fresh()->solicitud_preparacion_json;
        $json['credito_wizerp'] = ['resultado' => 'aprobado'];
        $this->pedido->update(['solicitud_preparacion_json' => $json]);
        $this->postJson(route('control_pedidos.municipal.resguardo', $this->pedido), $this->capturaResguardo($this->pedido->fresh()))
            ->assertUnprocessable();
        $this->assertStringContainsString(
            'crédito',
            mb_strtolower($this->postJson(route('control_pedidos.municipal.resguardo', $this->pedido), $this->capturaResguardo($this->pedido->fresh()))->json('errors.resguardo.0') ?? '')
        );
    }

    public function test_enviar_m10_cubre_mercancia_y_conserva_excedente_y_saf(): void
    {
        $this->cerrarConsultaConocida(30);
        $this->pagoEtapa(100);
        $this->postJson(route('control_pedidos.municipal.resguardo', $this->pedido), $this->capturaResguardo())->assertOk();
        $origen = CatalogoOrigenPedido::query()->create(['nombre' => 'Envío municipal', 'requiere_logistica' => true, 'activo' => true]);
        $almacen = Almacen::create(['codigo' => 'RG-CEDIS', 'nombre' => 'CEDIS', 'activo' => true, 'visible_en_pedidos' => true]);
        $this->pedido->update([
            'origen_id' => $origen->id,
            'almacen_id' => $almacen->id,
        ]);
        $this->putJson(route('control_pedidos.enviar', $this->pedido))->assertOk()->assertJsonPath('estatus.fase_ciclo', 'PENDIENTE_AUXILIAR');
        $this->assertNull(app(RegistrarPagoPedidoBmaService::class)->generarExcedenteSiAplica($this->pedido->fresh(), $this->ventas->id));
    }

    public function test_aprobar_sigue_exigiendo_transporte_conocido_en_m10(): void
    {
        $this->cerrarConsultaConocida(30);
        $this->pagoEtapa(100);
        $this->postJson(route('control_pedidos.municipal.resguardo', $this->pedido), $this->capturaResguardo())->assertOk();
        try {
            app(RegistrarPagoPedidoBmaService::class)->assertPagoListoParaAvanzar(
                $this->pedido->fresh(),
                RegistrarPagoPedidoBmaService::FASE_APROBAR
            );
            $this->fail('Aprobar no debe tratar el flete impago como cubierto.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('Falta', $e->getMessage());
        }
    }

    public function test_exclusividad_t05_bloquea_anexo_y_pivot_bloquea_otro_principal(): void
    {
        $this->cerrarConsultaConocida(0);
        $anexo = $this->pedidoHermano('RG-ANX', ['es_resguardo' => true, 'numero_remision' => 'REM-ANX', 'total_mercancia' => 80]);
        $otro = $this->pedidoHermano('RG-OTRO', ['total_mercancia' => 10]);
        $anexo->update(['pedido_principal_id' => $otro->id]);
        try {
            ExclusividadRemisionEnvio::assertDisponible($anexo->fresh(), $this->pedido->fresh());
            $this->fail('T-05 debe ocupar la remisión.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('otro envío', $e->getMessage());
        }

        $anexo->update(['pedido_principal_id' => null]);
        PedidoBmaEnvioResguardo::query()->create([
            'pedido_envio_id' => $this->pedido->id,
            'pedido_resguardo_id' => $anexo->id,
        ]);
        try {
            ExclusividadRemisionEnvio::assertDisponible($anexo->fresh(['envioQueLoAnexa']), $otro->fresh());
            $this->fail('El pivote debe ocupar la remisión.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('anexada', $e->getMessage());
        }

        try {
            app(ResguardosEnvioPedido::class)->aplicar($otro, ['resguardo_ids' => [$anexo->id]], $this->ventas->id);
            $this->fail('aplicar no debe duplicar el vínculo.');
        } catch (\InvalidArgumentException $e) {
            $this->assertNotEmpty($e->getMessage());
        }
        $this->assertSame(1, PedidoBmaEnvioResguardo::query()->where('pedido_resguardo_id', $anexo->id)->count());
    }

    public function test_liberacion_solo_remisiones_previas_conserva_origenes_y_bloquea_despacho_autonomo(): void
    {
        $this->cerrarConsultaConocida(30);
        $this->pedido->update(['total_mercancia' => 0, 'envio_solo_resguardos' => true]);
        $a = $this->pedidoHermano('RG-A', ['es_resguardo' => true, 'numero_remision' => 'REM-A', 'total_mercancia' => 40, 'pago_validado_at' => now()]);
        $b = $this->pedidoHermano('RG-B', ['es_resguardo' => true, 'numero_remision' => 'REM-B', 'total_mercancia' => 60, 'pago_validado_at' => now()]);
        PedidoBmaEnvioResguardo::query()->create(['pedido_envio_id' => $this->pedido->id, 'pedido_resguardo_id' => $a->id]);
        PedidoBmaEnvioResguardo::query()->create(['pedido_envio_id' => $this->pedido->id, 'pedido_resguardo_id' => $b->id]);
        $this->postJson(route('control_pedidos.municipal.resguardo', $this->pedido), $this->capturaResguardo($this->pedido->fresh()))->assertOk();
        $this->cerrarAcumulacionYContinuacion();
        $principal = app(LiberarResguardoMunicipalService::class)->ejecutar($this->pedido->fresh(), $this->auxiliar->id);

        $this->assertFalse(PoliticaResguardoEnvio::intencionActiva($principal));
        $this->assertFalse((bool) $principal->es_resguardo);
        $this->assertSame(2, $principal->resguardosAnexados()->count());
        $this->assertSame('REM-A', $a->fresh()->numero_remision);
        $this->assertSame(40.0, (float) $a->fresh()->total_mercancia);
        $this->assertNull($a->fresh()->pedido_principal_id);
        $this->expectException(\RuntimeException::class);
        PoliticaResguardoEnvio::assertPuedeDespachar($a->fresh());
    }

    public function test_liberacion_mixta_no_duplica_cobros_ni_suma_remisiones(): void
    {
        $this->cerrarConsultaConocida(30);
        $this->pagoEtapa(100);
        $anexo = $this->pedidoHermano('RG-MIX', [
            'es_resguardo' => true,
            'numero_remision' => 'REM-MIX',
            'total_mercancia' => 250,
            'pago_validado_at' => now(),
        ]);
        PedidoBmaEnvioResguardo::query()->create(['pedido_envio_id' => $this->pedido->id, 'pedido_resguardo_id' => $anexo->id]);
        $this->postJson(route('control_pedidos.municipal.resguardo', $this->pedido), $this->capturaResguardo())->assertOk();
        $this->cerrarAcumulacionYContinuacion();
        $principal = app(LiberarResguardoMunicipalService::class)->ejecutar($this->pedido->fresh(), $this->auxiliar->id);

        $eval = app(\App\Services\ControlPedidos\Municipal\PoliticaFinancieraMunicipal::class)->evaluar($principal);
        $this->assertTrue($eval['mercancia_cubierta']);
        $this->assertSame(100.0, (float) $principal->total_mercancia);
        $this->assertSame(250.0, (float) $anexo->fresh()->total_mercancia);
        $this->assertSame(1, $principal->pagosExhibicion()->count());
        $this->assertSame(0, $anexo->fresh()->pagosExhibicion()->count());
        $this->assertTrue(PoliticaResguardoEnvio::esMiembroConjunto($anexo->fresh()));
        $this->assertFalse(PoliticaResguardoEnvio::esMiembroConjunto($principal->fresh()));
    }

    public function test_liberacion_municipal_no_limpia_espera_de_pago(): void
    {
        $this->cerrarConsultaConocida(30);
        $this->pagoEtapa(100);
        $this->postJson(route('control_pedidos.municipal.resguardo', $this->pedido), $this->capturaResguardo())->assertOk();
        $this->pedido->update(['esperando_pago_at' => now(), 'es_resguardo' => true]);
        $this->cerrarAcumulacionYContinuacion();
        $fresco = app(LiberarResguardoMunicipalService::class)->ejecutar($this->pedido->fresh(), $this->auxiliar->id);
        $this->assertFalse(PoliticaResguardoEnvio::intencionActiva($fresco));
        $this->assertNotNull($fresco->esperando_pago_at);
        $this->assertTrue((bool) $fresco->es_resguardo);
        $this->assertContains('espera_pago', PoliticaResguardoEnvio::otrasRetencionesEsResguardo($fresco));
        $this->assertFalse(PoliticaResguardoEnvio::puedeLimpiarFlagCompartido($fresco));
    }

    public function test_liberar_tarea_no_borra_intencion_municipal(): void
    {
        $this->cerrarConsultaConocida(30);
        $this->pagoEtapa(100);
        $this->postJson(route('control_pedidos.municipal.resguardo', $this->pedido), $this->capturaResguardo())->assertOk();
        $almacen = Almacen::create(['codigo' => 'RG-PDV', 'nombre' => 'PDV', 'activo' => true, 'visible_en_pedidos' => true]);
        $modalidad = CatalogoModalidadPreparacionPedido::query()->firstOrCreate(
            ['codigo' => CatalogoModalidadPreparacionPedido::CODIGO_ENVIO_MUNICIPIO],
            ['nombre' => 'Envío a municipio', 'activo' => true]
        );
        $tarea = PedidoBmaTareaPreparacion::query()->create([
            'pedido_bma_id' => $this->pedido->id,
            'catalogo_modalidad_preparacion_id' => $modalidad->id,
            'almacen_id' => $almacen->id,
            'estado' => PedidoBmaTareaPreparacion::ESTADO_RESPONDIDA,
            'area_responsable_codigo' => 'TIENDA',
            'version' => 1,
            'solicitada_por_id' => $this->ventas->id,
            'solicitada_at' => now(),
            'fecha_limite' => now()->addDay(),
        ]);
        app(LiberarTareaPreparacionService::class)->ejecutar($tarea, $this->auxiliar);
        $fresco = $this->pedido->fresh();
        $this->assertTrue(PoliticaResguardoEnvio::intencionActiva($fresco));
        $this->assertTrue((bool) $fresco->es_resguardo);
    }

    public function test_fusionar_conserva_resguardo_y_autoguardado_no_lo_falsifica(): void
    {
        $this->cerrarConsultaConocida(30);
        $this->pagoEtapa(100);
        $this->postJson(route('control_pedidos.municipal.resguardo', $this->pedido), $this->capturaResguardo())->assertOk();
        $previa = $this->pedido->fresh()->solicitud_preparacion_json['resguardo_envio'];
        $fusion = app(FusionarSolicitudPreparacionPedido::class)->ejecutar(
            $this->pedido->fresh()->solicitud_preparacion_json,
            ['modalidad_entrega' => 'municipal', 'resguardo_envio' => ['activo' => false, 'motivo' => 'intruso']]
        );
        $this->assertTrue($fusion['resguardo_envio']['activo']);
        $this->assertSame('acumulacion', $fusion['resguardo_envio']['motivo']);
        $this->assertSame($previa['activo'], $this->pedido->fresh()->solicitud_preparacion_json['resguardo_envio']['activo']);
    }

    public function test_empaque_bloqueado_con_intencion_y_ventas_no_libera(): void
    {
        $this->cerrarConsultaConocida(30);
        $this->pagoEtapa(100);
        $this->postJson(route('control_pedidos.municipal.resguardo', $this->pedido), $this->capturaResguardo())->assertOk();
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(PoliticaResguardoEnvio::MENSAJE_BLOQUEO_DESPACHO);
        PoliticaResguardoEnvio::assertPuedeDespachar($this->pedido->fresh());
    }

    public function test_ventas_no_libera_el_resguardo_municipal(): void
    {
        $this->cerrarConsultaConocida(30);
        $this->pagoEtapa(100);
        $this->postJson(route('control_pedidos.municipal.resguardo', $this->pedido), $this->capturaResguardo())->assertOk();
        $this->cerrarAcumulacionYContinuacion();
        $this->ventas->givePermissionTo(Permission::findOrCreate('control_pedidos.liberar_resguardo', 'web'));
        $this->ventas->givePermissionTo(Permission::findOrCreate('control_pedidos.auditar', 'web'));
        $this->actingAs($this->ventas);
        $this->post(route('control_pedidos.auditar.liberar_resguardo', $this->pedido))
            ->assertRedirect();
        $this->assertTrue(PoliticaResguardoEnvio::intencionActiva($this->pedido->fresh()));
    }

    private function cerrarConsultaConocida(float $costo): void
    {
        $id = $this->postJson(route('control_pedidos.municipal.expediente.store', $this->cliente), [
            'nombre' => 'Persona ficticia', 'telefono' => '3311111111', 'pedido_id' => $this->pedido->id,
        ])->assertCreated()->json('id');
        $payload = [
            'catalogo_paqueteria_id' => $this->jaguar->id,
            'consignatario_id' => $id,
            'leyenda_terminal' => 'PAGADO',
            'costo_transporte' => $costo > 0 ? 'conocido' : 'diferido',
        ];
        if ($costo > 0) {
            $payload['costo_envio'] = $costo;
        }
        $this->postJson(route('control_pedidos.municipal.solicitud', $this->pedido), $payload)->assertOk();
        $this->postJson(route('control_pedidos.municipal.consulta', $this->pedido))->assertOk();
        $this->post(route('control_pedidos.municipal.disponibilidad', $this->pedido), [
            'confirmacion_explicita' => 1, 'evidencias' => [UploadedFile::fake()->image('conjunto.jpg')],
        ])->assertOk();
        $this->postJson(route('control_pedidos.cerrar_consulta', $this->pedido))->assertOk();
    }

    /** @return array<string, mixed> */
    private function cerrarAcumulacionYContinuacion(): void
    {
        $this->actingAs($this->ventas);
        $this->postJson(route('control_pedidos.municipal.resguardo.acumulacion', $this->pedido), $this->capturaContinuacion())->assertOk();
        $this->pasarAAuxiliar();
        $this->postJson(route('control_pedidos.municipal.resguardo.continuacion', $this->pedido), $this->capturaContinuacion())->assertOk();
    }

    /** @return array<string, mixed> */
    private function capturaContinuacion(?PedidoBma $pedido = null): array
    {
        $pedido = $pedido ?: $this->pedido->fresh();

        return [
            'revision_esperada' => (int) $pedido->revision_registro,
            'base_captura' => RevisionCapturaPedido::base($pedido),
        ];
    }

    /** @return array<string, mixed> */
    private function capturaResguardo(?PedidoBma $pedido = null): array
    {
        $pedido = $pedido ?: $this->pedido->fresh();

        return [
            'activo' => true,
            'motivo' => 'acumulacion',
            'revision_esperada' => (int) $pedido->revision_registro,
            'base_captura' => RevisionCapturaPedido::base($pedido),
        ];
    }

    private function pagoEtapa(float $monto): PedidoBmaPago
    {
        return app(RegistrarPagoPedidoBmaService::class)->handle($this->pedido->fresh(), [
            'monto' => $monto, 'forma_pago' => 'efectivo', 'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
        ], UploadedFile::fake()->image('pago.jpg'), $this->ventas->id);
    }

    private function pasarAAuxiliar(): void
    {
        $fase = CatalogoEstatusPedido::porFase('PENDIENTE_AUXILIAR');
        $this->pedido->update(['catalogo_estatus_pedido_id' => $fase->id]);
        $this->pedido->refresh();
    }

    /** @param  array<string, mixed>  $attrs */
    private function pedidoHermano(string $folio, array $attrs): PedidoBma
    {
        return PedidoBma::query()->create(array_merge([
            'folio' => $folio,
            'fecha' => now()->toDateString(),
            'vendedor_id' => $this->ventas->id,
            'cliente_id' => $this->cliente->id,
            'catalogo_estatus_pedido_id' => CatalogoEstatusPedido::porFase('BORRADOR')->id,
            'solicitud_preparacion_json' => ['modalidad_entrega' => 'municipal'],
        ], $attrs));
    }
}
