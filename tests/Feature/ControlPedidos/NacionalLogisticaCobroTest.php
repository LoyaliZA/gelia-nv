<?php

namespace Tests\Feature\ControlPedidos;

use App\Events\PedidoBmaActualizado;
use App\Models\Almacen;
use App\Models\Cliente;
use App\Models\ClienteDireccion;
use App\Models\ControlPedidos\CatalogoTipoGuiaPedido;
use App\Models\SaldosAFavor\PedidoBmaPago;
use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\CatalogoOrigenPedido;
use App\Models\ControlPedidos\CatalogoPaqueteriaPedido;
use App\Models\ControlPedidos\CatalogoTipoCajaPedido;
use App\Models\ControlPedidos\CatalogoZonaPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaCaja;
use App\Models\ControlPedidos\PedidoBmaCajaRemision;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Models\User;
use App\Services\ControlPedidos\AsignarGuiaPedidoBmaService;
use App\Services\ControlPedidos\AprobarPedidoBmaService;
use App\Services\ControlPedidos\ValidarPagoPedidoBmaService;
use App\Services\ControlPedidos\GestionarGuiaPdfPedidoBmaService;
use App\Services\SaldosAFavor\CoberturaPagoPedidoBmaService;
use App\Services\ControlPedidos\Direcciones\InvalidarGuiaPorCambioDireccion;
use App\Services\ControlPedidos\GestionarRemisionPedidoBmaService;
use App\Services\ControlPedidos\MarcarEnviadoPedidoBmaService;
use App\Services\ControlPedidos\Nacional\CambiarPaqueteriaNacional;
use App\Services\ControlPedidos\NotificarPedidoBmaService;
use App\Services\SaldosAFavor\RegistrarPagoPedidoBmaService;
use App\Support\ControlPedidos\RevisionCapturaPedido;
use App\Support\ControlPedidos\Nacional\GuiasPdfPorCaja;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class NacionalLogisticaCobroTest extends TestCase
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
            CatalogoEstatusPedido::FASE_PENDIENTE_AUXILIAR => ['AUX_NAC', 'Auxiliar', 4],
            CatalogoEstatusPedido::FASE_EN_CEDIS => ['CEDIS_NAC', 'CEDIS', 5],
            CatalogoEstatusPedido::FASE_PENDIENTE_DE_GUIA => ['GUIA_NAC', 'Guía', 6],
            CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO => ['ENVIO_NAC', 'Envío', 7],
            CatalogoEstatusPedido::FASE_ENVIADO => ['ENVIADO_NAC', 'Enviado', 8],
        ] as $fase => [$codigo, $nombre, $orden]) {
            CatalogoEstatusPedido::query()->firstOrCreate(
                ['fase_ciclo' => $fase],
                ['codigo_interno' => $codigo, 'nombre_visual' => $nombre, 'color_hex' => '#999999', 'orden' => $orden, 'activo' => true]
            );
        }
    }

    public function test_invalidar_guia_limpia_la_caja_y_deja_el_pdf_fuera_de_la_vigente(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('guias/pedido.pdf', 'pedido');
        Storage::disk('public')->put('guias/caja.pdf', 'caja');
        $pedido = $this->pedido(CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO, ['numero_rastreo' => 'PED-1']);
        $caja = $this->caja($pedido, ['numero_rastreo' => 'CAJ-1', 'costo_envio' => 80]);
        $guiaPedido = $this->guia($pedido, 'guias/pedido.pdf', 'guia-pedido.pdf');
        $guiaCaja = $this->guia($pedido, 'guias/caja.pdf', 'guia-caja.pdf', $caja->id);

        $actual = app(InvalidarGuiaPorCambioDireccion::class)->ejecutar($pedido->fresh(), $this->usuario->id, 'Domicilio corregido');

        $this->assertNull($actual->numero_rastreo);
        $this->assertNull($caja->fresh()->numero_rastreo);
        $this->assertFalse($guiaPedido->fresh()->activo);
        $this->assertFalse($guiaCaja->fresh()->activo);
        $this->assertSame('guias/pedido.pdf', $guiaPedido->fresh()->ruta_archivo);
        $this->assertSame('guias/caja.pdf', $guiaCaja->fresh()->ruta_archivo);
        Storage::disk('public')->assertExists('guias/pedido.pdf');
        Storage::disk('public')->assertExists('guias/caja.pdf');
        $this->assertStringContainsString('INVALIDADA', (string) $guiaPedido->fresh()->getRawOriginal('nombre_original'));
        $this->assertFalse((bool) data_get($actual->solicitud_preparacion_json, 'invalidacion_guia.cancelacion_paqueteria_confirmada'));
        $this->assertSame(80.0, (float) $caja->fresh()->costo_envio);
    }

    public function test_la_cancelacion_externa_no_vuelve_a_mover_la_guia(): void
    {
        $pedido = $this->pedido(CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO, ['numero_rastreo' => 'PED-2']);
        $caja = $this->caja($pedido, ['numero_rastreo' => 'CAJ-2']);
        $guia = $this->guia($pedido, 'guias/pedido.pdf', 'guia.pdf');
        $invalidada = app(InvalidarGuiaPorCambioDireccion::class)->ejecutar($pedido->fresh(), $this->usuario->id, 'Cambio de domicilio');

        $confirmada = app(InvalidarGuiaPorCambioDireccion::class)->confirmarCancelacionExterna($invalidada, $this->usuario->id);

        $this->assertTrue((bool) data_get($confirmada->solicitud_preparacion_json, 'invalidacion_guia.cancelacion_paqueteria_confirmada'));
        $this->assertNull($confirmada->numero_rastreo);
        $this->assertNull($caja->fresh()->numero_rastreo);
        $this->assertFalse($guia->fresh()->activo);
        $this->assertSame('Cambio de domicilio', data_get($confirmada->solicitud_preparacion_json, 'invalidacion_guia.motivo'));
    }

    public function test_la_guia_invalidada_no_autoriza_la_salida_de_la_caja(): void
    {
        $pedido = $this->pedido(CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO, [
            'numero_rastreo' => 'PED-3',
            'empacado_at' => now(),
        ]);
        $caja = $this->caja($pedido, ['numero_rastreo' => 'CAJ-3', 'peso_real_kg' => 1]);
        $this->guia($pedido, 'guias/vieja.pdf', 'vieja.pdf', $caja->id, false);

        $rechazo = null;
        try {
            app(MarcarEnviadoPedidoBmaService::class)->ejecutar($pedido->fresh(), $this->usuario->id);
        } catch (\RuntimeException $e) {
            if ($e instanceof \PHPUnit\Framework\AssertionFailedError) {
                throw $e;
            }
            $rechazo = $e;
        }
        $this->assertNotNull($rechazo);
        $this->assertStringContainsString('vigente', $rechazo->getMessage());
        $this->assertSame(CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO, $pedido->fresh()->estatus->fase_ciclo);
        $this->assertNotSame(PedidoBmaCaja::ESTATUS_RECOLECTADA, $caja->fresh()->estatus_recoleccion);
    }

    public function test_cambiar_paqueteria_despues_de_salida_conserva_guia_y_costo(): void
    {
        $pedido = $this->pedido(CatalogoEstatusPedido::FASE_ENVIADO, ['numero_rastreo' => 'SAL-1', 'costo_envio' => 90]);
        $caja = $this->caja($pedido, ['numero_rastreo' => 'SAL-C', 'costo_envio' => 90, 'estatus_recoleccion' => PedidoBmaCaja::ESTATUS_RECOLECTADA]);
        $otra = $this->paqueteria('Estafeta');

        $rechazo = $this->rechazo(fn () => app(CambiarPaqueteriaNacional::class)->ejecutar($pedido->fresh(), $this->contexto($pedido, $otra->id)));
        $this->assertNotNull($rechazo);
        $this->assertStringContainsString('salida', $rechazo->getMessage());
        $fresco = $pedido->fresh();
        $this->assertSame($pedido->catalogo_paqueteria_id, $fresco->catalogo_paqueteria_id);
        $this->assertSame('SAL-1', $fresco->numero_rastreo);
        $this->assertSame('SAL-C', $caja->fresh()->numero_rastreo);
        $this->assertSame(90.0, (float) $fresco->costo_envio);
    }

    public function test_cambiar_paqueteria_antes_de_salida_invalida_guia_y_permite_costo_cero(): void
    {
        $pedido = $this->pedido(CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO, [
            'numero_rastreo' => 'PRE-1',
            'pago_validado_at' => now(),
            'pago_validado_por_id' => $this->usuario->id,
            'costo_envio' => 80,
        ]);
        $caja = $this->caja($pedido, ['numero_rastreo' => 'PRE-C', 'costo_envio' => 80, 'peso_real_kg' => 1]);
        $guia = $this->guia($pedido, 'guias/pre.pdf', 'pre.pdf', $caja->id);
        $otra = $this->paqueteria('FedEx');
        $contexto = $this->contexto($pedido, $otra->id, [['uuid_operativo' => $caja->uuid_operativo, 'costo_envio' => 0]]);

        $conCero = app(CambiarPaqueteriaNacional::class)->ejecutar($pedido->fresh(), $contexto);

        $this->assertSame($otra->id, $conCero->catalogo_paqueteria_id);
        $this->assertSame('0.00', number_format((float) $caja->fresh()->costo_envio, 2, '.', ''));
        $this->assertNull($conCero->numero_rastreo);
        $this->assertNull($caja->fresh()->numero_rastreo);
        $this->assertFalse($guia->fresh()->activo);
        $this->assertNull($conCero->pago_validado_at);

        $valido = $this->contexto($conCero, $otra->id, [
            ['uuid_operativo' => $caja->uuid_operativo, 'costo_envio' => 95],
        ]);
        $aplicada = app(CambiarPaqueteriaNacional::class)->ejecutar($conCero->fresh(), $valido);
        $reintento = app(CambiarPaqueteriaNacional::class)->ejecutar($aplicada->fresh(), [
            ...$this->contexto($aplicada->fresh(), $pedido->catalogo_paqueteria_id, [
                ['uuid_operativo' => $caja->uuid_operativo, 'costo_envio' => 10],
            ]),
            'solicitud_id' => $valido['solicitud_id'],
        ]);

        $this->assertSame($otra->id, $aplicada->catalogo_paqueteria_id);
        $this->assertSame(95.0, (float) $caja->fresh()->costo_envio);
        $this->assertSame($otra->id, $reintento->catalogo_paqueteria_id);
        $this->assertSame((int) $aplicada->revision_registro, (int) $reintento->revision_registro);
    }

    public function test_paq_pendiente_conserva_el_costo_y_no_asigna_guia(): void
    {
        $pedido = $this->pedido(CatalogoEstatusPedido::FASE_PENDIENTE_DE_GUIA, ['costo_envio' => 70]);
        $caja = $this->caja($pedido, ['costo_envio' => 70, 'peso_real_kg' => 1]);
        $pendiente = CatalogoPaqueteriaPedido::query()->firstOrCreate(
            ['nombre' => CatalogoPaqueteriaPedido::NOMBRE_PENDIENTE],
            [
                'categoria' => CatalogoPaqueteriaPedido::CATEGORIA_COMERCIAL,
                'requiere_peso' => true,
                'activo' => true,
                'modalidad_tarifa' => CatalogoPaqueteriaPedido::MODALIDAD_FIJA,
            ]
        );

        $actual = app(CambiarPaqueteriaNacional::class)->ejecutar(
            $pedido->fresh(),
            $this->contexto($pedido, $pendiente->id)
        );

        $this->assertTrue($actual->tienePaqueteriaPendiente());
        $this->assertSame(70.0, (float) $caja->fresh()->costo_envio);
        $this->assertNotSame(0.0, (float) $actual->costo_envio);
        $rechazo = $this->rechazo(fn () => app(AsignarGuiaPedidoBmaService::class)->ejecutar($actual->fresh(), 'GUIA-NO', $this->usuario->id));
        $this->assertNotNull($rechazo);
        $this->assertStringContainsString('PAQ. PENDIENTE', $rechazo->getMessage());
        $this->assertNull($actual->fresh()->numero_rastreo);
    }

    public function test_liquida_solo_mercancia_y_cobra_solo_el_envio_al_elegir_paqueteria(): void
    {
        Storage::fake('public');
        $this->activarDetalle();
        $pendiente = CatalogoPaqueteriaPedido::query()->updateOrCreate(['nombre' => 'PAQ. PENDIENTE'], [
            'categoria' => 'local_regional', 'requiere_peso' => false, 'sin_logistica' => true, 'activo' => true,
        ]);
        $cliente = Cliente::query()->create([
            'numero_cliente' => 'R040001', 'nombre' => 'Cliente ficticio', 'vendedor_id' => $this->usuario->id,
            'lista_actual_id' => DB::table('catalogo_listas_descuento')->insertGetId(['nombre' => 'Lista R04', 'activo' => true]),
        ]);
        $pedido = $this->pedido(CatalogoEstatusPedido::FASE_PENDIENTE_AUXILIAR, [
            'catalogo_paqueteria_id' => $pendiente->id, 'cliente_id' => $cliente->id,
            'costo_envio' => null, 'consulta_cerrada_at' => now(), 'pesaje_solicitado_at' => now(),
            'pesaje_respondido_at' => now(), 'peso_real_kg' => 1.5,
        ]);
        $caja = $this->caja($pedido, ['peso_real_kg' => 1.5]);
        $pdf = fn () => UploadedFile::fake()->create('remision-r04.pdf', 10, 'application/pdf');
        $remisiones = app(GestionarRemisionPedidoBmaService::class);
        $this->assertStringContainsString('mercancía', $this->rechazo(
            fn () => $remisiones->actualizarNumeroRemision($pedido->fresh(), 'REM-R04', $this->usuario->id)
        )->getMessage());

        $pagos = app(RegistrarPagoPedidoBmaService::class);
        $pagoMercancia = $pagos->handle($pedido->fresh(), ['monto' => 100, 'forma_pago' => 'efectivo'], $pdf(), $this->usuario->id);
        app(ValidarPagoPedidoBmaService::class)->ejecutar($pedido->fresh(), $this->usuario->id);
        $remisiones->actualizarNumeroRemision($pedido->fresh(), 'REM-R04', $this->usuario->id);
        $remisiones->subir($pedido->fresh(), $pdf(), $this->usuario->id);
        $enCustodia = app(AprobarPedidoBmaService::class)->ejecutar($pedido->fresh(), $this->usuario->id);
        $this->assertSame(CatalogoEstatusPedido::FASE_EN_CEDIS, $enCustodia->estatus->fase_ciclo);
        $this->assertNotNull(data_get($enCustodia->solicitud_preparacion_json, 'pago_solo_mercancia_nacional.validado_at'));
        $this->assertStringContainsString('paquetería', $this->rechazo(
            fn () => app(GestionarGuiaPdfPedidoBmaService::class)->subir($enCustodia->fresh(), $pdf(), $this->usuario->id)
        )->getMessage());

        $dhl = $this->paqueteria('DHL');
        $this->actingAs($this->usuario)->postJson('/control-pedidos/'.$pedido->id.'/paqueteria-nacional', $this->contexto($enCustodia, $dhl->id))
            ->assertOk()->assertJsonPath('mercancia_pagada', true)
            ->assertJsonPath('estatus.fase_ciclo', CatalogoEstatusPedido::FASE_PENDIENTE_AUXILIAR);
        $this->assertStringContainsString('cotización', $this->rechazo(
            fn () => app(ValidarPagoPedidoBmaService::class)->ejecutar($pedido->fresh(), $this->usuario->id)
        )->getMessage());

        $direccion = ClienteDireccion::query()->create([
            'cliente_id' => $cliente->id, 'numero_direccion' => 1, 'tipo_direccion' => ClienteDireccion::TIPO_ENVIO,
            'nombre_destinatario' => 'Cliente ficticio', 'telefono_destinatario' => '5551234567',
            'calle' => 'Calle ficticia', 'numero_exterior' => '1', 'colonia' => 'Centro',
            'municipio' => 'Centro', 'ciudad' => 'Centro', 'estado' => 'Tabasco', 'codigo_postal' => '86000',
            'pais' => 'México', 'esta_activa' => true, 'estado_verificacion' => ClienteDireccion::ESTADO_VERIFIED,
            'origen' => ClienteDireccion::ORIGEN_MANUAL,
        ]);
        $zona = CatalogoZonaPedido::query()->create(['nombre' => 'Sin cargo R04', 'activo' => true, 'costo_adicional' => 0]);
        $tipoGuia = CatalogoTipoGuiaPedido::query()->create(['nombre' => 'Guía R04', 'activo' => true]);
        $this->postJson('/control-pedidos/'.$pedido->id.'/paqueteria-nacional', [
            ...$this->contexto($pedido->fresh(), $dhl->id, [['uuid_operativo' => $caja->uuid_operativo, 'costo_envio' => 60]]),
            'cotizacion' => [
                'cliente_direccion_id' => $direccion->id, 'catalogo_zona_id' => $zona->id,
                'catalogo_tipo_guia_id' => $tipoGuia->id,
            ],
        ])->assertOk()->assertJsonPath('costo_envio', '60.00')->assertJsonPath('numero_remision', 'REM-R04');

        $pedido->refresh();
        $cobertura = app(CoberturaPagoPedidoBmaService::class);
        $this->assertSame('60.00', $cobertura->calcular($pedido)['pendiente']);
        $this->assertSame(1, $pedido->pagosExhibicion()->count());
        $this->assertStringContainsString('cobertura', $this->rechazo(
            fn () => app(GestionarGuiaPdfPedidoBmaService::class)->subir($pedido->fresh(), $pdf(), $this->usuario->id)
        )->getMessage());
        $this->assertStringContainsString('cobertura', $this->rechazo(
            fn () => $remisiones->actualizarNumeroRemision($pedido->fresh(), 'REM-DUPLICADA', $this->usuario->id)
        )->getMessage());
        $this->assertSame('REM-R04', $pedido->fresh()->numero_remision);

        $pagos->handle($pedido->fresh(), ['monto' => 60, 'forma_pago' => 'efectivo'], $pdf(), $this->usuario->id);
        app(ValidarPagoPedidoBmaService::class)->ejecutar($pedido->fresh(), $this->usuario->id);
        $listo = app(AprobarPedidoBmaService::class)->ejecutar($pedido->fresh(), $this->usuario->id);
        $this->assertTrue($cobertura->calcular($listo)['cubierto']);
        $this->assertSame('REM-R04', $listo->numero_remision);
        $this->assertSame(PedidoBmaPago::REVISION_VERIFICADO, $pagoMercancia->fresh()->estado_revision);
        $this->assertSame(2, $listo->pagosExhibicion()->count());
    }

    public function test_la_salida_rechaza_cajas_con_guia_si_el_flete_nacional_sigue_sin_cobertura(): void
    {
        $dhl = $this->paqueteria('DHL');
        $pedido = $this->pedido(CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO, [
            'catalogo_paqueteria_id' => $dhl->id, 'numero_rastreo' => 'GUIA-PRUEBA',
            'costo_envio' => 60, 'empacado_at' => now(), 'consulta_cerrada_at' => now(),
        ]);
        $json = $pedido->solicitud_preparacion_json;
        $json['pago_solo_mercancia_nacional'] = ['validado_at' => now()->toIso8601String()];
        $json['cotizacion_nacional'] = ['paqueteria_id' => $dhl->id];
        $pedido->update(['solicitud_preparacion_json' => $json]);
        $caja = $this->caja($pedido, ['numero_rastreo' => 'GUIA-CAJA', 'costo_envio' => 60, 'peso_real_kg' => 1]);
        $this->guia($pedido, 'guias/prueba.pdf', 'prueba.pdf', $caja->id);

        $rechazo = $this->rechazo(fn () => app(MarcarEnviadoPedidoBmaService::class)->ejecutar($pedido->fresh(), $this->usuario->id));

        $this->assertNotNull($rechazo);
        $this->assertStringContainsString('cobertura', $rechazo->getMessage());
        $this->assertFalse($caja->fresh()->estaRecolectada());
        $this->assertSame(CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO, $pedido->fresh()->estatus->fase_ciclo);
    }

    public function test_la_cotizacion_nacional_no_cobra_flete_y_conserva_reexpedicion(): void
    {
        $this->activarDetalle();
        $dhl = $this->paqueteria('DHL');
        $zona = CatalogoZonaPedido::query()->updateOrCreate(
            ['nombre' => 'Con reexpedición'],
            ['costo_adicional' => 150, 'activo' => true]
        );
        $pedido = $this->pedido(CatalogoEstatusPedido::FASE_PENDIENTE_DE_GUIA, [
            'catalogo_paqueteria_id' => $dhl->id,
            'catalogo_zona_id' => $zona->id,
            'total_mercancia' => 1000,
            'aplica_seguro' => true,
            'costo_envio' => 1,
            'costo_seguro' => 0,
        ]);
        $caja = $this->caja($pedido, ['costo_envio' => 1, 'peso_real_kg' => 2]);

        $actual = app(CambiarPaqueteriaNacional::class)->ejecutar($pedido->fresh(), $this->contexto($pedido, $dhl->id, [
            ['uuid_operativo' => $caja->uuid_operativo, 'costo_envio' => 0],
        ], true));

        $this->assertSame('0.00', number_format((float) $caja->fresh()->costo_envio, 2, '.', ''));
        $this->assertSame('71.00', number_format((float) $actual->costo_seguro, 2, '.', ''));
        $this->assertSame('150.00', number_format((float) $actual->costo_envio, 2, '.', ''));
    }

    public function test_capturar_envio_en_auditoria_corrige_el_excedente_sin_validar_el_pago(): void
    {
        $this->activarDetalle();
        $zona = CatalogoZonaPedido::query()->create([
            'nombre' => 'Reexpedición del pedido en auditoría', 'costo_adicional' => 150, 'activo' => true,
        ]);
        $pedido = $this->pedido(CatalogoEstatusPedido::FASE_PENDIENTE_AUXILIAR, [
            'catalogo_zona_id' => $zona->id,
            'total_mercancia' => 2000,
            'aplica_seguro' => false,
            'costo_envio' => 150,
        ]);
        $caja = $this->caja($pedido, ['costo_envio' => 0, 'peso_real_kg' => 2]);
        $pago = PedidoBmaPago::query()->create([
            'pedido_bma_id' => $pedido->id,
            'numero_exhibicion' => 1,
            'monto' => 2356,
            'forma_pago' => 'efectivo',
            'ruta_archivo' => 'pedidos_bma/pagos/prueba.pdf',
            'estado_revision' => PedidoBmaPago::REVISION_PENDIENTE,
            'activo_para_cobertura' => true,
            'capturado_por_id' => $this->usuario->id,
        ]);
        $cobertura = app(CoberturaPagoPedidoBmaService::class);
        $this->assertSame('206.00', $cobertura->calcular($pedido->fresh())['excedente_generado']);

        $actual = app(CambiarPaqueteriaNacional::class)->ejecutar(
            $pedido->fresh(),
            $this->contexto($pedido, $pedido->catalogo_paqueteria_id, [
                ['uuid_operativo' => $caja->uuid_operativo, 'costo_envio' => 206],
            ]),
        );

        $resumen = $cobertura->calcular($actual);
        $this->assertSame('2356.00', $resumen['total_a_cubrir']);
        $this->assertSame('0.00', $resumen['excedente_generado']);
        $this->assertSame(CatalogoEstatusPedido::FASE_PENDIENTE_AUXILIAR, $actual->estatus->fase_ciclo);
        $this->assertSame(PedidoBmaPago::REVISION_PENDIENTE, $pago->fresh()->estado_revision);
    }

    public function test_dhl_cobra_reexpedicion_una_vez_y_asegura_solo_el_flete(): void
    {
        $this->activarDetalle();
        $dhl = $this->paqueteria('DHL');
        $zona = CatalogoZonaPedido::query()->updateOrCreate(
            ['nombre' => 'Con reexpedición'],
            ['costo_adicional' => 150, 'activo' => true]
        );
        $pedido = $this->pedido(CatalogoEstatusPedido::FASE_PENDIENTE_DE_GUIA, [
            'catalogo_paqueteria_id' => $dhl->id,
            'catalogo_zona_id' => $zona->id,
            'total_mercancia' => 1000,
            'aplica_seguro' => true,
            'costo_envio' => 200,
            'costo_seguro' => 0,
        ]);
        $caja = $this->caja($pedido, ['costo_envio' => 200, 'peso_real_kg' => 2]);

        $actual = app(CambiarPaqueteriaNacional::class)->ejecutar($pedido->fresh(), $this->contexto($pedido, $dhl->id, [
            ['uuid_operativo' => $caja->uuid_operativo, 'costo_envio' => 200],
        ], true));

        $this->assertSame('200.00', number_format((float) $caja->fresh()->costo_envio, 2, '.', ''));
        $this->assertSame('75.00', number_format((float) $actual->costo_seguro, 2, '.', ''));
        $this->assertSame('350.00', number_format((float) $actual->costo_envio, 2, '.', ''));
    }

    public function test_la_suma_de_cajas_redondea_el_total_sin_seguro(): void
    {
        $this->activarDetalle();
        $pedido = $this->pedido(CatalogoEstatusPedido::FASE_PENDIENTE_DE_GUIA, ['aplica_seguro' => false, 'costo_envio' => 1]);
        $a = $this->caja($pedido, ['costo_envio' => 10.10, 'peso_real_kg' => 1, 'orden' => 1]);
        $b = $this->caja($pedido, ['costo_envio' => 10.25, 'peso_real_kg' => 1, 'orden' => 2]);

        $actual = app(CambiarPaqueteriaNacional::class)->ejecutar($pedido->fresh(), $this->contexto($pedido, $pedido->catalogo_paqueteria_id, [
            ['uuid_operativo' => $a->uuid_operativo, 'costo_envio' => 10.10],
            ['uuid_operativo' => $b->uuid_operativo, 'costo_envio' => 10.25],
        ]));

        $this->assertSame('20.35', number_format((float) $actual->costo_envio, 2, '.', ''));
        $this->assertSame('0.00', number_format((float) $actual->costo_seguro, 2, '.', ''));
    }

    public function test_delegado_actualiza_el_pdf_de_una_caja_con_respuesta_json(): void
    {
        Storage::fake('local');
        $pedido = $this->pedido(CatalogoEstatusPedido::FASE_PENDIENTE_DE_GUIA);
        $primera = $this->caja($pedido, ['orden' => 1, 'peso_real_kg' => 1]);
        $segunda = $this->caja($pedido, ['orden' => 2, 'peso_real_kg' => 1]);
        $carga = route('control_pedidos.delegado.guia_pdf.store', $pedido);
        $retiro = route('control_pedidos.delegado.guia_pdf.destroy', $pedido);
        $archivo = fn (string $nombre) => UploadedFile::fake()->create($nombre, 1, 'application/pdf');

        $this->actingAs(User::factory()->create())->deleteJson($retiro, ['caja_id' => $segunda->id])->assertForbidden();
        $this->usuario->givePermissionTo(Permission::findOrCreate('control_pedidos.delegado', 'web'));
        $this->actingAs($this->usuario);
        $this->guardarPdfCaja($pedido, $primera);
        $this->postJson($carga, ['caja_id' => $segunda->id, 'guia_pdf' => UploadedFile::fake()->image('foto.jpg')])
            ->assertUnprocessable()->assertJsonValidationErrors('guia_pdf');

        $guia = $this->post($carga, ['caja_id' => $segunda->id, 'guia_pdf' => $archivo('guia.pdf')], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('guia.pedido_bma_caja_id', $segunda->id)
            ->assertJsonPath('guia.nombre_original', 'guia.pdf')
            ->assertJsonPath('guia.relacion_tipo', GuiasPdfPorCaja::AVANCE)
            ->json('guia');
        $this->get($guia['url'])->assertOk();

        $reemplazo = $this->post($carga, ['caja_id' => $segunda->id, 'guia_pdf' => $archivo('reemplazo.pdf')], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('guia.nombre_original', 'reemplazo.pdf')->json('guia.id');
        $this->assertNotSame($guia['id'], $reemplazo);
        $this->assertDatabaseHas('pedido_bma_documentos', ['id' => $guia['id'], 'activo' => false]);

        $this->deleteJson($retiro, ['caja_id' => $segunda->id])->assertOk()->assertExactJson([
            'guia' => null,
            'message' => 'PDF de guía eliminado.',
        ]);
        $this->assertDatabaseHas('pedido_bma_documentos', ['id' => $reemplazo, 'activo' => false]);
        $this->assertSame([$primera->id], GuiasPdfPorCaja::vigentes($pedido->fresh())->pluck('pedido_bma_caja_id')->all());
        $this->deleteJson($retiro, ['caja_id' => $segunda->id + 1])->assertUnprocessable()->assertJsonStructure(['message']);
    }

    public function test_cedis_confirma_todas_las_cajas_pendientes_con_las_guias_de_delegado(): void
    {
        Storage::fake('local');
        $pedido = $this->pedido(CatalogoEstatusPedido::FASE_PENDIENTE_DE_GUIA, [
            'empacado_at' => now(), 'pago_validado_at' => now(),
        ]);
        $pedido->documentos()->create([
            'tipo' => PedidoBmaDocumento::TIPO_REMISION, 'activo' => true,
            'ruta_archivo' => 'remision.pdf', 'nombre_original' => 'remision.pdf',
        ]);
        $cajas = collect([1, 2, 3])->map(fn ($orden) => $this->caja($pedido, ['orden' => $orden, 'peso_real_kg' => 1]));
        $this->usuario->givePermissionTo(Permission::findOrCreate('control_pedidos.delegado', 'web'));
        $cedis = User::factory()->create();
        foreach (['control_pedidos.cedis', 'control_pedidos.cedis.ver_todos', 'control_pedidos.cedis.enviar'] as $permiso) {
            $cedis->givePermissionTo(Permission::findOrCreate($permiso, 'web'));
        }
        $carga = route('control_pedidos.delegado.guia_pdf.store', $pedido);
        $asignar = route('control_pedidos.delegado.asignar_guia', $pedido);
        $listado = route('control_pedidos.cedis.listado', ['q' => $pedido->folio]);
        $salida = route('control_pedidos.cedis.marcar_enviado', $pedido);
        $datos = ['numero_rastreo' => 'COMPARTIDO-1'];
        Event::fake([PedidoBmaActualizado::class]);

        $this->actingAs($cedis)->post($carga, ['caja_id' => $cajas[0]->id])->assertForbidden();
        foreach ($cajas->take(2) as $caja) {
            $this->actingAs($this->usuario)->post($carga, [
                'caja_id' => $caja->id,
                'guia_pdf' => UploadedFile::fake()->create("guia-{$caja->orden}.pdf", 1, 'application/pdf'),
            ])->assertSessionHas('success');
        }
        $avances = $pedido->documentos()->where('tipo', 'guia')->get();
        $this->assertCount(2, $avances);
        $this->assertSame([GuiasPdfPorCaja::AVANCE], $avances->pluck('relacion_tipo')->unique()->values()->all());
        $this->actingAs($this->usuario)->post($asignar, $datos)->assertSessionHas('error');
        $this->assertNull($pedido->fresh()->numero_rastreo);
        Event::assertNotDispatched(PedidoBmaActualizado::class);
        $this->actingAs($cedis)->getJson($listado)
            ->assertJsonPath('pedidos.data.0.faltan_guias_por_caja', 3)
            ->assertJsonMissing(['relacion_tipo' => GuiasPdfPorCaja::AVANCE]);
        $this->get($avances->first()->url)->assertForbidden();
        $this->actingAs($this->usuario)->get($avances->first()->url)->assertOk();
        $docs = $this->getJson(route('control_pedidos.delegado.listado', ['q' => $pedido->folio]))
            ->assertOk()->json('pedidos.data.0.documentos', []);
        $this->assertCount(2, array_filter($docs, fn ($doc) => $doc['tipo'] === 'guia'));

        $this->guardarPdfCaja($pedido, $cajas[2]);
        $this->post($asignar, $datos)->assertSessionHas('success');
        $this->post($asignar, $datos)->assertSessionHas('success');
        $this->assertSame(1, $pedido->historial()->where('accion', 'asignacion_guia')->count());
        app(NotificarPedidoBmaService::class)->shouldHaveReceived('ejecutar')->withArgs(
            fn ($p, $tipo) => $p->id === $pedido->id && $tipo === 'pedido_guia_asignada'
        )->once();
        Event::assertDispatchedTimes(PedidoBmaActualizado::class, 1);

        $confirmadas = $pedido->documentos()->where('relacion_tipo', GuiasPdfPorCaja::CONFIRMADA)->orderBy('orden')->get();
        $this->assertSame($cajas->pluck('id')->all(), $confirmadas->pluck('pedido_bma_caja_id')->all());
        $this->actingAs($cedis)->getJson($listado)
            ->assertJsonPath('pedidos.data.0.numero_rastreo', 'COMPARTIDO-1')
            ->assertJsonPath('pedidos.data.0.faltan_guias_por_caja', 0);
        foreach ($confirmadas as $documento) {
            $this->get($documento->url)->assertOk();
        }

        $this->actingAs($this->usuario)->delete(route('control_pedidos.delegado.guia_pdf.destroy', $pedido), ['caja_id' => $cajas[1]->id])
            ->assertSessionHas('success');
        $todas = ['todas_las_cajas_pendientes' => true];
        $this->post($salida, $todas)->assertForbidden();
        $this->actingAs($cedis)->post($salida, $todas)->assertSessionHas('error');
        foreach ($cajas as $caja) {
            $this->assertTrue($caja->fresh()->estaPendiente());
        }
        $this->guardarPdfCaja($pedido, $cajas[1]);
        $this->actingAs($this->usuario)->post($asignar, $datos)->assertSessionHas('success');

        $this->actingAs($cedis)->post($salida, ['cajas' => [['id' => $cajas[0]->id, 'numero_rastreo' => 'IGNORAR']]])->assertSessionHas('success');
        $this->assertSame(CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO, $pedido->fresh('estatus')->estatus->fase_ciclo);
        $this->assertSame('COMPARTIDO-1', $cajas[0]->fresh()->numero_rastreo);
        $cajas[0]->update(['recolectada_at' => now()->subMinute()]);
        $fechaPrimera = $cajas[0]->fresh()->recolectada_at;
        $retirada = $this->caja($pedido, ['orden' => 4, 'estado_operativo' => PedidoBmaCaja::ESTADO_RETIRADA]);
        $this->post($salida, $todas + ['cajas' => [['id' => $cajas[1]->id]]])->assertSessionHas('error');
        $this->post($salida, $todas)->assertSessionHas('success');
        $this->post($salida, $todas)->assertSessionHas('success');
        $this->assertSame(CatalogoEstatusPedido::FASE_ENVIADO, $pedido->fresh('estatus')->estatus->fase_ciclo);
        foreach ($cajas as $caja) {
            $actual = $caja->fresh();
            $this->assertTrue($actual->estaRecolectada());
            $this->assertSame('COMPARTIDO-1', $actual->numero_rastreo);
            $this->assertNotNull($actual->recolectada_at);
            $this->assertSame($cedis->id, (int) $actual->recolectada_por_id);
        }
        $this->assertEquals($fechaPrimera, $cajas[0]->fresh()->recolectada_at);
        $this->assertNull($retirada->fresh()->recolectada_at);
        $this->assertSame(1, $pedido->historial()->where('accion', 'envio_final')->count());
        app(NotificarPedidoBmaService::class)->shouldHaveReceived('ejecutar')->withArgs(
            fn ($p, $tipo) => $p->id === $pedido->id && $tipo === 'pedido_enviado'
        )->once();
    }

    public function test_un_pdf_general_requiere_vincularse_y_confirmarse_para_despachar_una_caja(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $pedido = $this->pedido(CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO, [
            'numero_rastreo' => 'LEGADO-1', 'empacado_at' => now(),
        ]);
        $caja = $this->caja($pedido, ['peso_real_kg' => 1]);
        $general = $this->guia($pedido, 'guias/general.pdf', 'general.pdf');
        Storage::disk('public')->put($general->ruta_archivo, 'PDF');
        $enviar = fn () => app(MarcarEnviadoPedidoBmaService::class)->ejecutar(
            $pedido->fresh(), $this->usuario->id, todasLasCajasPendientes: true
        );
        $this->assertStringContainsString('confirmar', $this->rechazo($enviar)->getMessage());
        app(GestionarGuiaPdfPedidoBmaService::class)->subir($pedido, null, $this->usuario->id, $caja->id, $general->id);
        $this->assertStringContainsString('confirmar', $this->rechazo($enviar)->getMessage());
        $this->assertFalse($general->fresh()->activo);
        Storage::disk('public')->assertExists($general->ruta_archivo);
        app(AsignarGuiaPedidoBmaService::class)->ejecutar($pedido, 'LEGADO-1', $this->usuario->id);
        $this->assertSame(CatalogoEstatusPedido::FASE_ENVIADO, $enviar()->estatus->fase_ciclo);
        $this->assertSame('LEGADO-1', $caja->fresh()->numero_rastreo);
    }

    public function test_nacional_asegurado_conserva_la_seleccion_parcial_y_rechaza_la_confirmacion_total(): void
    {
        $pedido = $this->pedido(CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO, [
            'empacado_at' => now(), 'aplica_seguro' => true, 'numero_rastreo' => 'ASEGURADO-1',
        ]);
        $primera = $this->caja($pedido, ['orden' => 1, 'peso_real_kg' => 1]);
        $segunda = $this->caja($pedido, ['orden' => 2, 'peso_real_kg' => 1]);
        foreach ([$primera, $segunda] as $caja) {
            PedidoBmaCajaRemision::query()->create([
                'pedido_bma_caja_id' => $caja->id,
                'solicitud_id' => (string) Str::uuid(),
                'folio_externo' => 'REM-'.$caja->orden,
                'cabe' => true,
                'activa' => true,
            ]);
        }

        $servicio = app(MarcarEnviadoPedidoBmaService::class);
        try {
            $servicio->ejecutar($pedido, $this->usuario->id, todasLasCajasPendientes: true);
            $this->fail('La confirmación total debe rechazarse para pedidos con seguro.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('guías por caja de Delegado', $e->getMessage());
        }
        $this->assertTrue($primera->fresh()->estaPendiente());
        $this->assertTrue($segunda->fresh()->estaPendiente());
        $parcial = $servicio->ejecutar($pedido, $this->usuario->id, [['id' => $primera->id]]);
        $this->assertSame(CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO, $parcial->estatus->fase_ciclo);
        $this->assertTrue($primera->fresh()->estaRecolectada());
        $this->assertTrue($segunda->fresh()->estaPendiente());
    }

    public function test_reemplazo_por_caja_preserva_los_otros_pdfs_y_rechaza_cajas_no_editables(): void
    {
        Storage::fake('local');
        $pedido = $this->pedido(CatalogoEstatusPedido::FASE_EN_CEDIS);
        $primera = $this->caja($pedido, ['peso_real_kg' => 1, 'orden' => 1]);
        $segunda = $this->caja($pedido, ['peso_real_kg' => 1, 'orden' => 2]);
        $ajena = $this->caja($this->pedido(CatalogoEstatusPedido::FASE_EN_CEDIS), ['peso_real_kg' => 1]);
        $retirada = $this->caja($pedido, ['estado_operativo' => PedidoBmaCaja::ESTADO_RETIRADA, 'peso_real_kg' => 1]);
        $recolectada = $this->caja($pedido, ['estatus_recoleccion' => PedidoBmaCaja::ESTATUS_RECOLECTADA, 'peso_real_kg' => 1]);
        foreach ([$ajena, $retirada, $recolectada] as $noEditable) {
            $this->assertNotNull($this->rechazo(fn () => $this->guardarPdfCaja($pedido, $noEditable)));
        }
        $original = $this->guardarPdfCaja($pedido, $primera);
        $otro = $this->guardarPdfCaja($pedido, $segunda);
        $reemplazo = $this->guardarPdfCaja($pedido, $primera);
        $this->assertSame($original->id, $reemplazo->reemplaza_documento_id);
        $this->assertFalse($original->fresh()->activo);
        $this->assertSame($otro->id, $segunda->documentos()->vigente()->sole()->id);
        Storage::disk('local')->assertExists($original->ruta_archivo);

        $archivos = Storage::disk('local');
        $almacenFallido = \Mockery::mock(\Illuminate\Filesystem\FilesystemAdapter::class);
        $almacenFallido->shouldReceive('putFileAs')->once()->andReturn(false);
        Storage::shouldReceive('disk')->with('local')->andReturn($almacenFallido);
        $this->assertNotNull($this->rechazo(fn () => $this->guardarPdfCaja($pedido, $primera)));
        $this->assertSame($reemplazo->id, $primera->documentos()->vigente()->sole()->id);
        $archivos->assertExists([$reemplazo->ruta_archivo, $otro->ruta_archivo]);
    }

    public function test_guia_antes_de_empacar_y_guia_asegurada_conservan_la_fase(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $pedido = $this->pedido(CatalogoEstatusPedido::FASE_EN_CEDIS);
        $caja = $this->caja($pedido, ['peso_real_kg' => 1]);
        $this->guardarPdfCaja($pedido, $caja);
        $asignado = app(AsignarGuiaPedidoBmaService::class)->ejecutar($pedido, 'ANTES-1', $this->usuario->id);
        $this->assertSame(CatalogoEstatusPedido::FASE_EN_CEDIS, $asignado->estatus->fase_ciclo);
        $this->assertSame(CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO, \App\Support\ControlPedidos\MaquinaEstadosPedidoBma::faseDestinoEmpaque($asignado));

        $asegurado = $this->pedido(CatalogoEstatusPedido::FASE_PENDIENTE_DE_GUIA, ['aplica_seguro' => true, 'empacado_at' => now()]);
        $this->caja($asegurado, ['peso_real_kg' => 1]);
        $asignado = app(AsignarGuiaPedidoBmaService::class)->ejecutar($asegurado, 'SEGURO-1', $this->usuario->id);
        $this->assertSame(CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO, $asignado->estatus->fase_ciclo);
        $conPdf = app(GestionarGuiaPdfPedidoBmaService::class)->subir($asignado, UploadedFile::fake()->create('seguro.pdf', 1, 'application/pdf'), $this->usuario->id);
        $this->assertNull($conPdf->documentos->sole()->pedido_bma_caja_id);
        $this->assertSame('public', $conPdf->documentos->sole()->discoArchivo());
    }

    public function test_la_importacion_y_la_correccion_general_no_confirman_avances_por_caja(): void
    {
        Storage::fake('local');
        $pedido = $this->pedido(CatalogoEstatusPedido::FASE_PENDIENTE_DE_GUIA, ['empacado_at' => now()]);
        $caja = $this->caja($pedido, ['peso_real_kg' => 1]);
        $avance = $this->guardarPdfCaja($pedido, $caja);
        Storage::disk('local')->put('guias.csv', "ID_Pedido,Guia_Rastreo\n{$pedido->id},NO-PUBLICAR\n");
        $resultado = app(\App\Services\ControlPedidos\ImportarGuiasPedidoService::class)
            ->ejecutar(Storage::disk('local')->path('guias.csv'), $this->usuario->id);
        $this->assertSame(0, $resultado['actualizados']);
        $this->assertSame(1, $resultado['omitidos']);
        $this->assertNull($pedido->fresh()->numero_rastreo);
        $this->assertSame(GuiasPdfPorCaja::AVANCE, $avance->fresh()->relacion_tipo);
        $error = $this->rechazo(fn () => app(\App\Services\ControlPedidos\ActualizarGuiaPedidoBmaService::class)
            ->ejecutar($pedido, 'NO-PUBLICAR', $this->usuario->id));
        $this->assertStringContainsString('Asignar', $error->getMessage());
    }

    public function test_un_cambio_de_direccion_invalida_el_avance_sin_adelantar_el_empaque(): void
    {
        Storage::fake('local');
        $pedido = $this->pedido(CatalogoEstatusPedido::FASE_EN_CEDIS);
        $caja = $this->caja($pedido, ['peso_real_kg' => 1]);
        $avance = $this->guardarPdfCaja($pedido, $caja);
        $actual = app(InvalidarGuiaPorCambioDireccion::class)->ejecutar($pedido, $this->usuario->id, 'Domicilio corregido');
        $this->assertSame(CatalogoEstatusPedido::FASE_EN_CEDIS, $actual->estatus->fase_ciclo);
        $this->assertNull($actual->empacado_at);
        $this->assertFalse($avance->fresh()->activo);
        $this->assertNotNull($this->rechazo(fn () => app(AsignarGuiaPedidoBmaService::class)
            ->ejecutar($actual, 'NUEVA-1', $this->usuario->id)));
        Storage::disk('local')->assertExists($avance->ruta_archivo);
    }

    public function test_cambiar_rastreo_desde_asignar_requiere_pdfs_nuevos_y_conserva_el_aviso_de_correccion(): void
    {
        Storage::fake('local');
        $pedido = $this->pedido(CatalogoEstatusPedido::FASE_PENDIENTE_DE_GUIA, ['empacado_at' => now()]);
        $caja = $this->caja($pedido, ['peso_real_kg' => 1]);
        $this->guardarPdfCaja($pedido, $caja);
        $asignar = app(AsignarGuiaPedidoBmaService::class);
        $asignar->ejecutar($pedido, 'ANTERIOR-1', $this->usuario->id);
        $this->assertNotNull($this->rechazo(fn () => $asignar->ejecutar($pedido, 'CORREGIDO-1', $this->usuario->id)));
        $this->guardarPdfCaja($pedido, $caja);
        $actual = $asignar->ejecutar($pedido, 'CORREGIDO-1', $this->usuario->id);
        $this->assertSame('CORREGIDO-1', $actual->numero_rastreo);
        $this->assertTrue($actual->guia_retraso);
        $this->assertSame($this->usuario->id, $actual->guia_corregida_por_id);
        app(NotificarPedidoBmaService::class)->shouldHaveReceived('ejecutar')->withArgs(
            fn ($p, $tipo, $texto, $permisos) => $p->id === $pedido->id && $tipo === 'pedido_guia_retraso'
                && $permisos === ['control_pedidos.cedis', 'control_pedidos.auditar']
        )->once();
    }

    private function guardarPdfCaja(PedidoBma $pedido, PedidoBmaCaja $caja): PedidoBmaDocumento
    {
        app(GestionarGuiaPdfPedidoBmaService::class)->subir(
            $pedido,
            UploadedFile::fake()->create("guia-{$caja->orden}.pdf", 1, 'application/pdf'),
            $this->usuario->id,
            $caja->id,
        );

        return $caja->documentos()->vigente()->sole();
    }

    public function test_credito_bloqueado_no_remisiona_ni_guia_ni_sale_ni_acepta_transferencia(): void
    {
        $auxiliar = $this->pedido(CatalogoEstatusPedido::FASE_PENDIENTE_AUXILIAR);
        $this->marcarCredito($auxiliar, 'bloqueado');
        $remision = $this->rechazo(fn () => app(GestionarRemisionPedidoBmaService::class)->actualizarNumeroRemision($auxiliar->fresh(), 'REM-N', $this->usuario->id));
        $this->assertNotNull($remision);
        $this->assertStringContainsString('crédito bloqueado', $remision->getMessage());
        $this->assertNull($auxiliar->fresh()->numero_remision);

        $guia = $this->pedido(CatalogoEstatusPedido::FASE_PENDIENTE_DE_GUIA);
        $this->caja($guia, ['peso_real_kg' => 1]);
        $this->marcarCredito($guia, 'bloqueado');
        $sinGuia = $this->rechazo(fn () => app(AsignarGuiaPedidoBmaService::class)->ejecutar($guia->fresh(), 'GUIA-CRED', $this->usuario->id));
        $this->assertNotNull($sinGuia);
        $this->assertStringContainsString('crédito bloqueado', $sinGuia->getMessage());

        $salida = $this->pedido(CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO, [
            'numero_rastreo' => 'OK-1',
            'empacado_at' => now(),
        ]);
        $this->caja($salida, ['numero_rastreo' => 'OK-C', 'peso_real_kg' => 1]);
        $this->guia($salida, 'guias/ok.pdf', 'ok.pdf');
        $this->marcarCredito($salida, 'bloqueado');
        $sinSalida = $this->rechazo(fn () => app(MarcarEnviadoPedidoBmaService::class)->ejecutar($salida->fresh(), $this->usuario->id));
        $this->assertNotNull($sinSalida);
        $this->assertStringContainsString('crédito bloqueado', $sinSalida->getMessage());
        $this->assertSame(CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO, $salida->fresh()->estatus->fase_ciclo);

        $mezcla = null;
        try {
            app(RegistrarPagoPedidoBmaService::class)->handle($auxiliar->fresh(), [
                'monto' => 10,
                'forma_pago' => 'transferencia',
                'idempotency_key' => (string) Str::uuid(),
            ], UploadedFile::fake()->image('pago.jpg'), $this->usuario->id);
        } catch (\InvalidArgumentException $e) {
            $mezcla = $e;
        }
        $this->assertNotNull($mezcla);
        $this->assertStringContainsString('transferencia', $mezcla->getMessage());
        $this->assertSame(0, $auxiliar->pagosExhibicion()->count());
    }

    public function test_una_caja_sin_peso_no_recibe_guia(): void
    {
        $pedido = $this->pedido(CatalogoEstatusPedido::FASE_PENDIENTE_DE_GUIA);
        $this->caja($pedido, ['peso_real_kg' => null]);

        $sinPeso = $this->rechazo(fn () => app(AsignarGuiaPedidoBmaService::class)->ejecutar($pedido->fresh(), 'GUIA-SIN-PESO', $this->usuario->id));
        $this->assertNotNull($sinPeso);
        $this->assertStringContainsString('peso', $sinPeso->getMessage());
        $this->assertNull($pedido->fresh()->numero_rastreo);
    }

    public function test_cambiar_paqueteria_http_exige_permiso_y_rechaza_la_salida(): void
    {
        $pedido = $this->pedido(CatalogoEstatusPedido::FASE_ENVIADO, ['numero_rastreo' => 'HTTP-1']);
        $ajeno = User::factory()->create();
        $this->actingAs($ajeno)
            ->postJson('/control-pedidos/'.$pedido->id.'/paqueteria-nacional', $this->contexto($pedido, $pedido->catalogo_paqueteria_id))
            ->assertForbidden();
        $this->assertSame('HTTP-1', $pedido->fresh()->numero_rastreo);

        $this->actingAs($this->usuario)
            ->postJson('/control-pedidos/'.$pedido->id.'/paqueteria-nacional', $this->contexto($pedido->fresh(), $pedido->catalogo_paqueteria_id))
            ->assertStatus(422);
        $this->assertSame('HTTP-1', $pedido->fresh()->numero_rastreo);
    }

    /** @param  array<string, mixed>  $extra */
    private function pedido(string $fase, array $extra = []): PedidoBma
    {
        $estatus = CatalogoEstatusPedido::query()->where('fase_ciclo', $fase)->firstOrFail();
        $origen = CatalogoOrigenPedido::query()->create([
            'codigo' => 'NAC-'.uniqid(),
            'nombre' => 'Nacional '.uniqid(),
            'requiere_logistica' => true,
            'activo' => true,
        ]);
        $almacen = Almacen::query()->create([
            'codigo' => 'CEDIS-'.uniqid(),
            'nombre' => 'CEDIS',
            'activo' => true,
            'permite_salida_pedidos' => true,
        ]);
        $paqueteria = $this->paqueteria('DHL '.uniqid());

        return PedidoBma::query()->create(array_merge([
            'folio' => 'NAC-'.uniqid(),
            'folio_remision' => 'WIZ-'.uniqid(),
            'fecha' => now()->toDateString(),
            'vendedor_id' => $this->usuario->id,
            'origen_id' => $origen->id,
            'almacen_id' => $almacen->id,
            'catalogo_estatus_pedido_id' => $estatus->id,
            'catalogo_paqueteria_id' => $paqueteria->id,
            'solicitud_preparacion_json' => [
                'modalidad_entrega' => 'nacional',
                'captura_nacional' => 'compartida',
                'codigo_modalidad' => 'ENVIO_BODEGA_NORMAL',
            ],
            'total_mercancia' => 100,
            'costo_envio' => 0,
            'saldo_a_favor' => 0,
            'costo_seguro' => 0,
            'total_a_cobrar' => 100,
            'aplica_seguro' => false,
            'es_resguardo' => false,
        ], $extra));
    }

    /** @param  array<string, mixed>  $extra */
    private function caja(PedidoBma $pedido, array $extra = []): PedidoBmaCaja
    {
        return PedidoBmaCaja::query()->create(array_merge([
            'pedido_bma_id' => $pedido->id,
            'uuid_operativo' => (string) Str::uuid(),
            'catalogo_tipo_caja_id' => CatalogoTipoCajaPedido::query()->firstOrCreate(
                ['nombre' => 'Caja nacional'],
                ['activo' => true]
            )->id,
            'orden' => 1,
            'estado_operativo' => PedidoBmaCaja::ESTADO_ACTIVA,
            'estatus_recoleccion' => PedidoBmaCaja::ESTATUS_PENDIENTE,
        ], $extra));
    }

    private function guia(PedidoBma $pedido, string $ruta, string $nombre, ?int $cajaId = null, bool $activo = true): PedidoBmaDocumento
    {
        return PedidoBmaDocumento::query()->create([
            'pedido_bma_id' => $pedido->id,
            'pedido_bma_caja_id' => $cajaId,
            'tipo' => PedidoBmaDocumento::TIPO_GUIA,
            'ruta_archivo' => $ruta,
            'nombre_original' => $nombre,
            'mime_type' => 'application/pdf',
            'activo' => $activo,
        ]);
    }

    private function paqueteria(string $nombre): CatalogoPaqueteriaPedido
    {
        return CatalogoPaqueteriaPedido::query()->create([
            'nombre' => $nombre,
            'categoria' => CatalogoPaqueteriaPedido::CATEGORIA_COMERCIAL,
            'requiere_peso' => true,
            'activo' => true,
            'modalidad_tarifa' => CatalogoPaqueteriaPedido::MODALIDAD_FIJA,
        ]);
    }

    /** @param  list<array<string, mixed>>  $lineas */
    private function contexto(PedidoBma $pedido, int $paqueteriaId, array $lineas = [], bool $seguro = false): array
    {
        $fresco = $pedido->fresh();

        return [
            'catalogo_paqueteria_id' => $paqueteriaId,
            'solicitud_id' => (string) Str::uuid(),
            'revision_esperada' => (int) $fresco->revision_registro,
            'base_captura' => RevisionCapturaPedido::base($fresco),
            'aplica_seguro' => $seguro,
            'lineas' => $lineas,
            'usuario_id' => $this->usuario->id,
        ];
    }

    private function marcarCredito(PedidoBma $pedido, string $resultado): void
    {
        $json = $pedido->solicitud_preparacion_json ?? [];
        $json['credito_wizerp'] = ['resultado' => $resultado, 'causa' => 'Saldo vencido'];
        $pedido->update(['solicitud_preparacion_json' => $json]);
    }

    private function rechazo(callable $accion): ?\RuntimeException
    {
        try {
            $accion();
        } catch (\RuntimeException $e) {
            if ($e instanceof \PHPUnit\Framework\AssertionFailedError) {
                throw $e;
            }

            return $e;
        }

        return null;
    }

    private function activarDetalle(): void
    {
        \App\Models\ConfiguracionSistema::query()->updateOrCreate(
            ['clave' => \App\Services\ControlPedidos\EnviosPedidoBmaConfig::CLAVE_DETALLE],
            ['valor' => '1', 'tipo' => 'boolean', 'descripcion' => 'test', 'grupo' => 'control_pedidos']
        );
        app(\App\Services\ControlPedidos\EnviosPedidoBmaConfig::class)->olvidarCache();
    }
}
