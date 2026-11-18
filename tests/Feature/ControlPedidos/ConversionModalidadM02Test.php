<?php

namespace Tests\Feature\ControlPedidos;

use App\Events\PedidoBmaActualizado;
use App\Models\Almacen;
use App\Models\CatalogoBanco;
use App\Models\CatalogoTipoAlmacen;
use App\Models\Cliente;
use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\CatalogoModalidadPreparacionPedido;
use App\Models\ControlPedidos\CatalogoOrigenPedido;
use App\Models\ControlPedidos\CatalogoPaqueteriaPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\Departamento;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\SaldosAFavor\PedidoBmaPago;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\ControlPedidos\Conversion\PersistirConversionModalidad;
use App\Services\ControlPedidos\ListarPedidosBmaService;
use App\Services\ControlPedidos\NotificarPedidoBmaService;
use App\Services\ControlPedidos\RegistrarHistorialPedidoService;
use App\Services\SaldosAFavor\RegistrarPagoPedidoBmaService;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use App\Support\ControlPedidos\Conversion\CodigosConversionModalidad;
use App\Support\ControlPedidos\Conversion\ConsultaPedidoYaCerradaAlgunaVez;
use App\Support\ControlPedidos\Conversion\ConversionModalidadEnCursoPedidoBma;
use App\Support\ControlPedidos\Conversion\DispatcherPoliticasConversion;
use App\Support\ControlPedidos\Conversion\PoliticaConversionMunicipal;
use App\Support\ControlPedidos\Conversion\PoliticaConversionNacional;
use App\Support\ControlPedidos\Conversion\PoliticaConversionTienda;
use App\Support\ControlPedidos\InstruccionEntrega;
use App\Support\ControlPedidos\RevisionCapturaPedido;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Casos 1–29 del contrato M-02. Copian helpers de las suites Tienda/M-01;
 * no las modifican. Fallar hasta que aterrice el motor es el resultado esperado.
 */
class ConversionModalidadM02Test extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    private Departamento $departamento;

    private Almacen $pdv;

    private CatalogoPaqueteriaPedido $jaguar;

    /** @var array<int, int> */
    private array $consignatarios = [];

    private ?string $instanciaActual = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->withoutMiddleware([ValidateCsrfToken::class, PreventRequestForgery::class]);
        Storage::fake('local');
        Storage::fake('public');
        Cache::flush();
        $this->instanciaActual = null;
        $this->consignatarios = [];
        $this->mock(NotificarPedidoBmaService::class)->shouldReceive('ejecutar')->zeroOrMoreTimes();

        $this->departamento = Departamento::query()->create(['nombre' => 'Ventas', 'activo' => true]);
        $this->usuario = User::factory()->create(['departamento_id' => $this->departamento->id]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach ([
            'control_pedidos.crear',
            'control_pedidos.tienda.ver',
            'control_pedidos.cedis',
            'control_pedidos.cedis.ver_todos',
            'clientes.expediente.ver',
            'clientes.expediente.editar',
            'clientes.expediente.documentos',
            'clientes.expediente.ver_identificacion',
        ] as $permiso) {
            $this->usuario->givePermissionTo(Permission::findOrCreate($permiso, 'web'));
        }
        foreach ([
            CatalogoEstatusPedido::FASE_BORRADOR => 'BORRADOR',
            CatalogoEstatusPedido::FASE_PESAJE_PENDIENTE => 'PESAJE',
            CatalogoEstatusPedido::FASE_PESAJE_RESPONDIDO => 'PESAJE_OK',
            CatalogoEstatusPedido::FASE_ENTREGADO => 'ENTREGADO',
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
        CatalogoModalidadPreparacionPedido::query()->firstOrCreate(
            ['codigo' => CatalogoModalidadPreparacionPedido::CODIGO_ENVIO_MUNICIPIO],
            ['nombre' => 'Envío municipio', 'activo' => true]
        );
        $tipo = CatalogoTipoAlmacen::query()->firstOrCreate(['nombre' => 'PDV']);
        $this->pdv = Almacen::query()->create([
            'codigo' => 'SUC-M02',
            'nombre' => 'Sucursal Centro',
            'activo' => true,
            'visible_en_pedidos' => true,
            'permite_salida_pedidos' => true,
            'tipo_almacen_id' => $tipo->id,
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
    }

    public function test_01_consulta_no_iniciada_convierte_mismo_folio_y_deja_circuito_listo(): void
    {
        $pedido = $this->pedido();
        $this->documento($pedido);
        $folio = $pedido->folio;
        $id = $pedido->id;

        $this->reservar($pedido)->assertOk();
        $respuesta = $this->confirmar($pedido, ['costo_transporte' => 'diferido', 'costo_envio' => null]);
        $respuesta->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('pedido_id', $id)
            ->assertJsonPath('folio', $folio)
            ->assertJsonPath('modalidad_entrega', CodigosConversionModalidad::DESTINO_MUNICIPAL)
            ->assertJsonPath('herencia.caso', CodigosConversionModalidad::CASO_SIN_CONSULTA)
            ->assertJsonPath('herencia.procedencia', CodigosConversionModalidad::PROCEDENCIA_NUEVA);

        $fresco = $pedido->fresh();
        $this->assertSame($id, $fresco->id);
        $this->assertSame($folio, $fresco->folio);
        $this->assertSame('municipal', $fresco->solicitud_preparacion_json['modalidad_entrega']);
        $consulta = $fresco->solicitud_preparacion_json['consulta_municipal'] ?? [];
        $this->assertSame(CodigosConversionModalidad::PROCEDENCIA_NUEVA, $consulta['procedencia'] ?? null);
        $this->assertSame(CodigosConversionModalidad::CONSULTA_ORIGEN_NINGUNA, $consulta['consulta_origen'] ?? null);
        $this->assertArrayNotHasKey('enviada_at', $consulta);
        $this->assertNull($consulta['enviada_at'] ?? null);
        $this->assertArrayNotHasKey('revision_disponibilidad', $fresco->solicitud_preparacion_json);

        $this->actingAs($this->usuario)
            ->postJson(route('control_pedidos.municipal.consulta', $fresco))
            ->assertOk();
        $this->assertNotEmpty($fresco->fresh()->solicitud_preparacion_json['consulta_municipal']['enviada_at'] ?? null);
    }

    public function test_02_consulta_parcial_conserva_evidencia_pide_complementaria_y_no_cierra(): void
    {
        $enviada = '2026-11-10T09:00:00-06:00';
        $pedido = $this->pedido([
            'solicitud_preparacion_json' => [
                'version' => 1,
                'modalidad_entrega' => 'tienda',
                'area_revision' => 'control_pedidos',
                'revision_disponibilidad' => [
                    'estado' => 'excepciones_pendientes',
                    'vigente' => true,
                    'enviada_at' => $enviada,
                    'unidades' => [['descripcion' => 'Crema corporal', 'cantidad' => 2]],
                ],
            ],
        ]);
        $this->documento($pedido);

        $this->reservar($pedido)->assertOk();
        $this->confirmar($pedido)->assertOk()
            ->assertJsonPath('herencia.caso', CodigosConversionModalidad::CASO_PARCIAL)
            ->assertJsonPath('herencia.procedencia', CodigosConversionModalidad::PROCEDENCIA_HEREDADA_PARCIAL);

        $fresco = $pedido->fresh();
        $revision = $fresco->solicitud_preparacion_json['revision_disponibilidad'] ?? [];
        $this->assertSame('excepciones_pendientes', $revision['estado'] ?? null);
        $this->assertSame($enviada, $revision['enviada_at'] ?? null);
        $this->assertSame([['descripcion' => 'Crema corporal', 'cantidad' => 2]], $revision['unidades'] ?? null);
        $consulta = $fresco->solicitud_preparacion_json['consulta_municipal'] ?? [];
        $this->assertSame(CodigosConversionModalidad::PROCEDENCIA_HEREDADA_PARCIAL, $consulta['procedencia'] ?? null);
        $this->assertTrue($consulta['requiere_revision_complementaria'] ?? false);
        $this->assertSame('excepciones_pendientes', $consulta['estado'] ?? null);
        $this->assertNotSame('cerrada', $consulta['estado'] ?? null);
        $this->assertNotSame('aprobada', $consulta['estado'] ?? null);
        $this->assertNull($fresco->consulta_cerrada_at);
        $this->assertFalse($fresco->consultaCerrada());
    }

    public function test_03_consulta_respondida_abierta_hereda_revision_completa(): void
    {
        $enviada = '2026-11-11T12:30:00-06:00';
        $revision = [
            'estado' => 'sin_excepciones',
            'vigente' => true,
            'respondida_at' => $enviada,
            'enviada_at' => $enviada,
            'conjunto' => 'ok',
        ];
        $pedido = $this->pedidoRespondidoAbierto(['revision' => $revision]);

        $this->reservar($pedido)->assertOk();
        $this->confirmar($pedido)->assertOk()
            ->assertJsonPath('herencia.caso', CodigosConversionModalidad::CASO_RESPONDIDA_ABIERTA)
            ->assertJsonPath('herencia.procedencia', CodigosConversionModalidad::PROCEDENCIA_HEREDADA_COMPLETA);

        $fresco = $pedido->fresh();
        $this->assertSame($revision, $fresco->solicitud_preparacion_json['revision_disponibilidad'] ?? null);
        $consulta = $fresco->solicitud_preparacion_json['consulta_municipal'] ?? [];
        $this->assertSame(CodigosConversionModalidad::PROCEDENCIA_HEREDADA_COMPLETA, $consulta['procedencia'] ?? null);
        $this->assertSame($enviada, $consulta['enviada_at'] ?? null);
        $this->assertNull($fresco->consulta_cerrada_at);
        $this->assertArrayHasKey('revision_disponibilidad', $fresco->solicitud_preparacion_json);
    }

    public function test_04_consulta_cerrada_devuelve_422_y_no_cambia_modalidad(): void
    {
        $pedido = $this->pedidoRespondidoAbierto();
        $this->historialCierre($pedido);
        $pedido->update(['consulta_cerrada_at' => now()]);
        $this->assertTrue(ConsultaPedidoYaCerradaAlgunaVez::es($pedido->fresh()));

        $this->confirmar($pedido)->assertStatus(422)
            ->assertJsonPath('codigo', CodigosConversionModalidad::CODIGO_CONSULTA_YA_CERRADA);
        $fresco = $pedido->fresh();
        $this->assertSame('tienda', $fresco->solicitud_preparacion_json['modalidad_entrega']);
        $this->assertNotNull($fresco->consulta_cerrada_at);
    }

    public function test_05_cerrada_y_reabierta_sigue_bloqueada_por_historial(): void
    {
        $pedido = $this->pedidoRespondidoAbierto();
        $this->historialCierre($pedido);
        $pedido->update(['consulta_cerrada_at' => null, 'consulta_cerrada_por_id' => null]);
        $fresco = $pedido->fresh();
        $this->assertNull($fresco->consulta_cerrada_at);
        $this->assertTrue(
            $fresco->historial()->where('accion', AccionesHistorialPedidoBma::CIERRE_CONSULTA)->exists()
        );
        $this->assertTrue(ConsultaPedidoYaCerradaAlgunaVez::es($fresco));

        $this->confirmar($fresco)->assertStatus(422)
            ->assertJsonPath('codigo', CodigosConversionModalidad::CODIGO_CONSULTA_YA_CERRADA);
        $this->assertSame('tienda', $pedido->fresh()->solicitud_preparacion_json['modalidad_entrega']);
        $this->assertNull($pedido->fresh()->consulta_cerrada_at);
    }

    public function test_06_pago_confirmado_con_consulta_abierta_convierte_y_conserva_pagos(): void
    {
        $pedido = $this->pedidoRespondidoAbierto();
        $pago = $this->pagar($pedido, ['monto' => 100, 'forma_pago' => 'transferencia']);
        $pedido->update(['pago_validado_at' => now()]);
        $this->assertTrue($pedido->fresh()->pedidoPagadoORemisionado());
        $this->assertFalse(ConsultaPedidoYaCerradaAlgunaVez::es($pedido->fresh()));

        $this->reservar($pedido)->assertOk();
        $previa = $this->vistaPrevia($pedido, ['costo_transporte' => 'conocido', 'costo_envio' => '150.00'])->assertOk();
        $this->assertSame('150.00', $previa->json('conversion_modalidad.finanzas.pendiente_transporte'));
        $this->assertTrue($previa->json('conversion_modalidad.finanzas.validacion_historica_no_cubre_nuevo_cargo'));
        $this->confirmar($pedido, ['costo_transporte' => 'conocido', 'costo_envio' => '150.00'])->assertOk()
            ->assertJsonPath('modalidad_entrega', 'municipal');

        $fresco = $pedido->fresh();
        $this->assertSame('municipal', $fresco->solicitud_preparacion_json['modalidad_entrega']);
        $this->assertSame(1, PedidoBmaPago::query()->where('pedido_bma_id', $fresco->id)->count());
        $this->assertEquals(100, (float) $pago->fresh()->monto);
        $this->assertNotNull($fresco->pago_validado_at);
        $this->assertEquals(150, (float) $fresco->costo_envio);
        $this->assertSame('150.00', $this->flags($fresco)->assertOk()->json('conversion_modalidad.finanzas.pendiente_transporte'));
    }

    public function test_07_remision_emitida_con_consulta_abierta_convierte_y_conserva_remision(): void
    {
        $pedido = $this->pedidoRespondidoAbierto();
        $pedido->update(['numero_remision' => 'REM-M02']);
        $pedido->documentos()->create([
            'tipo' => PedidoBmaDocumento::TIPO_REMISION,
            'ruta_archivo' => 'remision-m02.pdf',
            'nombre_original' => 'remision-m02.pdf',
            'mime_type' => 'application/pdf',
            'tamano_bytes' => 80,
            'orden' => 2,
            'activo' => true,
        ]);
        $this->assertTrue($pedido->fresh()->pedidoPagadoORemisionado());

        $this->reservar($pedido)->assertOk();
        $this->confirmar($pedido)->assertOk();

        $fresco = $pedido->fresh();
        $this->assertSame('municipal', $fresco->solicitud_preparacion_json['modalidad_entrega']);
        $this->assertSame('REM-M02', $fresco->numero_remision);
        $this->assertTrue($fresco->tieneRemision());
        $this->assertSame(1, $fresco->remision()->count());
        $this->assertSame('remision-m02.pdf', $fresco->remision()->first()->nombre_original);
    }

    public function test_08_solo_folio_remision_no_es_cierre_ni_veto(): void
    {
        $pedido = $this->pedido();
        $this->assertSame('FOL-211', $pedido->folio_remision);
        $this->assertNull($pedido->numero_remision);
        $this->assertFalse($pedido->tieneRemision());
        $this->assertFalse($pedido->pedidoPagadoORemisionado());
        $this->assertFalse(ConsultaPedidoYaCerradaAlgunaVez::es($pedido));
        $this->flags($pedido)->assertOk()
            ->assertJsonPath('conversion_modalidad.consulta_ya_cerrada_alguna_vez', false)
            ->assertJsonPath('conversion_modalidad.puede_iniciar', true);

        $this->reservar($pedido)->assertOk();
        $this->confirmar($pedido, ['costo_transporte' => 'diferido', 'costo_envio' => null])->assertOk();
        $this->assertSame('municipal', $pedido->fresh()->solicitud_preparacion_json['modalidad_entrega']);
        $this->assertSame('FOL-211', $pedido->fresh()->folio_remision);
    }

    public function test_09_transporte_diferido_persiste_null_no_cubierto_ni_cero(): void
    {
        $pedido = $this->pedido();
        $this->reservar($pedido)->assertOk();
        $previa = $this->vistaPrevia($pedido, ['costo_transporte' => 'diferido', 'costo_envio' => null])->assertOk();
        $this->assertTrue($previa->json('conversion_modalidad.finanzas.transporte_desconocido'));
        $this->assertTrue($previa->json('conversion_modalidad.finanzas.validacion_historica_no_cubre_nuevo_cargo'));
        $this->assertNull($previa->json('conversion_modalidad.finanzas.costo_envio_nuevo'));
        $this->confirmar($pedido, ['costo_transporte' => 'diferido', 'costo_envio' => null])->assertOk();

        $fresco = $pedido->fresh();
        $this->assertNull($fresco->costo_envio);
        $this->assertNotSame('0.00', $fresco->costo_envio);
        $this->assertNotSame(0, $fresco->costo_envio);
        $this->assertSame('diferido', $fresco->solicitud_preparacion_json['finanzas_municipales']['costo_transporte'] ?? null);
        $this->assertTrue($this->flags($fresco)->assertOk()->json('conversion_modalidad.finanzas.transporte_desconocido'));
    }

    public function test_10_exhibicion_nueva_cubre_solo_pendiente_historica_no_alcanza(): void
    {
        $pedido = $this->pedidoRespondidoAbierto();
        $historico = $this->pagar($pedido, ['monto' => 100, 'forma_pago' => 'transferencia']);
        $pedido->update(['pago_validado_at' => now()]);

        $this->reservar($pedido)->assertOk();
        $antes = $this->vistaPrevia($pedido, ['costo_transporte' => 'conocido', 'costo_envio' => '150.00'])->assertOk();
        $this->assertSame('150.00', $antes->json('conversion_modalidad.finanzas.pendiente_transporte'));
        $this->assertTrue($antes->json('conversion_modalidad.finanzas.validacion_historica_no_cubre_nuevo_cargo'));
        $this->confirmar($pedido, ['costo_transporte' => 'conocido', 'costo_envio' => '150.00'])->assertOk();
        $this->assertSame('150.00', $this->flags($pedido->fresh())->assertOk()->json('conversion_modalidad.finanzas.pendiente_transporte'));
        $this->assertEquals(100, (float) $historico->fresh()->monto);

        try {
            $this->pagar($pedido->fresh(), ['monto' => 150, 'forma_pago' => 'transferencia', 'referencia' => 'ENV-M02']);
            $this->fail('La exhibición no debió proceder con la consulta heredada abierta.');
        } catch (ValidationException $e) {
            $this->assertSame(
                'La consulta municipal no está cerrada. La respuesta del área no autoriza el pago ni la remisión.',
                $e->errors()['consulta'][0] ?? null
            );
        }
        $this->assertSame(1, PedidoBmaPago::query()->where('pedido_bma_id', $pedido->id)->count());
        $this->assertSame('150.00', $this->flags($pedido->fresh())->assertOk()->json('conversion_modalidad.finanzas.pendiente_transporte'));

        $this->actingAs($this->usuario)->postJson("/control-pedidos/{$pedido->id}/cerrar-consulta", [
            'modalidad_esperada' => 'municipal',
            'version_consulta' => (int) $pedido->fresh()->historial()->max('id'),
        ])->assertOk();
        $this->assertNotNull($pedido->fresh()->consulta_cerrada_at);

        $nuevo = $this->pagar($pedido->fresh(), ['monto' => 150, 'forma_pago' => 'transferencia', 'referencia' => 'ENV-M02']);
        $despues = $this->flags($pedido->fresh())->assertOk();
        $this->assertSame('0.00', $despues->json('conversion_modalidad.finanzas.pendiente_transporte'));
        $this->assertEquals(150, (float) $nuevo->monto);
        $this->assertEquals(100, (float) $historico->fresh()->monto);
        $this->assertSame(2, PedidoBmaPago::query()->where('pedido_bma_id', $pedido->id)->count());
    }

    public function test_11_anexos_o_retiros_pendientes_son_parcial_sin_aprobar_por_inferencia(): void
    {
        $pedido = $this->pedido([
            'solicitud_preparacion_json' => [
                'version' => 1,
                'modalidad_entrega' => 'tienda',
                'area_revision' => 'control_pedidos',
                'revision_disponibilidad' => ['estado' => 'sin_excepciones', 'vigente' => true],
                'anexo_sin_remision' => [['entrada' => 'documento', 'documento_id' => 1, 'estado' => 'pendiente']],
            ],
        ]);
        $this->flags($pedido)->assertOk()
            ->assertJsonPath('conversion_modalidad.herencia.caso', CodigosConversionModalidad::CASO_PARCIAL);

        $this->reservar($pedido)->assertOk();
        $this->confirmar($pedido)->assertOk()
            ->assertJsonPath('herencia.caso', CodigosConversionModalidad::CASO_PARCIAL);

        $consulta = $pedido->fresh()->solicitud_preparacion_json['consulta_municipal'] ?? [];
        $this->assertSame(CodigosConversionModalidad::PROCEDENCIA_HEREDADA_PARCIAL, $consulta['procedencia'] ?? null);
        $this->assertTrue($consulta['requiere_revision_complementaria'] ?? false);
        $this->assertNotSame('cerrada', $consulta['estado'] ?? null);
        $this->assertNotSame('sin_excepciones', $consulta['estado'] ?? null);
        $this->assertSame('excepciones_pendientes', $consulta['estado'] ?? null);
        $this->assertNull($pedido->fresh()->consulta_cerrada_at);
    }

    public function test_12_cedis_o_preparacion_temprana_mismo_pedido_una_instruccion_vigente(): void
    {
        $pedido = $this->pedido();
        $this->documento($pedido);
        $this->actingAs($this->usuario)->postJson(
            "/control-pedidos/{$pedido->id}/solicitud-tienda",
            $this->solicitud($pedido, 'preparacion_tienda', $this->pdv->id)
        )->assertOk();
        $tienda = PedidoBmaTareaPreparacion::query()->where('pedido_bma_id', $pedido->id)->firstOrFail();
        $this->assertSame(1, InstruccionEntrega::abiertas($pedido->id)->count());

        $this->reservar($pedido->fresh())->assertOk();
        $this->confirmar($pedido->fresh(), ['costo_transporte' => 'diferido', 'costo_envio' => null])->assertOk();

        $fresco = $pedido->fresh();
        $this->assertSame($pedido->id, $fresco->id);
        $this->assertSame('municipal', $fresco->solicitud_preparacion_json['modalidad_entrega']);
        $this->assertSame(PedidoBmaTareaPreparacion::ESTADO_SUSTITUIDA, $tienda->fresh()->estado);
        $this->assertLessThanOrEqual(1, InstruccionEntrega::abiertas($fresco->id)->count());
        $this->assertSame(1, PedidoBma::query()->whereKey($pedido->id)->count());
    }

    public function test_13_custodia_con_consulta_abierta_bloquea_entrega_cerrada_no_convierte(): void
    {
        $abierta = $this->pedidoRespondidoAbierto();
        $resguardo = $this->custodia($abierta);
        $salida = $resguardo->salida_cedis_at;
        $this->reservar($abierta)->assertOk();
        $this->confirmar($abierta)->assertOk();

        $fresco = $abierta->fresh();
        $this->assertSame('municipal', $fresco->solicitud_preparacion_json['modalidad_entrega']);
        $this->flags($fresco)->assertOk()
            ->assertJsonPath('conversion_modalidad.fisica.entrega_bloqueada', true)
            ->assertJsonPath('conversion_modalidad.fisica.condicion', CodigosConversionModalidad::FISICA_CUSTODIA_EXCEPCION);
        $this->assertNotNull($fresco->motivoBloqueoEntregaTienda());
        $custodia = $resguardo->fresh();
        $this->assertSame(ResguardoPdv::ESTADO_EN_CUSTODIA, $custodia->estado);
        $this->assertEquals($salida, $custodia->salida_cedis_at);
        $this->assertTrue((bool) $custodia->entrega_bloqueada);

        $cerrada = $this->pedidoRespondidoAbierto(['folio' => 'INT-CERRADA']);
        $this->custodia($cerrada);
        $this->historialCierre($cerrada);
        $cerrada->update(['consulta_cerrada_at' => now()]);
        $this->confirmar($cerrada)->assertStatus(422)
            ->assertJsonPath('codigo', CodigosConversionModalidad::CODIGO_CONSULTA_YA_CERRADA);
        $this->assertSame('tienda', $cerrada->fresh()->solicitud_preparacion_json['modalidad_entrega']);
        $this->assertSame(ResguardoPdv::ESTADO_EN_CUSTODIA, ResguardoPdv::query()->where('pedido_bma_id', $cerrada->id)->value('estado'));
    }

    public function test_14_abrir_modal_adquiere_reserva_atomica_y_bloquea_cierre(): void
    {
        Event::fake([PedidoBmaActualizado::class]);
        $pedido = $this->pedidoRespondidoAbierto();
        $instancia = (string) Str::uuid();
        $this->assertSame(45, ConversionModalidadEnCursoPedidoBma::TTL_SEGUNDOS);

        $this->reservar($pedido, $instancia)->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('reserva_activa', true)
            ->assertJsonPath('reserva_usuario_id', $this->usuario->id)
            ->assertJsonPath('ttl_segundos', 45);

        $payload = ConversionModalidadEnCursoPedidoBma::payload($pedido->id);
        $this->assertNotNull($payload);
        $this->assertSame($this->usuario->id, $payload->usuario_id);
        $this->assertSame($instancia, $payload->instancia_id);
        $this->assertTrue(Cache::has(ConversionModalidadEnCursoPedidoBma::clave($pedido->id)));

        Event::assertDispatched(
            PedidoBmaActualizado::class,
            fn (PedidoBmaActualizado $evento) => $evento->pedidoId === $pedido->id
                && $evento->accion === CodigosConversionModalidad::ACCION_RESERVA_ADQUIRIDA
        );

        $this->flags($pedido)->assertOk()
            ->assertJsonPath('conversion_modalidad.reserva_activa', true);
        $listado = app(ListarPedidosBmaService::class)->presentar($pedido->fresh(), $this->usuario);
        $this->assertFalse((bool) $listado->puede_cerrar_consulta);
        $this->assertSame(CodigosConversionModalidad::MENSAJE_RESERVA, $listado->motivo_bloqueo_cierre);
    }

    public function test_15_segunda_instancia_recibe_409_y_no_pisa_la_reserva(): void
    {
        $pedido = $this->pedido();
        $primera = (string) Str::uuid();
        $this->reservar($pedido, $primera)->assertOk();

        $this->reservar($pedido, (string) Str::uuid())->assertStatus(409)
            ->assertJsonPath('codigo', CodigosConversionModalidad::CODIGO_CONVERSION_EN_CURSO)
            ->assertJsonPath('message', CodigosConversionModalidad::MENSAJE_RESERVA);

        $otro = User::factory()->create(['departamento_id' => $this->departamento->id]);
        $otro->givePermissionTo(Permission::findOrCreate('control_pedidos.crear', 'web'));
        $this->actingAs($otro)->postJson("/control-pedidos/{$pedido->id}/conversion-modalidad/reserva", [
            'instancia_id' => (string) Str::uuid(),
        ])->assertForbidden();

        $this->assertSame($primera, ConversionModalidadEnCursoPedidoBma::payload($pedido->id)?->instancia_id);
        $this->assertSame($this->usuario->id, ConversionModalidadEnCursoPedidoBma::payload($pedido->id)?->usuario_id);
    }

    public function test_16_latido_o_soltar_de_token_ajeno_no_renueva_ni_libera(): void
    {
        $pedido = $this->pedido();
        $propia = (string) Str::uuid();
        $ajena = (string) Str::uuid();
        $this->reservar($pedido, $propia)->assertOk();

        $this->actingAs($this->usuario)->postJson("/control-pedidos/{$pedido->id}/conversion-modalidad/reserva/latido", [
            'instancia_id' => $ajena,
        ])->assertStatus(409)->assertJsonPath('codigo', CodigosConversionModalidad::CODIGO_CONVERSION_EN_CURSO);
        $this->actingAs($this->usuario)->deleteJson("/control-pedidos/{$pedido->id}/conversion-modalidad/reserva", [
            'instancia_id' => $ajena,
        ]);
        $this->assertTrue(ConversionModalidadEnCursoPedidoBma::activa($pedido->id));
        $this->assertSame($propia, ConversionModalidadEnCursoPedidoBma::payload($pedido->id)?->instancia_id);

        $this->actingAs($this->usuario)->postJson("/control-pedidos/{$pedido->id}/conversion-modalidad/reserva/latido", [
            'instancia_id' => $propia,
        ])->assertOk()->assertJsonPath('reserva_activa', true);
        $this->assertSame($propia, ConversionModalidadEnCursoPedidoBma::payload($pedido->id)?->instancia_id);
    }

    public function test_17_cerrar_consulta_con_reserva_activa_devuelve_422_aunque_la_version_sea_actual(): void
    {
        $pedido = $this->pedidoRespondidoAbierto();
        $this->reservar($pedido)->assertOk();
        $version = (int) $pedido->fresh()->historial()->max('id');

        $this->actingAs($this->usuario)->postJson("/control-pedidos/{$pedido->id}/cerrar-consulta", [
            'modalidad_esperada' => 'tienda',
            'version_consulta' => $version,
        ])->assertStatus(422)
            ->assertJsonPath('codigo', CodigosConversionModalidad::CODIGO_CONVERSION_EN_CURSO)
            ->assertJsonPath('message', CodigosConversionModalidad::MENSAJE_RESERVA);

        $this->assertNull($pedido->fresh()->consulta_cerrada_at);
        $this->assertSame('tienda', $pedido->fresh()->solicitud_preparacion_json['modalidad_entrega']);
    }

    public function test_18_confirmar_emite_broadcast_y_cierre_tienda_obsoleto_es_409(): void
    {
        Event::fake([PedidoBmaActualizado::class]);
        $pedido = $this->pedido();
        $this->reservar($pedido)->assertOk();
        $this->confirmar($pedido, ['costo_transporte' => 'diferido', 'costo_envio' => null])->assertOk();

        Event::assertDispatched(
            PedidoBmaActualizado::class,
            fn (PedidoBmaActualizado $evento) => $evento->pedidoId === $pedido->id
                && $evento->accion === CodigosConversionModalidad::ACCION_CONFIRMADA
        );
        $this->assertSame('municipal', $pedido->fresh()->solicitud_preparacion_json['modalidad_entrega']);
        $this->flags($pedido->fresh())->assertOk()
            ->assertJsonPath('conversion_modalidad.origen', 'municipal');

        $this->actingAs($this->usuario)->postJson("/control-pedidos/{$pedido->id}/cerrar-consulta", [
            'modalidad_esperada' => 'tienda',
            'version_consulta' => (int) $pedido->fresh()->historial()->max('id'),
        ])->assertStatus(409)
            ->assertJsonPath('codigo', CodigosConversionModalidad::CODIGO_CONTEXTO_OBSOLETO)
            ->assertJsonPath('message', CodigosConversionModalidad::MENSAJE_CONTEXTO_OBSOLETO);

        $this->actingAs($this->usuario)->postJson("/control-pedidos/{$pedido->id}/cerrar-consulta")
            ->assertStatus(409)
            ->assertJsonPath('codigo', CodigosConversionModalidad::CODIGO_CONTEXTO_OBSOLETO);
        $this->assertNull($pedido->fresh()->consulta_cerrada_at);
    }

    public function test_19_soltar_reserva_de_esa_instancia_restituye_cierre_si_sigue_elegible(): void
    {
        Event::fake([PedidoBmaActualizado::class]);
        $pedido = $this->pedidoRespondidoAbierto();
        $instancia = (string) Str::uuid();
        $this->reservar($pedido, $instancia)->assertOk();
        $this->assertFalse((bool) app(ListarPedidosBmaService::class)->presentar($pedido->fresh(), $this->usuario)->puede_cerrar_consulta);

        $this->actingAs($this->usuario)->deleteJson("/control-pedidos/{$pedido->id}/conversion-modalidad/reserva", [
            'instancia_id' => $instancia,
        ])->assertOk()->assertJsonPath('reserva_activa', false);

        $this->assertFalse(ConversionModalidadEnCursoPedidoBma::activa($pedido->id));
        Event::assertDispatched(
            PedidoBmaActualizado::class,
            fn (PedidoBmaActualizado $evento) => $evento->accion === CodigosConversionModalidad::ACCION_RESERVA_LIBERADA
        );
        $this->assertTrue((bool) app(ListarPedidosBmaService::class)->presentar($pedido->fresh(), $this->usuario)->puede_cerrar_consulta);
        $this->assertSame('tienda', $pedido->fresh()->solicitud_preparacion_json['modalidad_entrega']);
    }

    public function test_20_sin_latido_hasta_ttl_caduca_y_se_puede_adquirir_de_nuevo(): void
    {
        $pedido = $this->pedido();
        $primera = (string) Str::uuid();
        $this->reservar($pedido, $primera)->assertOk();
        $this->assertTrue(Cache::has(ConversionModalidadEnCursoPedidoBma::clave($pedido->id)));

        $this->travel(ConversionModalidadEnCursoPedidoBma::TTL_SEGUNDOS + 1)->seconds();
        $this->assertFalse(ConversionModalidadEnCursoPedidoBma::activa($pedido->id));

        $segunda = (string) Str::uuid();
        $this->reservar($pedido, $segunda)->assertOk()
            ->assertJsonPath('reserva_activa', true);
        $this->assertSame($segunda, ConversionModalidadEnCursoPedidoBma::payload($pedido->id)?->instancia_id);
        $this->assertNotSame($primera, ConversionModalidadEnCursoPedidoBma::payload($pedido->id)?->instancia_id);
    }

    public function test_21_cierre_o_conversion_primero_respeta_limite_y_contexto(): void
    {
        $cierraPrimero = $this->pedidoRespondidoAbierto(['folio' => 'INT-CIERRE']);
        $this->actingAs($this->usuario)->postJson("/control-pedidos/{$cierraPrimero->id}/cerrar-consulta")->assertOk();
        $this->assertNotNull($cierraPrimero->fresh()->consulta_cerrada_at);
        $this->confirmar($cierraPrimero)->assertStatus(422)
            ->assertJsonPath('codigo', CodigosConversionModalidad::CODIGO_CONSULTA_YA_CERRADA);
        $this->assertSame('tienda', $cierraPrimero->fresh()->solicitud_preparacion_json['modalidad_entrega']);

        $conviertePrimero = $this->pedidoRespondidoAbierto(['folio' => 'INT-CONV']);
        $this->reservar($conviertePrimero)->assertOk();
        $this->confirmar($conviertePrimero)->assertOk();
        $this->actingAs($this->usuario)->postJson("/control-pedidos/{$conviertePrimero->id}/cerrar-consulta", [
            'modalidad_esperada' => 'tienda',
            'version_consulta' => (int) $conviertePrimero->fresh()->historial()->max('id'),
        ])->assertStatus(409)->assertJsonPath('codigo', CodigosConversionModalidad::CODIGO_CONTEXTO_OBSOLETO);
        $this->assertSame('municipal', $conviertePrimero->fresh()->solicitud_preparacion_json['modalidad_entrega']);
        $this->assertNull($conviertePrimero->fresh()->consulta_cerrada_at);
    }

    public function test_22_rollback_de_persistencia_limpia_evidencias_sin_huerfanos(): void
    {
        $pedido = $this->pedido();
        $instancia = (string) Str::uuid();
        $this->reservar($pedido, $instancia)->assertOk();
        $this->mock(PersistirConversionModalidad::class, function ($mock) {
            $mock->shouldReceive('confirmar')->andThrow(new \RuntimeException('Fallo de persistencia de conversión.'));
        });
        $respuesta = $this->actingAs($this->usuario)->post(
            "/control-pedidos/{$pedido->id}/conversion-modalidad",
            $this->cuerpoConfirmar($pedido, [
                'instancia_id' => $instancia,
                'error_captura' => false,
                'evidencias' => [UploadedFile::fake()->image('peticion-cliente.jpg')],
            ]),
            ['Accept' => 'application/json']
        );
        $this->assertContains($respuesta->status(), [422, 500]);
        $this->assertSame(0, $pedido->documentos()->where('relacion_tipo', CodigosConversionModalidad::RELACION_TIPO_EVIDENCIA)->count());
        foreach (Storage::disk('public')->allFiles('pedidos_bma/'.$pedido->id) as $ruta) {
            $this->assertStringNotContainsString('conversion', $ruta);
        }
        $this->assertSame('tienda', $pedido->fresh()->solicitud_preparacion_json['modalidad_entrega']);
    }

    public function test_23_reintento_misma_clave_con_version_atrasada_recupera_sin_duplicar(): void
    {
        $pedido = $this->pedido();
        $clave = 'idem-m02-'.uniqid();
        $this->reservar($pedido)->assertOk();
        $primero = $this->confirmar($pedido, ['clave_idempotencia' => $clave, 'costo_transporte' => 'diferido', 'costo_envio' => null]);
        $primero->assertOk()->assertJsonPath('conversion_version', 1);
        $version = (int) $primero->json('conversion_version');
        $revision = (int) $pedido->fresh()->revision_registro;

        $segundo = $this->confirmar($pedido->fresh(), [
            'clave_idempotencia' => $clave,
            'conversion_version' => 0,
            'revision_esperada' => 0,
            'costo_transporte' => 'diferido',
            'costo_envio' => null,
        ]);
        $segundo->assertOk()
            ->assertJsonPath('pedido_id', $pedido->id)
            ->assertJsonPath('conversion_version', $version)
            ->assertJsonPath('modalidad_entrega', 'municipal');
        $this->assertNotSame(409, $segundo->status());
        $this->assertSame(
            1,
            $pedido->fresh()->historial()->where('accion', CodigosConversionModalidad::HISTORIAL_ACCION)->count()
        );
        $this->assertSame($revision, (int) $pedido->fresh()->revision_registro);
        $this->assertSame('municipal', $pedido->fresh()->solicitud_preparacion_json['modalidad_entrega']);
    }

    public function test_24_dos_confirmaciones_distintas_una_persiste_la_otra_409_o_idempotente(): void
    {
        $pedido = $this->pedido();
        $this->reservar($pedido)->assertOk();
        $this->confirmar($pedido, [
            'clave_idempotencia' => 'clave-a-'.uniqid(),
            'costo_transporte' => 'diferido',
            'costo_envio' => null,
        ])->assertOk();

        $segunda = $this->confirmar($pedido->fresh(), [
            'clave_idempotencia' => 'clave-b-'.uniqid(),
            'conversion_version' => 0,
            'costo_transporte' => 'diferido',
            'costo_envio' => null,
        ]);
        $this->assertContains($segunda->status(), [409, 422, 200]);
        if (in_array($segunda->status(), [409, 422], true)) {
            $this->assertContains(
                $segunda->json('codigo'),
                [
                    CodigosConversionModalidad::CODIGO_VERSION_OBSOLETA,
                    CodigosConversionModalidad::CODIGO_NO_ELEGIBLE,
                    CodigosConversionModalidad::CODIGO_CONSULTA_YA_CERRADA,
                    CodigosConversionModalidad::CODIGO_CONTEXTO_OBSOLETO,
                ]
            );
        }
        $this->assertSame(
            1,
            $pedido->fresh()->historial()->where('accion', CodigosConversionModalidad::HISTORIAL_ACCION)->count()
        );
        $this->assertSame('municipal', $pedido->fresh()->solicitud_preparacion_json['modalidad_entrega']);
        $this->assertSame(1, (int) ($pedido->fresh()->solicitud_preparacion_json['conversion_modalidad']['conversion_version'] ?? 0));
    }

    public function test_25_autoguardado_obsoleto_es_409_y_modalidad_municipal_queda_pinneada(): void
    {
        $pedido = $this->pedido();
        $this->reservar($pedido)->assertOk();
        $this->confirmar($pedido, ['costo_transporte' => 'diferido', 'costo_envio' => null])->assertOk();
        $fresco = $pedido->fresh();

        $this->actingAs($this->usuario)->postJson('/control-pedidos/autoguardar', [
            'pedido_id' => $fresco->id,
            'revision_esperada' => 0,
            'base_captura' => str_repeat('a', 64),
            'solicitud_preparacion' => ['modalidad_entrega' => 'tienda'],
        ])->assertConflict()->assertJsonPath('codigo', 'conflicto_captura');

        $this->actingAs($this->usuario)->postJson('/control-pedidos/autoguardar', $this->captura($fresco, [
            'solicitud_preparacion' => ['modalidad_entrega' => 'tienda', 'area_revision' => 'preparacion_tienda'],
        ]))->assertOk();
        $this->assertSame('municipal', $pedido->fresh()->solicitud_preparacion_json['modalidad_entrega']);
    }

    public function test_26_t03_pendiente_conexion_continua_si_abierta_y_se_conserva_si_ya_hubo_cierre(): void
    {
        $abierta = $this->pedidoRespondidoAbierto(['folio' => 'INT-T03A']);
        $this->marcarT03($abierta, 'municipal');
        $flagsAbierta = $this->flags($abierta)->assertOk();
        $flagsAbierta->assertJsonPath('conversion_modalidad.puede_continuar', true)
            ->assertJsonPath('conversion_modalidad.accion_ui', CodigosConversionModalidad::ACCION_UI_CONTINUAR)
            ->assertJsonPath('conversion_modalidad.solicitud_pendiente.fuente', CodigosConversionModalidad::FUENTE_T03)
            ->assertJsonPath('conversion_modalidad.solicitud_pendiente.estado', CodigosConversionModalidad::ESTADO_T03_PENDIENTE);
        $this->reservar($abierta)->assertOk();
        $this->confirmar($abierta)->assertOk();
        $this->assertSame('municipal', $abierta->fresh()->solicitud_preparacion_json['modalidad_entrega']);
        $this->assertNotSame(
            'pendiente_conexion',
            $abierta->fresh()->solicitud_preparacion_json['cambio_modalidad_tienda']['estado'] ?? 'pendiente_conexion'
        );

        $cerrada = $this->pedidoRespondidoAbierto(['folio' => 'INT-T03C']);
        $this->marcarT03($cerrada, 'municipal');
        $this->historialCierre($cerrada);
        $cerrada->update(['consulta_cerrada_at' => now()]);
        $this->flags($cerrada)->assertOk()
            ->assertJsonPath('conversion_modalidad.puede_iniciar', false)
            ->assertJsonPath('conversion_modalidad.puede_continuar', false)
            ->assertJsonPath('conversion_modalidad.solicitud_pendiente.estado', CodigosConversionModalidad::ESTADO_T03_PENDIENTE);
        $this->confirmar($cerrada)->assertStatus(422)
            ->assertJsonPath('codigo', CodigosConversionModalidad::CODIGO_CONSULTA_YA_CERRADA);
        $this->assertSame('tienda', $cerrada->fresh()->solicitud_preparacion_json['modalidad_entrega']);
        $this->assertSame(
            'pendiente_conexion',
            $cerrada->fresh()->solicitud_preparacion_json['cambio_modalidad_tienda']['estado'] ?? null
        );
    }

    public function test_27_sin_permiso_es_403_y_el_historial_de_conversion_no_pisa_el_anterior(): void
    {
        $ajeno = User::factory()->create(['departamento_id' => $this->departamento->id]);
        $pedido = $this->pedido();
        $cuerpo = $this->cuerpoConfirmar($pedido);
        $this->actingAs($ajeno)->postJson("/control-pedidos/{$pedido->id}/conversion-modalidad/reserva", [
            'instancia_id' => (string) Str::uuid(),
        ])->assertForbidden();
        $this->actingAs($ajeno)->post(
            "/control-pedidos/{$pedido->id}/conversion-modalidad",
            $cuerpo,
            ['Accept' => 'application/json']
        )->assertForbidden();

        $previa = [
            'estado' => CodigosConversionModalidad::ESTADO_CONFIRMADA,
            'origen' => CodigosConversionModalidad::DESTINO_MUNICIPAL,
            'destino' => CodigosConversionModalidad::DESTINO_TIENDA,
            'conversion_version' => 1,
            'clave_idempotencia' => 'previa-m02',
            'historial' => [],
        ];
        $json = $pedido->solicitud_preparacion_json;
        $json['conversion_modalidad'] = $previa;
        $pedido->update(['solicitud_preparacion_json' => $json]);

        $this->reservar($pedido)->assertOk();
        $this->confirmar($pedido, [
            'conversion_version' => 1,
            'costo_transporte' => 'diferido',
            'costo_envio' => null,
        ])->assertOk()->assertJsonPath('conversion_version', 2);

        $vigente = $pedido->fresh()->solicitud_preparacion_json['conversion_modalidad'] ?? [];
        $this->assertSame(CodigosConversionModalidad::ESTADO_CONFIRMADA, $vigente['estado'] ?? null);
        $this->assertSame('municipal', $vigente['destino'] ?? null);
        $this->assertSame(2, (int) ($vigente['conversion_version'] ?? 0));
        $this->assertNotEmpty($vigente['historial'] ?? []);
        $this->assertSame('tienda', $vigente['historial'][0]['destino'] ?? null);
        $this->assertSame(1, (int) ($vigente['historial'][0]['conversion_version'] ?? 0));
    }

    public function test_28_nacional_y_tienda_permanecen_inactivos_t03_nacional_sigue_pending(): void
    {
        $this->assertTrue((new PoliticaConversionMunicipal)->activo());
        $this->assertFalse((new PoliticaConversionTienda)->activo());
        $this->assertFalse((new PoliticaConversionNacional)->activo());
        $dispatcher = new DispatcherPoliticasConversion;
        $this->assertFalse($dispatcher->porDestino(CodigosConversionModalidad::DESTINO_NACIONAL)->activo());
        $this->assertFalse($dispatcher->porDestino(CodigosConversionModalidad::DESTINO_TIENDA)->activo());

        $pedido = $this->pedido();
        $this->marcarT03($pedido, 'nacional');
        $flags = $this->flags($pedido)->assertOk();
        $destinos = collect($flags->json('conversion_modalidad.destinos'));
        $this->assertTrue((bool) $destinos->firstWhere('codigo', 'municipal')['habilitado']);
        $this->assertFalse((bool) $destinos->firstWhere('codigo', 'nacional')['habilitado']);
        $this->assertFalse((bool) $destinos->firstWhere('codigo', 'tienda')['habilitado']);
        $this->assertSame('Envío nacional', $destinos->firstWhere('codigo', 'nacional')['nombre']);
        $this->assertSame('pendiente_conexion', $pedido->fresh()->solicitud_preparacion_json['cambio_modalidad_tienda']['estado']);
        $this->assertSame('tienda', $pedido->fresh()->solicitud_preparacion_json['modalidad_entrega']);

        $nacional = $this->confirmar($pedido, ['destino' => 'nacional']);
        $nacional->assertStatus(422);
        $this->assertTrue(
            $nacional->json('codigo') === CodigosConversionModalidad::CODIGO_DESTINO_INACTIVO
            || is_array($nacional->json('errors.destino'))
        );
        $this->assertSame('tienda', $pedido->fresh()->solicitud_preparacion_json['modalidad_entrega']);
        $this->assertSame('nacional', $pedido->fresh()->solicitud_preparacion_json['cambio_modalidad_tienda']['destino'] ?? null);
    }

    public function test_29_alta_m01_sigue_bloqueando_pagado_y_cierre_sin_campos_nuevos_vale_sin_conversion(): void
    {
        $pagado = $this->pedido([
            'folio' => 'M01-PAG',
            'pago_validado_at' => now(),
            'solicitud_preparacion_json' => ['version' => 1, 'modalidad_entrega' => 'municipal'],
        ]);
        $this->actingAs($this->usuario)->postJson(route('control_pedidos.municipal.solicitud', $pagado), [
            'catalogo_paqueteria_id' => $this->jaguar->id,
            'consignatario_id' => $this->consignatario($pagado),
            'leyenda_terminal' => 'PAGADO',
            'costo_transporte' => 'diferido',
        ])->assertStatus(422);

        $remisionado = $this->pedido([
            'folio' => 'M01-REM',
            'numero_remision' => 'REM-M01',
            'solicitud_preparacion_json' => ['version' => 1, 'modalidad_entrega' => 'municipal'],
        ]);
        $this->actingAs($this->usuario)->postJson(route('control_pedidos.municipal.solicitud', $remisionado), [
            'catalogo_paqueteria_id' => $this->jaguar->id,
            'consignatario_id' => $this->consignatario($remisionado),
            'leyenda_terminal' => 'PAGADO',
            'costo_transporte' => 'diferido',
        ])->assertStatus(422);

        $sinConversion = $this->pedidoRespondidoAbierto(['folio' => 'INT-CIERRE-M01']);
        $this->actingAs($this->usuario)->postJson("/control-pedidos/{$sinConversion->id}/cerrar-consulta")->assertOk();
        $this->assertNotNull($sinConversion->fresh()->consulta_cerrada_at);
        $this->assertSame('tienda', $sinConversion->fresh()->solicitud_preparacion_json['modalidad_entrega']);
        $this->assertArrayNotHasKey('conversion_modalidad', $sinConversion->fresh()->solicitud_preparacion_json);
    }

    private function flags(PedidoBma $pedido): TestResponse
    {
        return $this->actingAs($this->usuario)->getJson("/control-pedidos/{$pedido->id}/conversion-modalidad");
    }

    /**
     * @param  array<string, mixed>  $captura
     */
    private function vistaPrevia(PedidoBma $pedido, array $captura = []): TestResponse
    {
        return $this->actingAs($this->usuario)->postJson(
            "/control-pedidos/{$pedido->id}/conversion-modalidad/vista-previa",
            array_merge([
                'destino' => CodigosConversionModalidad::DESTINO_MUNICIPAL,
                'instancia_id' => $this->instanciaActual ?? (string) Str::uuid(),
            ], $captura)
        );
    }

    private function reservar(PedidoBma $pedido, ?string $instancia = null): TestResponse
    {
        $this->instanciaActual = $instancia ?? (string) Str::uuid();

        return $this->actingAs($this->usuario)->postJson("/control-pedidos/{$pedido->id}/conversion-modalidad/reserva", [
            'instancia_id' => $this->instanciaActual,
        ]);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function confirmar(PedidoBma $pedido, array $extra = []): TestResponse
    {
        return $this->actingAs($this->usuario)->post(
            "/control-pedidos/{$pedido->id}/conversion-modalidad",
            $this->cuerpoConfirmar($pedido, $extra),
            ['Accept' => 'application/json']
        );
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function cuerpoConfirmar(PedidoBma $pedido, array $extra = []): array
    {
        $pedido = $pedido->fresh();

        return array_merge([
            'destino' => CodigosConversionModalidad::DESTINO_MUNICIPAL,
            'instancia_id' => $this->instanciaActual ?? (string) Str::uuid(),
            'motivo' => 'El cliente pide envío municipal',
            'error_captura' => true,
            'catalogo_paqueteria_id' => $this->jaguar->id,
            'consignatario_id' => $this->consignatario($pedido),
            'almacen_id' => null,
            'leyenda_terminal' => 'PAGADO',
            'costo_transporte' => 'conocido',
            'costo_envio' => '150.00',
            'revision_esperada' => (int) $pedido->revision_registro,
            'conversion_version' => (int) ($pedido->solicitud_preparacion_json['conversion_modalidad']['conversion_version'] ?? 0),
            'clave_idempotencia' => 'idem-m02-'.uniqid(),
        ], $extra);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function pedido(array $extra = []): PedidoBma
    {
        $listaId = DB::table('catalogo_listas_descuento')->insertGetId([
            'nombre' => 'Lista M02 '.uniqid(),
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
        $sucursal = Sucursal::factory()->create(['activo' => true]);

        return PedidoBma::query()->create(array_merge([
            'folio' => 'INT-'.uniqid(),
            'folio_remision' => 'FOL-211',
            'fecha' => now()->toDateString(),
            'vendedor_id' => $this->usuario->id,
            'cliente_id' => $cliente->id,
            'departamento_operacion_id' => $this->departamento->id,
            'origen_id' => $origen->id,
            'almacen_id' => $salida->id,
            'sucursal_destino_id' => $sucursal->id,
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

    /**
     * @param  array<string, mixed>  $extra
     */
    private function pedidoRespondidoAbierto(array $extra = []): PedidoBma
    {
        $revision = $extra['revision'] ?? [
            'estado' => 'sin_excepciones',
            'vigente' => true,
            'enviada_at' => '2026-11-12T08:00:00-06:00',
        ];
        unset($extra['revision']);
        $json = $extra['solicitud_preparacion_json'] ?? [
            'version' => 1,
            'modalidad_entrega' => 'tienda',
            'area_revision' => 'control_pedidos',
            'revision_disponibilidad' => $revision,
        ];
        unset($extra['solicitud_preparacion_json']);

        $pedido = $this->pedido(array_merge([
            'solicitud_preparacion_json' => $json,
        ], $extra));
        $this->documento($pedido);

        return $pedido;
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

    private function consignatario(PedidoBma $pedido): int
    {
        if (isset($this->consignatarios[$pedido->id])) {
            return $this->consignatarios[$pedido->id];
        }
        $id = $this->actingAs($this->usuario)->postJson(
            route('control_pedidos.municipal.expediente.store', $pedido->cliente_id),
            ['nombre' => 'Persona ficticia', 'telefono' => '3311111111', 'pedido_id' => $pedido->id]
        )->assertCreated()->json('id');
        $this->consignatarios[$pedido->id] = (int) $id;

        return (int) $id;
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function pagar(PedidoBma $pedido, array $datos): PedidoBmaPago
    {
        $banco = CatalogoBanco::query()->firstOrCreate(
            ['nombre' => 'Banco M02'],
            ['activo' => true, 'compartido' => true]
        );

        return app(RegistrarPagoPedidoBmaService::class)->handle(
            $pedido->fresh(),
            array_merge([
                'monto' => 100,
                'forma_pago' => 'transferencia',
                'catalogo_banco_id' => $banco->id,
                'idempotency_key' => 'pago-m02-'.uniqid(),
            ], $datos),
            UploadedFile::fake()->create('comprobante.pdf', 20, 'application/pdf'),
            $this->usuario->id
        );
    }

    private function historialCierre(PedidoBma $pedido): void
    {
        app(RegistrarHistorialPedidoService::class)->ejecutar(
            $pedido->id,
            $this->usuario->id,
            (int) $pedido->catalogo_estatus_pedido_id,
            (int) $pedido->catalogo_estatus_pedido_id,
            'Consulta cerrada (ficticia).',
            AccionesHistorialPedidoBma::CIERRE_CONSULTA
        );
    }

    private function custodia(PedidoBma $pedido): ResguardoPdv
    {
        $this->pdv->update(['sucursal_id' => $pedido->sucursal_destino_id]);

        return ResguardoPdv::query()->create([
            'pedido_bma_id' => $pedido->id,
            'cliente_id' => $pedido->cliente_id,
            'sucursal_id' => $pedido->sucursal_destino_id,
            'almacen_id' => $this->pdv->id,
            'estado' => ResguardoPdv::ESTADO_EN_CUSTODIA,
            'entrega_bloqueada' => false,
            'snapshot_folio' => $pedido->folio,
            'snapshot_cliente_nombre' => 'Cliente ficticio',
            'version' => 1,
        ]);
    }

    private function marcarT03(PedidoBma $pedido, string $destino): void
    {
        $json = $pedido->solicitud_preparacion_json;
        $json['cambio_modalidad_tienda'] = [
            'estado' => CodigosConversionModalidad::ESTADO_T03_PENDIENTE,
            'destino' => $destino,
            'motivo' => 'El cliente pide otro tipo de entrega',
            'solicitada_por_id' => $this->usuario->id,
            'solicitada_at' => now()->toIso8601String(),
        ];
        $pedido->update(['solicitud_preparacion_json' => $json]);
    }
}
