<?php

namespace Tests\Feature\ControlPedidos;

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
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

    public function test_cambiar_paqueteria_antes_de_salida_invalida_guia_y_rechaza_costo_cero(): void
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

        $rechazo = $this->rechazo(fn () => app(CambiarPaqueteriaNacional::class)->ejecutar($pedido->fresh(), $contexto));
        $this->assertNotNull($rechazo);
        $this->assertStringContainsString('cero', $rechazo->getMessage());
        $this->assertSame(80.0, (float) $caja->fresh()->costo_envio);
        $this->assertSame('PRE-1', $pedido->fresh()->numero_rastreo);

        $valido = $this->contexto($pedido->fresh(), $otra->id, [
            ['uuid_operativo' => $caja->uuid_operativo, 'costo_envio' => 95],
        ]);
        $aplicada = app(CambiarPaqueteriaNacional::class)->ejecutar($pedido->fresh(), $valido);
        $reintento = app(CambiarPaqueteriaNacional::class)->ejecutar($aplicada->fresh(), [
            ...$this->contexto($aplicada->fresh(), $pedido->catalogo_paqueteria_id, [
                ['uuid_operativo' => $caja->uuid_operativo, 'costo_envio' => 10],
            ]),
            'solicitud_id' => $valido['solicitud_id'],
        ]);

        $this->assertSame($otra->id, $aplicada->catalogo_paqueteria_id);
        $this->assertNull($aplicada->numero_rastreo);
        $this->assertNull($caja->fresh()->numero_rastreo);
        $this->assertFalse($guia->fresh()->activo);
        $this->assertNull($aplicada->pago_validado_at);
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
