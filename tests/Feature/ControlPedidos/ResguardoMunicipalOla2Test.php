<?php

namespace Tests\Feature\ControlPedidos;

use App\Models\Almacen;
use App\Models\CatalogoListaDescuento;
use App\Models\Cliente;
use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\CatalogoPaqueteriaPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaEnvioResguardo;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\ControlPedidos\Municipal\IntegrarCustodiaLocalMunicipalService;
use App\Services\ControlPedidos\Municipal\LiberarResguardoMunicipalService;
use App\Services\ControlPedidos\Municipal\PoliticaFinancieraMunicipal;
use App\Services\ControlPedidos\Municipal\VincularRemisionPagadaService;
use App\Services\ControlPedidos\NotificarPedidoBmaService;
use App\Services\SaldosAFavor\RegistrarPagoPedidoBmaService;
use App\Support\ControlPedidos\EstatusSucursalPedidoBma;
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

class ResguardoMunicipalOla2Test extends TestCase
{
    use RefreshDatabase;

    private User $ventas;

    private User $auxiliar;

    private Cliente $cliente;

    private PedidoBma $pedido;

    private CatalogoPaqueteriaPedido $jaguar;

    private Sucursal $sucursal;

    private Almacen $pdv;

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
        foreach (['control_pedidos.auditar', 'control_pedidos.liberar_resguardo'] as $permiso) {
            $this->auxiliar->givePermissionTo(Permission::findOrCreate($permiso, 'web'));
        }
        foreach (['BORRADOR', 'PENDIENTE_AUXILIAR', 'EN_CEDIS', 'ENVIADO'] as $orden => $fase) {
            CatalogoEstatusPedido::query()->create([
                'codigo_interno' => 'R2_'.$fase,
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
            'numero_cliente' => 'RG2-001',
            'nombre' => 'Cliente resguardo 2',
            'lista_actual_id' => $lista->id,
            'vendedor_id' => $this->ventas->id,
            'monto_venta_actual' => 0,
        ]);
        $this->pedido = PedidoBma::query()->create([
            'folio' => 'RG2-001',
            'fecha' => now()->toDateString(),
            'vendedor_id' => $this->ventas->id,
            'cliente_id' => $this->cliente->id,
            'catalogo_estatus_pedido_id' => CatalogoEstatusPedido::porFase('BORRADOR')->id,
            'total_mercancia' => 100,
            'solicitud_preparacion_json' => ['modalidad_entrega' => 'municipal'],
        ]);
        $departamento = \App\Models\Departamento::create(['nombre' => 'Departamento ficticio 2']);
        $this->ventas->update(['departamento_id' => $departamento->id]);
        $this->auxiliar->update(['departamento_id' => $departamento->id]);
        $this->pedido->update(['departamento_operacion_id' => $departamento->id, 'folio_remision' => 'WIZ-RG2']);
        Storage::disk('public')->put('qa/pedido2.png', 'soporte ficticio');
        $this->pedido->documentos()->create([
            'tipo' => 'pdf_pedido', 'ruta_archivo' => 'qa/pedido2.png',
            'nombre_original' => 'pedido.png', 'mime_type' => 'image/png', 'tamano_bytes' => 16, 'orden' => 0,
        ]);
        $this->sucursal = Sucursal::factory()->create();
        $this->pdv = Almacen::create([
            'codigo' => 'RG2-PDV', 'nombre' => 'PDV resguardo', 'activo' => true,
            'visible_en_pedidos' => true, 'sucursal_id' => $this->sucursal->id,
        ]);
        $this->jaguar = CatalogoPaqueteriaPedido::query()->create([
            'nombre' => 'Transporte Jaguar 2',
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

    public function test_intencion_temprana_con_consulta_abierta(): void
    {
        $this->guardarSolicitud(30);
        $this->postJson(route('control_pedidos.municipal.resguardo', $this->pedido), $this->capturaResguardo())->assertOk();
        $fresco = $this->pedido->fresh();
        $this->assertTrue(PoliticaResguardoEnvio::intencionActiva($fresco));
        $this->assertTrue((bool) $fresco->es_resguardo);
        $eval = PoliticaResguardoEnvio::evaluar($fresco);
        $this->assertTrue($eval['capacidades']['puede_activar_intencion']);
        $this->assertSame('ausente', $eval['cobertura_mercancia']);
        $this->assertSame(PoliticaResguardoEnvio::MENSAJE_SIN_PAGO, $eval['motivo_bloqueo_resguardo']);
    }

    public function test_m09_sin_transporte_no_esta_validado_y_no_libera_por_fecha(): void
    {
        $this->cerrarConsultaConocida(30);
        $this->postJson(route('control_pedidos.municipal.resguardo', $this->pedido), $this->capturaResguardo(null, [
            'motivo' => 'fecha_posterior',
            'fecha_solicitada' => now()->addWeek()->toDateString(),
        ]))->assertOk();
        $this->pagoEtapa(100);
        $eval = PoliticaResguardoEnvio::evaluar($this->pedido->fresh());
        $this->assertFalse($eval['m09_validado']);
        $this->assertSame('conocido_exigible', $eval['cobertura_transporte']);
        $this->expectException(\RuntimeException::class);
        PoliticaResguardoEnvio::assertPuedeDespachar($this->pedido->fresh());
    }

    public function test_m10_acumulacion_incremental_cajas_na_y_transporte_pendiente(): void
    {
        $this->cerrarConsultaConocida(30);
        $this->pagoEtapa(100);
        $this->postJson(route('control_pedidos.municipal.resguardo', $this->pedido), $this->capturaResguardo())->assertOk();
        $eval = PoliticaResguardoEnvio::evaluar($this->pedido->fresh());
        $this->assertTrue($eval['m10_en_acumulacion']);
        $this->assertSame('N/A', $eval['cajas']);
        $this->assertNull($this->pedido->fresh()->numero_cajas);
        $a = $this->remisionPagada('RG2-A', 'REM-A');
        $b = $this->remisionPagada('RG2-B', 'REM-B');
        $servicio = app(VincularRemisionPagadaService::class);
        $servicio->ejecutar($this->pedido->fresh(), $a, $this->ventas, PoliticaResguardoEnvio::MECANISMO_RESGUARDO);
        $servicio->ejecutar($this->pedido->fresh(), $b, $this->ventas, PoliticaResguardoEnvio::MECANISMO_RESGUARDO);
        $this->assertSame(2, $this->pedido->fresh()->resguardosAnexados()->count());
        $this->assertSame(100.0, (float) $this->pedido->fresh()->total_mercancia);
        $this->assertTrue((bool) $this->pedido->fresh()->es_resguardo);
        $this->postJson(route('control_pedidos.municipal.resguardo.acumulacion', $this->pedido), $this->capturaRevision())->assertOk();
        $this->assertNull($this->pedido->fresh()->numero_cajas);
        $this->assertFalse(PoliticaResguardoEnvio::evaluar($this->pedido->fresh())['m10_en_acumulacion']);
    }

    public function test_jaguar_diferido_conserva_transporte_nulo(): void
    {
        $this->cerrarConsultaConocida(0);
        $finanzas = app(PoliticaFinancieraMunicipal::class)->evaluar($this->pedido->fresh());
        $this->assertNull($finanzas['importe_transporte']);
        $this->assertTrue($finanzas['transporte_desconocido']);
        $this->assertSame('diferido', PoliticaResguardoEnvio::evaluar($this->pedido->fresh())['cobertura_transporte']);
        $this->postJson(route('control_pedidos.municipal.resguardo', $this->pedido), $this->capturaResguardo())->assertOk();
        $this->assertTrue(PoliticaResguardoEnvio::exigeSoloMercanciaParaAvanzar(
            $this->pedido->fresh(),
            RegistrarPagoPedidoBmaService::FASE_VALIDAR
        ));
    }

    public function test_vincular_mismo_cliente_es_exclusivo_e_idempotente(): void
    {
        $this->guardarSolicitud(0);
        $origen = $this->remisionPagada('RG2-V', 'REM-V');
        $otro = $this->pedidoHermano('RG2-OTRO', ['total_mercancia' => 10]);
        $servicio = app(VincularRemisionPagadaService::class);
        $servicio->ejecutar($this->pedido->fresh(), $origen, $this->ventas, PoliticaResguardoEnvio::MECANISMO_RESGUARDO);
        $servicio->ejecutar($this->pedido->fresh(), $origen->fresh(), $this->ventas, PoliticaResguardoEnvio::MECANISMO_RESGUARDO);
        $this->assertSame(1, PedidoBmaEnvioResguardo::query()->where('pedido_resguardo_id', $origen->id)->count());
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $servicio->ejecutar($otro, $origen->fresh(), $this->ventas, PoliticaResguardoEnvio::MECANISMO_RESGUARDO);
    }

    public function test_rechaza_otro_cliente_entregada_y_t05_ocupada(): void
    {
        $this->guardarSolicitud(0);
        $ajeno = Cliente::query()->create([
            'numero_cliente' => 'RG2-AJ',
            'nombre' => 'Otro cliente',
            'lista_actual_id' => $this->cliente->lista_actual_id,
            'vendedor_id' => $this->ventas->id,
            'monto_venta_actual' => 0,
        ]);
        $deOtro = $this->pedidoHermano('RG2-AJENO', [
            'cliente_id' => $ajeno->id,
            'pago_validado_at' => now(),
            'numero_remision' => 'REM-AJ',
        ]);
        $servicio = app(VincularRemisionPagadaService::class);
        try {
            $servicio->ejecutar($this->pedido->fresh(), $deOtro, $this->ventas, PoliticaResguardoEnvio::MECANISMO_RESGUARDO);
            $this->fail('Otro cliente debe rechazarse.');
        } catch (\Illuminate\Validation\ValidationException|\InvalidArgumentException $e) {
            $this->assertNotEmpty($e->getMessage());
        }

        $entregada = $this->remisionPagada('RG2-ENT', 'REM-ENT');
        ResguardoPdv::query()->create([
            'pedido_bma_id' => $entregada->id,
            'cliente_id' => $this->cliente->id,
            'sucursal_id' => $this->sucursal->id,
            'almacen_id' => $this->pdv->id,
            'estado' => ResguardoPdv::ESTADO_ENTREGADO,
            'entrega_bloqueada' => false,
            'snapshot_folio' => $entregada->folio,
            'snapshot_cliente_nombre' => 'Cliente',
            'version' => 1,
        ]);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $servicio->ejecutar($this->pedido->fresh(), $entregada->fresh(), $this->ventas, PoliticaResguardoEnvio::MECANISMO_RESGUARDO);
    }

    public function test_desvincular_antes_del_movimiento_y_bloquear_tras_integrar(): void
    {
        $this->guardarSolicitud(0);
        $origen = $this->remisionPagada('RG2-DV', 'REM-DV');
        $servicio = app(VincularRemisionPagadaService::class);
        $servicio->ejecutar($this->pedido->fresh(), $origen, $this->ventas, PoliticaResguardoEnvio::MECANISMO_RESGUARDO);
        $servicio->desvincular($this->pedido->fresh(), $origen->fresh(), $this->ventas);
        $this->assertSame(0, PedidoBmaEnvioResguardo::query()->where('pedido_resguardo_id', $origen->id)->count());

        $local = $this->remisionEnCustodia('RG2-LOC', 'REM-LOC');
        $servicio->ejecutar($this->pedido->fresh(), $local, $this->ventas, PoliticaResguardoEnvio::MECANISMO_LOCAL);
        $this->assertSame('pendiente', $local->fresh()->solicitud_preparacion_json['integracion_local']['estado']);
        $this->assertArrayNotHasKey('retiro_recoleccion', $local->fresh()->solicitud_preparacion_json);
        app(IntegrarCustodiaLocalMunicipalService::class)->ejecutar($local->fresh(), $this->ventas);
        $this->assertSame('integrado', $local->fresh()->solicitud_preparacion_json['integracion_local']['estado']);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $servicio->desvincular($this->pedido->fresh(), $local->fresh(), $this->ventas);
    }

    public function test_integracion_local_bloquea_entrega_sin_traslado_cedis(): void
    {
        $this->guardarSolicitud(0);
        $local = $this->remisionEnCustodia('RG2-PDV', 'REM-PDV');
        app(VincularRemisionPagadaService::class)->ejecutar(
            $this->pedido->fresh(),
            $local,
            $this->ventas,
            PoliticaResguardoEnvio::MECANISMO_LOCAL
        );
        $this->assertTrue((bool) ResguardoPdv::query()->where('pedido_bma_id', $local->id)->value('entrega_bloqueada'));
        $json = $local->fresh()->solicitud_preparacion_json;
        $this->assertSame('pendiente', $json['integracion_local']['estado']);
        $this->assertNotSame('pendiente_traslado', $json['retiro_recoleccion']['estado'] ?? null);
        $this->assertSame((int) $this->pedido->id, (int) $local->fresh()->pedido_principal_id);
        $this->assertSame('recepcion_pendiente', PoliticaResguardoEnvio::estadoConjunto($this->pedido->fresh()));
        $this->assertFalse((bool) $this->pedido->fresh()->es_resguardo);
    }

    public function test_retiro_cedis_no_integra_hasta_recibir_y_bloquea_liberacion(): void
    {
        $this->cerrarConsultaConocida(30);
        $this->pagoEtapa(100);
        $this->postJson(route('control_pedidos.municipal.resguardo', $this->pedido), $this->capturaResguardo())->assertOk();
        $cedis = $this->remisionEnCustodia('RG2-CED', 'REM-CED');
        app(VincularRemisionPagadaService::class)->ejecutar(
            $this->pedido->fresh(),
            $cedis,
            $this->ventas,
            PoliticaResguardoEnvio::MECANISMO_CEDIS
        );
        $this->assertSame('pendiente_traslado', $cedis->fresh()->solicitud_preparacion_json['retiro_recoleccion']['estado']);
        $this->assertFalse((bool) ($cedis->fresh()->solicitud_preparacion_json['retiro_recoleccion']['integrado_cedis'] ?? false));
        $this->assertTrue((bool) ResguardoPdv::query()->where('pedido_bma_id', $cedis->id)->value('entrega_bloqueada'));
        $this->postJson(route('control_pedidos.municipal.resguardo.acumulacion', $this->pedido), $this->capturaRevision())->assertOk();
        $this->postJson(route('control_pedidos.municipal.resguardo.continuacion', $this->pedido), $this->capturaRevision())
            ->assertUnprocessable();
        $this->assertSame('recepcion_pendiente', PoliticaResguardoEnvio::estadoConjunto($this->pedido->fresh()));
    }

    public function test_liberacion_exige_continuacion_y_conjunto_y_no_marca_enviado(): void
    {
        $this->cerrarConsultaConocida(30);
        $this->pagoEtapa(100);
        $this->postJson(route('control_pedidos.municipal.resguardo', $this->pedido), $this->capturaResguardo())->assertOk();
        try {
            app(LiberarResguardoMunicipalService::class)->ejecutar($this->pedido->fresh(), $this->auxiliar->id);
            $this->fail('Sin continuación no libera.');
        } catch (\RuntimeException $e) {
            $this->assertSame(PoliticaResguardoEnvio::MENSAJE_CONTINUACION, $e->getMessage());
        }
        $this->postJson(route('control_pedidos.municipal.resguardo.acumulacion', $this->pedido), $this->capturaRevision())->assertOk();
        $this->pasarAAuxiliar();
        $this->postJson(route('control_pedidos.municipal.resguardo.continuacion', $this->pedido), $this->capturaRevision())->assertOk();
        $principal = app(LiberarResguardoMunicipalService::class)->ejecutar($this->pedido->fresh(), $this->auxiliar->id);
        $this->assertFalse(PoliticaResguardoEnvio::intencionActiva($principal));
        $this->assertNotSame('ENVIADO', $principal->estatus?->fase_ciclo);
        $repetido = app(LiberarResguardoMunicipalService::class)->ejecutar($principal, $this->auxiliar->id);
        $this->assertNotNull(PoliticaResguardoEnvio::datos($repetido)['liberado_at']);
    }

    public function test_conjunto_incompleto_rechaza_liberar_y_vincular_no_toca_expediente(): void
    {
        $this->cerrarConsultaConocida(30);
        $paqueteria = $this->pedido->fresh()->catalogo_paqueteria_id;
        $this->pagoEtapa(100);
        $this->postJson(route('control_pedidos.municipal.resguardo', $this->pedido), $this->capturaResguardo())->assertOk();
        $this->postJson(route('control_pedidos.municipal.resguardo.acumulacion', $this->pedido), $this->capturaRevision())->assertOk();
        $this->pasarAAuxiliar();
        $this->postJson(route('control_pedidos.municipal.resguardo.continuacion', $this->pedido), $this->capturaRevision())->assertOk();
        $cedis = $this->remisionEnCustodia('RG2-EXP', 'REM-EXP');
        app(VincularRemisionPagadaService::class)->ejecutar(
            $this->pedido->fresh(),
            $cedis,
            $this->ventas,
            PoliticaResguardoEnvio::MECANISMO_CEDIS
        );
        $this->assertSame($paqueteria, $this->pedido->fresh()->catalogo_paqueteria_id);
        $this->assertSame('municipal', $this->pedido->fresh()->solicitud_preparacion_json['modalidad_entrega']);
        try {
            app(LiberarResguardoMunicipalService::class)->ejecutar($this->pedido->fresh(), $this->auxiliar->id);
            $this->fail('No debe liberar con el conjunto o la continuación inválidos.');
        } catch (\RuntimeException $e) {
            $this->assertContains($e->getMessage(), [
                PoliticaResguardoEnvio::MENSAJE_CONTINUACION,
                PoliticaResguardoEnvio::MENSAJE_CONJUNTO,
            ]);
        }
        $this->assertTrue(PoliticaResguardoEnvio::intencionActiva($this->pedido->fresh()));
    }

    public function test_cedis_y_ventas_y_auxiliar_sin_facultad_no_liberan(): void
    {
        $this->cerrarConsultaConocida(30);
        $this->pagoEtapa(100);
        $this->postJson(route('control_pedidos.municipal.resguardo', $this->pedido), $this->capturaResguardo())->assertOk();
        $this->postJson(route('control_pedidos.municipal.resguardo.acumulacion', $this->pedido), $this->capturaRevision())->assertOk();
        $this->pasarAAuxiliar();
        $this->postJson(route('control_pedidos.municipal.resguardo.continuacion', $this->pedido), $this->capturaRevision())->assertOk();

        $cedis = User::factory()->create(['departamento_id' => $this->ventas->departamento_id]);
        $cedis->givePermissionTo(Permission::findOrCreate('control_pedidos.cedis', 'web'));
        $this->actingAs($cedis);
        $this->post(route('control_pedidos.auditar.liberar_resguardo', $this->pedido))->assertForbidden();

        $sinLiberar = User::factory()->create(['departamento_id' => $this->ventas->departamento_id]);
        $sinLiberar->givePermissionTo(Permission::findOrCreate('control_pedidos.auditar', 'web'));
        $this->actingAs($sinLiberar);
        $this->post(route('control_pedidos.auditar.liberar_resguardo', $this->pedido))->assertForbidden();
        $this->assertTrue(PoliticaResguardoEnvio::intencionActiva($this->pedido->fresh()));
    }

    public function test_m07_despachado_no_reconducir_y_m08_credito_no_habilita(): void
    {
        $this->guardarSolicitud(0);
        $this->pedido->update(['catalogo_estatus_pedido_id' => CatalogoEstatusPedido::porFase('ENVIADO')->id]);
        $this->assertFalse(PoliticaResguardoEnvio::puedeReconducir($this->pedido->fresh()));
        $this->assertFalse(PoliticaResguardoEnvio::evaluar($this->pedido->fresh())['capacidades']['puede_reconducir']);

        $otro = $this->pedidoHermano('RG2-CR', ['total_mercancia' => 80]);
        $json = $otro->solicitud_preparacion_json;
        $json['credito_wizerp'] = ['resultado' => 'aprobado'];
        $otro->update(['solicitud_preparacion_json' => $json]);
        $this->postJson(route('control_pedidos.municipal.resguardo', $otro), $this->capturaResguardo($otro->fresh()))
            ->assertUnprocessable();
        $this->assertSame('credito_aprobado', PoliticaResguardoEnvio::evaluar($otro->fresh())['fuente_cobertura']);
    }

    public function test_exclusividad_t05_y_pivot_secuencial(): void
    {
        $this->guardarSolicitud(0);
        $origen = $this->remisionEnCustodia('RG2-X', 'REM-X');
        $otro = $this->pedidoHermano('RG2-Y', ['total_mercancia' => 10]);
        app(VincularRemisionPagadaService::class)->ejecutar(
            $this->pedido->fresh(),
            $origen,
            $this->ventas,
            PoliticaResguardoEnvio::MECANISMO_CEDIS
        );
        $this->expectException(\InvalidArgumentException::class);
        ExclusividadRemisionEnvio::assertDisponible($origen->fresh(), $otro);
    }

    private function guardarSolicitud(float $costo): int
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

        return (int) $id;
    }

    private function cerrarConsultaConocida(float $costo): void
    {
        $this->guardarSolicitud($costo);
        $this->postJson(route('control_pedidos.municipal.consulta', $this->pedido))->assertOk();
        $this->post(route('control_pedidos.municipal.disponibilidad', $this->pedido), [
            'confirmacion_explicita' => 1, 'evidencias' => [UploadedFile::fake()->image('conjunto.jpg')],
        ])->assertOk();
        $this->postJson(route('control_pedidos.cerrar_consulta', $this->pedido))->assertOk();
    }

    /** @param  array<string, mixed>  $extra */
    private function capturaResguardo(?PedidoBma $pedido = null, array $extra = []): array
    {
        $pedido = $pedido ?: $this->pedido->fresh();

        return array_merge([
            'activo' => true,
            'motivo' => 'acumulacion',
            'revision_esperada' => (int) $pedido->revision_registro,
            'base_captura' => RevisionCapturaPedido::base($pedido),
        ], $extra);
    }

    /** @return array<string, mixed> */
    private function capturaRevision(?PedidoBma $pedido = null): array
    {
        $pedido = $pedido ?: $this->pedido->fresh();

        return [
            'revision_esperada' => (int) $pedido->revision_registro,
            'base_captura' => RevisionCapturaPedido::base($pedido),
        ];
    }

    private function pagoEtapa(float $monto): void
    {
        app(RegistrarPagoPedidoBmaService::class)->handle($this->pedido->fresh(), [
            'monto' => $monto, 'forma_pago' => 'efectivo', 'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
        ], UploadedFile::fake()->image('pago.jpg'), $this->ventas->id);
    }

    private function pasarAAuxiliar(): void
    {
        $this->pedido->update(['catalogo_estatus_pedido_id' => CatalogoEstatusPedido::porFase('PENDIENTE_AUXILIAR')->id]);
        $this->pedido->refresh();
        $this->actingAs($this->ventas);
    }

    /** @param  array<string, mixed>  $attrs */
    private function pedidoHermano(string $folio, array $attrs): PedidoBma
    {
        return PedidoBma::query()->create(array_merge([
            'folio' => $folio,
            'fecha' => now()->toDateString(),
            'vendedor_id' => $this->ventas->id,
            'cliente_id' => $this->cliente->id,
            'departamento_operacion_id' => $this->pedido->departamento_operacion_id,
            'catalogo_estatus_pedido_id' => CatalogoEstatusPedido::porFase('BORRADOR')->id,
            'solicitud_preparacion_json' => ['modalidad_entrega' => 'municipal'],
        ], $attrs));
    }

    private function remisionPagada(string $folio, string $remision): PedidoBma
    {
        return $this->pedidoHermano($folio, [
            'numero_remision' => $remision,
            'pago_validado_at' => now(),
            'total_mercancia' => 40,
        ]);
    }

    private function remisionEnCustodia(string $folio, string $remision): PedidoBma
    {
        $pedido = $this->pedidoHermano($folio, [
            'numero_remision' => $remision,
            'pago_validado_at' => now(),
            'total_mercancia' => 40,
            'estatus_sucursal' => EstatusSucursalPedidoBma::EN_CUSTODIA,
            'sucursal_destino_id' => $this->sucursal->id,
        ]);
        ResguardoPdv::query()->create([
            'pedido_bma_id' => $pedido->id,
            'cliente_id' => $this->cliente->id,
            'sucursal_id' => $this->sucursal->id,
            'almacen_id' => $this->pdv->id,
            'estado' => ResguardoPdv::ESTADO_EN_CUSTODIA,
            'entrega_bloqueada' => false,
            'snapshot_folio' => $pedido->folio,
            'snapshot_cliente_nombre' => 'Cliente',
            'version' => 1,
        ]);

        return $pedido;
    }
}
