<?php

namespace Tests\Feature\ControlPedidos;

use App\Models\Almacen;
use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\CatalogoOrigenPedido;
use App\Models\ControlPedidos\CatalogoPaqueteriaPedido;
use App\Models\ControlPedidos\CatalogoTipoCajaPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaCaja;
use App\Models\ControlPedidos\PedidoBmaCajaRemision;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\ControlPedidos\AsignarGuiaPedidoBmaService;
use App\Services\ControlPedidos\MarcarEmpacadoPedidoBmaService;
use App\Services\ControlPedidos\MarcarEnviadoPedidoBmaService;
use App\Services\ControlPedidos\Nacional\AsignarRemisionCajaNacional;
use App\Services\ControlPedidos\Nacional\CambiarNacionalATienda;
use App\Services\ControlPedidos\Nacional\CambiarPaqueteriaNacional;
use App\Services\ControlPedidos\Nacional\CustodiaComplementoNacional;
use App\Services\ControlPedidos\Nacional\RegistrarComplementoNacional;
use App\Services\ControlPedidos\Nacional\RegistrarHechoCancelacionNacional;
use App\Services\ControlPedidos\SincronizarCajasPedidoBmaService;
use App\Support\ControlPedidos\Nacional\BarreraRecepcionComplementoNacional;
use App\Support\ControlPedidos\Nacional\BarreraSeguroCajaNacional;
use App\Support\ControlPedidos\RevisionCapturaPedido;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class NacionalOla3Test extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->withoutMiddleware([ValidateCsrfToken::class, PreventRequestForgery::class]);
        $this->mock(\App\Services\ControlPedidos\NotificarPedidoBmaService::class)->shouldReceive('ejecutar')->zeroOrMoreTimes();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->usuario = User::factory()->create();
        $this->usuario->givePermissionTo(Permission::findOrCreate('control_pedidos.crear', 'web'));
        $fases = [
            CatalogoEstatusPedido::FASE_BORRADOR => 'Borrador',
            CatalogoEstatusPedido::FASE_EN_CEDIS => 'En CEDIS',
            CatalogoEstatusPedido::FASE_PENDIENTE_DE_GUIA => 'Pendiente de guía',
            CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO => 'Pendiente de envío',
            CatalogoEstatusPedido::FASE_ENVIADO => 'Enviado',
            CatalogoEstatusPedido::FASE_CANCELADO => 'Cancelado',
        ];
        $orden = 1;
        foreach ($fases as $fase => $nombre) {
            CatalogoEstatusPedido::query()->firstOrCreate(
                ['fase_ciclo' => $fase],
                ['codigo_interno' => 'N3-'.$orden, 'nombre_visual' => $nombre, 'color_hex' => '#999999', 'orden' => $orden, 'activo' => true]
            );
            $orden++;
        }
    }

    public function test_anexo_con_remision_crea_un_minipedido_sin_pago_y_el_reintento_no_duplica(): void
    {
        Storage::fake('public');
        $pedido = $this->pedido(CatalogoEstatusPedido::FASE_EN_CEDIS, [
            'numero_remision' => 'REM-1',
            'total_mercancia' => 100,
            'pago_validado_at' => now(),
        ]);
        $datos = $this->datosComplemento($pedido, 40, 'SKU-NUEVA');

        $primero = app(RegistrarComplementoNacional::class)->ejecutar($pedido, $this->usuario, $datos, [$datos['evidencia']]);
        $segundo = app(RegistrarComplementoNacional::class)->ejecutar($pedido->fresh(), $this->usuario, $datos, [$datos['evidencia']]);

        $pedido->refresh();
        $hijo = $this->hijoDe($pedido);
        $this->assertSame($primero->id, $segundo->id);
        $this->assertSame(1, $this->anexosDe($pedido));
        $this->assertNull($hijo->pedido_principal_id, 'el anexo sale en caja y guía aparte');
        $this->assertSame('REM-1', $pedido->numero_remision);
        $this->assertSame('100.00', number_format((float) $pedido->total_mercancia, 2, '.', ''));
        $this->assertNotNull($hijo);
        $this->assertSame('40.00', number_format((float) $hijo->total_mercancia, 2, '.', ''));
        $this->assertNull($hijo->numero_remision);
        $this->assertSame('nacional', $hijo->solicitud_preparacion_json['modalidad_entrega']);
        $this->assertSame('compartida', $hijo->solicitud_preparacion_json['captura_nacional']);
        $this->assertSame('SKU-NUEVA', $hijo->solicitud_preparacion_json['complemento_nacional']['piezas'][0]['sku']);
        $this->assertSame('0.00', number_format((float) $hijo->solicitud_preparacion_json['complemento_nacional']['mercancia_nueva_por_cobrar'], 2, '.', ''));
        $this->assertNull($hijo->pago_validado_at, 'Ventas no valida su propio pago');
        $this->assertNull($hijo->sucursal_destino_id);
        $this->assertSame(0, PedidoBmaTareaPreparacion::query()->where('pedido_bma_id', $hijo->id)->count());
        $this->assertSame(0, $pedido->documentos()->count());
        $this->assertSame(1, $hijo->documentos()->count());
    }

    public function test_el_anexo_http_con_remision_crea_su_minipedido_y_no_toca_el_original(): void
    {
        Storage::fake('public');
        $pedido = $this->pedido(CatalogoEstatusPedido::FASE_EN_CEDIS, [
            'numero_remision' => 'REM-HTTP',
            'total_mercancia' => 100,
            'pago_validado_at' => now(),
        ]);
        $datos = $this->datosComplemento($pedido, 40, 'SKU-HTTP');
        $cuerpo = [
            'solicitud_id' => $datos['solicitud_id'],
            'folio_wizerp' => $datos['folio_wizerp'],
            'piezas' => $datos['piezas'],
            'total_mercancia_nueva' => 40,
            'revision_esperada' => $datos['revision_esperada'],
            'base_captura' => $datos['base_captura'],
            'documento' => $datos['evidencia'],
        ];
        $ajeno = User::factory()->create();

        $this->actingAs($ajeno)->post(route('control_pedidos.anexo_extraordinario', $pedido), $cuerpo, ['Accept' => 'application/json'])
            ->assertForbidden();
        $this->actingAs($this->usuario)->post(route('control_pedidos.anexo_extraordinario', $pedido), $cuerpo, ['Accept' => 'application/json'])
            ->assertOk();

        $this->assertSame('REM-HTTP', $pedido->fresh()->numero_remision);
        $this->assertSame(1, $this->anexosDe($pedido));
        $this->assertSame(0, PedidoBmaTareaPreparacion::query()->count());
    }

    public function test_tras_la_salida_el_complemento_es_otro_envio_y_la_caja_despachada_sigue_igual(): void
    {
        Storage::fake('public');
        $pedido = $this->pedido(CatalogoEstatusPedido::FASE_ENVIADO, [
            'numero_remision' => 'REM-SALIDA',
            'numero_rastreo' => 'GUIA-SALIDA',
            'total_mercancia' => 100,
            'pago_validado_at' => now(),
        ]);
        $caja = $this->caja($pedido, [
            'estatus_recoleccion' => PedidoBmaCaja::ESTATUS_RECOLECTADA,
            'numero_rastreo' => 'GUIA-CAJA',
            'peso_real_kg' => 2,
        ]);
        $datos = $this->datosComplemento($pedido, 25, 'SKU-POST');

        $otro = app(RegistrarComplementoNacional::class)->ejecutar($pedido, $this->usuario, $datos, [$datos['evidencia']]);

        $this->assertNull($otro->pedido_principal_id);
        $this->assertSame($pedido->id, $otro->solicitud_preparacion_json['complemento_nacional']['pedido_origen_id']);
        $this->assertFalse($otro->solicitud_preparacion_json['complemento_nacional']['antes_de_salida']);
        $this->assertSame('REM-SALIDA', $pedido->fresh()->numero_remision);
        $this->assertSame('GUIA-SALIDA', $pedido->fresh()->numero_rastreo);
        $this->assertSame('GUIA-CAJA', $caja->fresh()->numero_rastreo);
        $this->assertTrue($caja->fresh()->estaRecolectada());
        $this->assertSame('100.00', number_format((float) $pedido->fresh()->total_mercancia, 2, '.', ''));
        $this->assertSame(0, PedidoBmaTareaPreparacion::query()->count());
    }

    public function test_el_vinculo_no_es_recepcion_y_bloquea_empaque_guia_y_salida(): void
    {
        $envio = $this->pedido(CatalogoEstatusPedido::FASE_EN_CEDIS, ['numero_remision' => 'REM-ENV']);
        $resguardo = $this->pedido(CatalogoEstatusPedido::FASE_EN_CEDIS, [
            'numero_remision' => 'REM-RES',
            'es_resguardo' => true,
            'pago_validado_at' => now(),
            'total_mercancia' => 80,
        ]);

        app(CustodiaComplementoNacional::class)->vincular($envio, $resguardo, $this->usuario->id);
        $resguardo->refresh();
        $this->assertSame('vinculado', $resguardo->solicitud_preparacion_json['vinculo_resguardo']['estado']);
        $this->assertFalse($resguardo->solicitud_preparacion_json['vinculo_resguardo']['integrado']);
        $this->assertSame($envio->id, (int) $resguardo->pedido_principal_id);
        $this->esperaRecepcion($envio);
        foreach ([
            fn () => app(MarcarEmpacadoPedidoBmaService::class)->ejecutar($envio->fresh(), $this->usuario->id),
            fn () => app(AsignarGuiaPedidoBmaService::class)->ejecutar($envio->fresh(), 'GUIA-BLOQUEADA', $this->usuario->id),
            fn () => app(MarcarEnviadoPedidoBmaService::class)->ejecutar($envio->fresh(), $this->usuario->id),
        ] as $accion) {
            $bloqueo = $this->rechazo($accion);
            $this->assertNotNull($bloqueo);
            $this->assertStringContainsString('integración', $bloqueo->getMessage());
        }

        app(CustodiaComplementoNacional::class)->registrarSalidaOrigen($resguardo->fresh(), $this->usuario->id);
        $this->assertSame('en_traslado', $resguardo->fresh()->solicitud_preparacion_json['vinculo_resguardo']['estado']);
        $this->assertFalse($resguardo->fresh()->solicitud_preparacion_json['vinculo_resguardo']['integrado']);
        $this->esperaRecepcion($envio);

        app(CustodiaComplementoNacional::class)->confirmarRecepcionCedis($resguardo->fresh(), $this->usuario->id);
        $recibido = $resguardo->fresh();
        $this->assertSame('recibido_cedis', $recibido->solicitud_preparacion_json['vinculo_resguardo']['estado']);
        $this->assertFalse($recibido->solicitud_preparacion_json['vinculo_resguardo']['integrado']);
        $this->assertNotNull($recibido->solicitud_preparacion_json['vinculo_resguardo']['recibido_at']);
        $this->esperaRecepcion($envio);

        app(CustodiaComplementoNacional::class)->liberar($recibido, $this->usuario->id);
        $this->assertTrue($resguardo->fresh()->solicitud_preparacion_json['vinculo_resguardo']['integrado']);
    }

    public function test_sin_seguro_dos_cajas_no_exigen_desglose_y_con_seguro_cada_remision_queda_en_una_caja(): void
    {
        $this->activarDetalle();
        $sin = $this->pedido(CatalogoEstatusPedido::FASE_PENDIENTE_DE_GUIA, ['aplica_seguro' => false]);
        $this->caja($sin, ['peso_real_kg' => 1, 'costo_envio' => 10]);
        $this->caja($sin, ['peso_real_kg' => 1, 'costo_envio' => 10, 'orden' => 2]);
        BarreraSeguroCajaNacional::assert($sin->fresh('cajas'));

        $dhl = CatalogoPaqueteriaPedido::query()->firstOrCreate(
            ['nombre' => 'DHL'],
            ['categoria' => CatalogoPaqueteriaPedido::CATEGORIA_COMERCIAL, 'requiere_peso' => true, 'activo' => true, 'modalidad_tarifa' => CatalogoPaqueteriaPedido::MODALIDAD_FIJA]
        );
        $pedido = $this->pedido(CatalogoEstatusPedido::FASE_PENDIENTE_DE_GUIA, [
            'catalogo_paqueteria_id' => $dhl->id,
            'aplica_seguro' => true,
            'total_mercancia' => 1500,
        ]);
        $cajaA = $this->caja($pedido, ['peso_real_kg' => 1, 'costo_envio' => 100]);
        $cajaB = $this->caja($pedido, ['peso_real_kg' => 1, 'costo_envio' => 50, 'orden' => 2]);
        $remisionA = $this->pedido(CatalogoEstatusPedido::FASE_EN_CEDIS, ['numero_remision' => 'A-1', 'total_mercancia' => 1000, 'pago_validado_at' => now(), 'es_resguardo' => true]);
        $remisionB = $this->pedido(CatalogoEstatusPedido::FASE_EN_CEDIS, ['numero_remision' => 'B-1', 'total_mercancia' => 500]);
        app(AsignarRemisionCajaNacional::class)->ejecutar($cajaA, $this->usuario->id, [
            'solicitud_id' => (string) Str::uuid(),
            'pedido_bma_id' => $remisionA->id,
            'contenido' => 'Resguardo pagado',
            'valor_asegurado' => 1000,
        ]);
        app(AsignarRemisionCajaNacional::class)->ejecutar($cajaB, $this->usuario->id, [
            'solicitud_id' => (string) Str::uuid(),
            'pedido_bma_id' => $remisionB->id,
            'contenido' => 'Mercancía nueva',
            'valor_asegurado' => 500,
            'mercancia_nueva_por_cobrar' => 500,
        ]);

        $pagada = PedidoBmaCajaRemision::query()->where('pedido_bma_caja_id', $cajaA->id)->first();
        $this->assertNotNull($pagada);
        $this->assertSame('0.00', number_format((float) $pagada->mercancia_nueva_por_cobrar, 2, '.', ''));
        $this->assertSame('1000.00', number_format((float) $pagada->valor_asegurado, 2, '.', ''));
        $this->assertSame($cajaA->uuid_operativo, $cajaA->fresh()->uuid_operativo);
        $rechazo = $this->rechazo(fn () => app(AsignarRemisionCajaNacional::class)->ejecutar($cajaB->fresh(), $this->usuario->id, [
            'solicitud_id' => (string) Str::uuid(),
            'pedido_bma_id' => $remisionA->id,
            'contenido' => 'Parte',
            'valor_asegurado' => 100,
        ]));
        $this->assertNotNull($rechazo);
        $this->assertStringContainsString('no se divide', $rechazo->getMessage());

        $actual = app(CambiarPaqueteriaNacional::class)->ejecutar($pedido->fresh(), $this->contexto($pedido, $dhl->id, [
            ['uuid_operativo' => $cajaA->uuid_operativo, 'costo_envio' => 100],
            ['uuid_operativo' => $cajaB->uuid_operativo, 'costo_envio' => 50],
        ], true));
        $seguros = $actual->cajas()->orderBy('orden')->pluck('costo_seguro')->map(fn ($monto) => number_format((float) $monto, 2, '.', ''))->all();
        $this->assertSame(['73.00', '62.00'], $seguros);
        $this->assertSame('135.00', number_format((float) $actual->costo_seguro, 2, '.', ''));
    }

    public function test_la_remision_que_no_cabe_no_se_parte_ni_se_asegura(): void
    {
        $pedido = $this->pedido(CatalogoEstatusPedido::FASE_PENDIENTE_DE_GUIA, ['aplica_seguro' => true, 'costo_seguro' => 10]);
        $caja = $this->caja($pedido, ['peso_real_kg' => 1]);
        $rechazo = $this->rechazo(fn () => app(AsignarRemisionCajaNacional::class)->ejecutar($caja, $this->usuario->id, [
            'solicitud_id' => (string) Str::uuid(),
            'folio_externo' => 'EXT-GRANDE',
            'contenido' => 'No cabe',
            'valor_asegurado' => 9000,
            'cabe' => false,
        ]));
        $this->assertNotNull($rechazo);
        $this->assertStringContainsString('No hay despacho asegurado', $rechazo->getMessage());
        $this->assertSame(0, PedidoBmaCajaRemision::query()->count());
        $this->assertSame('10.00', number_format((float) $pedido->fresh()->costo_seguro, 2, '.', ''));
    }

    public function test_el_cambio_a_tienda_conserva_mercancia_y_no_enciende_la_conversion(): void
    {
        $sucursal = Sucursal::query()->create(['codigo' => 'SUC-'.uniqid(), 'nombre' => 'Sucursal norte', 'activo' => true]);
        $pedido = $this->pedido(CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO, [
            'numero_remision' => 'REM-TDA',
            'numero_rastreo' => 'GUIA-TDA',
            'total_mercancia' => 220,
            'pago_validado_at' => now(),
            'costo_envio' => 90,
            'empacado_at' => now(),
        ]);

        $actual = app(CambiarNacionalATienda::class)->ejecutar($pedido, $this->usuario, $sucursal->id, $this->contexto($pedido, $pedido->catalogo_paqueteria_id));

        $this->assertSame('REM-TDA', $actual->numero_remision);
        $this->assertSame('220.00', number_format((float) $actual->total_mercancia, 2, '.', ''));
        $this->assertSame('90.00', number_format((float) $actual->costo_envio, 2, '.', ''));
        $this->assertNotNull($actual->pago_validado_at);
        $this->assertNull($actual->numero_rastreo);
        $this->assertFalse((bool) ($actual->solicitud_preparacion_json['invalidacion_guia']['cancelacion_paqueteria_confirmada'] ?? true));
        $this->assertSame($sucursal->id, (int) $actual->sucursal_destino_id);
        $this->assertSame('pendiente_recepcion', $actual->solicitud_preparacion_json['cambio_a_tienda']['estado']);
        $this->assertNotSame(\App\Support\ControlPedidos\EstatusSucursalPedidoBma::EN_CUSTODIA, $actual->estatus_sucursal);
        $this->assertSame(1, \App\Models\PuntoVenta\ResguardoPdv::query()->where('pedido_bma_id', $actual->id)->where('estado', \App\Models\PuntoVenta\ResguardoPdv::ESTADO_PENDIENTE_RECEPCION)->count());
        $this->assertSame('nacional', $actual->solicitud_preparacion_json['modalidad_entrega']);
        $this->assertArrayNotHasKey('conversion_modalidad', $actual->solicitud_preparacion_json);
        $this->assertSame(0, PedidoBmaTareaPreparacion::query()->count());

        $salio = $this->pedido(CatalogoEstatusPedido::FASE_ENVIADO, ['numero_remision' => 'REM-YA', 'numero_rastreo' => 'GUIA-YA']);
        $rechazo = $this->rechazo(fn () => app(CambiarNacionalATienda::class)->ejecutar(
            $salio,
            $this->usuario,
            $sucursal->id,
            $this->contexto($salio, $salio->catalogo_paqueteria_id)
        ));
        $this->assertNotNull($rechazo);
        $this->assertSame('GUIA-YA', $salio->fresh()->numero_rastreo);
    }

    public function test_la_cancelacion_separa_solicitud_mercancia_pagos_y_guia(): void
    {
        $conGuia = $this->pedido(CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO, [
            'numero_remision' => 'REM-G',
            'numero_rastreo' => 'GUIA-G',
            'total_mercancia' => 300,
            'pago_validado_at' => now(),
            'costo_envio' => 40,
        ]);
        $registrado = app(RegistrarHechoCancelacionNacional::class)->ejecutar($conGuia, $this->usuario, [
            'solicitud_id' => (string) Str::uuid(),
            'motivo' => 'Cliente desiste',
            'cedis_notificado' => false,
            'liberar_reserva' => false,
        ]);
        $hecho = $registrado->solicitud_preparacion_json['cancelacion_nacional'];
        $this->assertSame('Cliente desiste', $hecho['solicitud']['motivo']);
        $this->assertSame('conservados', $hecho['pagos']['estado']);
        $this->assertNull($hecho['guia']['absorbido_por']);
        $this->assertFalse($hecho['guia']['cancelacion_paqueteria_confirmada']);
        $this->assertSame('300.00', number_format((float) $registrado->total_mercancia, 2, '.', ''));
        $this->assertNotNull($registrado->pago_validado_at);
        $this->assertSame(CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO, $registrado->estatus->fase_ciclo);

        $resguardo = $this->pedido(CatalogoEstatusPedido::FASE_EN_CEDIS, [
            'es_resguardo' => true,
            'numero_remision' => 'REM-LIB',
            'total_mercancia' => 50,
        ]);
        $sinAviso = $this->rechazo(fn () => app(RegistrarHechoCancelacionNacional::class)->ejecutar($resguardo, $this->usuario, [
            'solicitud_id' => (string) Str::uuid(),
            'motivo' => 'Cliente desiste',
            'cedis_notificado' => false,
            'liberar_reserva' => true,
        ]));
        $this->assertNotNull($sinAviso);
        $this->assertNull($resguardo->fresh()->cancelado_at);

        $liberado = app(RegistrarHechoCancelacionNacional::class)->ejecutar($resguardo->fresh(), $this->usuario, [
            'solicitud_id' => (string) Str::uuid(),
            'motivo' => 'cliente_desiste',
            'cedis_notificado' => true,
            'liberar_reserva' => true,
        ]);
        $this->assertNotNull($liberado->cancelado_at);
        $this->assertSame('REM-LIB', $liberado->numero_remision);
        $this->assertSame('50.00', number_format((float) $liberado->total_mercancia, 2, '.', ''));
        $this->assertSame('liberada', $liberado->solicitud_preparacion_json['cancelacion_nacional']['mercancia']['estado']);
    }

    public function test_la_caja_recolectada_no_cambia_contenido_ni_guia_y_el_pedido_sigue_abierto(): void
    {
        $pedido = $this->pedido(CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO, [
            'numero_rastreo' => 'GUIA-PED',
            'empacado_at' => now(),
            'peso_real_kg' => 3,
        ]);
        $recolectada = $this->caja($pedido, [
            'estatus_recoleccion' => PedidoBmaCaja::ESTATUS_RECOLECTADA,
            'numero_rastreo' => 'GUIA-1',
            'largo' => 10,
            'ancho' => 10,
            'alto' => 10,
            'peso_real_kg' => 1,
            'peso_volumetrico_kg' => 1,
        ]);
        $pendiente = $this->caja($pedido, ['orden' => 2, 'peso_real_kg' => 1, 'numero_rastreo' => 'GUIA-2']);

        $contenido = $this->rechazo(fn () => app(SincronizarCajasPedidoBmaService::class)->ejecutar($pedido->fresh(), [
            [
                'uuid_operativo' => $recolectada->uuid_operativo,
                'catalogo_tipo_caja_id' => $recolectada->catalogo_tipo_caja_id,
                'largo' => 99,
                'ancho' => 10,
                'alto' => 10,
                'peso_real_kg' => 1,
                'peso_volumetrico_kg' => 1,
            ],
            [
                'uuid_operativo' => $pendiente->uuid_operativo,
                'catalogo_tipo_caja_id' => $pendiente->catalogo_tipo_caja_id,
                'largo' => 10,
                'ancho' => 10,
                'alto' => 10,
                'peso_real_kg' => 1,
                'peso_volumetrico_kg' => 1,
            ],
        ], $this->usuario->id));
        $this->assertNotNull($contenido);
        $this->assertStringContainsString('contenido', $contenido->getMessage());
        $this->assertSame(10.0, (float) $recolectada->fresh()->largo);

        $guia = $this->rechazo(fn () => app(MarcarEnviadoPedidoBmaService::class)->ejecutar($pedido->fresh(), $this->usuario->id, [
            ['id' => $recolectada->id, 'numero_rastreo' => 'GUIA-NUEVA'],
        ]));
        $this->assertNotNull($guia);
        $this->assertStringContainsString('guía', $guia->getMessage());
        $this->assertSame('GUIA-1', $recolectada->fresh()->numero_rastreo);
        $this->assertTrue($pendiente->fresh()->estaPendiente());
        $this->assertSame(CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO, $pedido->fresh('estatus')->estatus->fase_ciclo);
    }

    /** @param  array<string, mixed>  $extra */
    public function test_recibido_sin_integracion_no_habilita_empaque_guia_ni_salida(): void
    {
        $envio = $this->pedido(CatalogoEstatusPedido::FASE_EN_CEDIS, [
            'numero_remision' => 'REM-INT',
            'pago_validado_at' => now(),
        ]);
        $envio->documentos()->create([
            'tipo' => \App\Models\ControlPedidos\PedidoBmaDocumento::TIPO_REMISION,
            'ruta_archivo' => 'test/remision.pdf',
            'nombre_original' => 'remision.pdf',
            'mime_type' => 'application/pdf',
            'tamano_bytes' => 100,
            'orden' => 0,
            'activo' => true,
        ]);
        $resguardo = $this->pedido(CatalogoEstatusPedido::FASE_BORRADOR, [
            'numero_remision' => 'REM-RES-INT',
            'es_resguardo' => true,
            'pago_validado_at' => now(),
        ]);
        $custodia = app(CustodiaComplementoNacional::class);
        $custodia->vincular($envio, $resguardo, $this->usuario->id);
        $custodia->registrarSalidaOrigen($resguardo->fresh(), $this->usuario->id);
        $custodia->confirmarRecepcionCedis($resguardo->fresh(), $this->usuario->id);
        $this->assertFalse($resguardo->fresh()->solicitud_preparacion_json['vinculo_resguardo']['integrado']);

        foreach ([
            fn () => app(MarcarEmpacadoPedidoBmaService::class)->ejecutar($envio->fresh(), $this->usuario->id),
            fn () => app(AsignarGuiaPedidoBmaService::class)->ejecutar($envio->fresh(), 'GUIA-NO', $this->usuario->id),
            fn () => app(MarcarEnviadoPedidoBmaService::class)->ejecutar($envio->fresh(), $this->usuario->id),
        ] as $accion) {
            $bloqueo = $this->rechazo($accion);
            $this->assertNotNull($bloqueo);
            $this->assertStringContainsString('integración', $bloqueo->getMessage());
        }
        $quieto = $envio->fresh('estatus');
        $this->assertSame(CatalogoEstatusPedido::FASE_EN_CEDIS, $quieto->estatus->fase_ciclo);
        $this->assertNull($quieto->empacado_at);
        $this->assertNull($quieto->numero_rastreo);

        $custodia->liberar($resguardo->fresh(), $this->usuario->id);
        $this->assertTrue($resguardo->fresh()->solicitud_preparacion_json['vinculo_resguardo']['integrado']);
        $empacado = app(MarcarEmpacadoPedidoBmaService::class)->ejecutar($envio->fresh(), $this->usuario->id);
        $this->assertNotNull($empacado->empacado_at);
        \Illuminate\Support\Facades\Storage::fake('local');
        $caja = $this->caja($envio, ['peso_real_kg' => 1]);
        app(\App\Services\ControlPedidos\GestionarGuiaPdfPedidoBmaService::class)->subir(
            $empacado, UploadedFile::fake()->create('integrada.pdf', 1, 'application/pdf'), $this->usuario->id, $caja->id
        );
        $conGuia = app(AsignarGuiaPedidoBmaService::class)->ejecutar($empacado->fresh(), 'GUIA-SI', $this->usuario->id);
        $this->assertSame('GUIA-SI', $conGuia->numero_rastreo);
        $enviado = app(MarcarEnviadoPedidoBmaService::class)->ejecutar($conGuia->fresh(), $this->usuario->id);
        $this->assertSame(CatalogoEstatusPedido::FASE_ENVIADO, $enviado->fresh('estatus')->estatus->fase_ciclo);
    }

    public function test_la_custodia_en_sucursal_recorre_recepcion_custodia_y_entrega(): void
    {
        $sucursal = Sucursal::query()->create(['codigo' => 'SUC-'.uniqid(), 'nombre' => 'Sucursal centro', 'activo' => true]);
        $tipo = \App\Models\CatalogoTipoAlmacen::query()->firstOrCreate(['nombre' => 'PDV'], ['activo' => true]);
        $almacen = Almacen::query()->create([
            'codigo' => 'PDV-'.uniqid(),
            'nombre' => 'PDV centro',
            'activo' => true,
            'sucursal_id' => $sucursal->id,
            'tipo_almacen_id' => $tipo->id,
            'permite_salida_pedidos' => true,
        ]);
        $pedido = $this->pedido(CatalogoEstatusPedido::FASE_PENDIENTE_DE_GUIA, [
            'numero_remision' => 'REM-BANDEJA',
            'total_mercancia' => 80,
            'pago_validado_at' => now(),
        ]);
        $eventos = app(\App\Services\ControlPedidos\Nacional\EventosCustodiaSucursalNacional::class);
        $abierto = app(CambiarNacionalATienda::class)->ejecutar($pedido, $this->usuario, $sucursal->id, $this->contexto($pedido, $pedido->catalogo_paqueteria_id));
        $this->assertSame('pendiente_recepcion', $abierto->solicitud_preparacion_json['cambio_a_tienda']['estado']);
        $this->assertNull($abierto->estatus_sucursal);
        $this->assertSame('nacional', $abierto->modalidad_entrega ?? $abierto->solicitud_preparacion_json['modalidad_entrega']);

        $recibido = $eventos->recibir($abierto->fresh(), $this->usuario->id, $this->contexto($abierto->fresh(), $abierto->catalogo_paqueteria_id));
        $this->assertSame(\App\Models\PuntoVenta\ResguardoPdv::ESTADO_RECIBIDO, $recibido->estado);
        $this->assertSame(\App\Support\ControlPedidos\EstatusSucursalPedidoBma::RECIBIDO, $pedido->fresh()->estatus_sucursal);
        $this->assertNotSame(\App\Support\ControlPedidos\EstatusSucursalPedidoBma::EN_CUSTODIA, $pedido->fresh()->estatus_sucursal);

        $enCustodia = $eventos->custodiar($pedido->fresh(), $this->usuario->id, $almacen->id, 'Anaquel 2', $this->contexto($pedido->fresh(), $pedido->catalogo_paqueteria_id));
        $this->assertSame(\App\Models\PuntoVenta\ResguardoPdv::ESTADO_EN_CUSTODIA, $enCustodia->estado);
        $this->assertSame(\App\Support\ControlPedidos\EstatusSucursalPedidoBma::EN_CUSTODIA, $pedido->fresh()->estatus_sucursal);

        Storage::fake('local');
        $entregado = $eventos->entregar($pedido->fresh(), $this->usuario->id, array_merge(
            $this->contexto($pedido->fresh(), $pedido->catalogo_paqueteria_id),
            ['evidencia' => UploadedFile::fake()->image('entrega.jpg')],
        ));
        $final = $pedido->fresh();
        $this->assertSame(\App\Models\PuntoVenta\ResguardoPdv::ESTADO_ENTREGADO, $entregado->estado);
        $this->assertNull($final->estatus_sucursal);
        $this->assertSame('REM-BANDEJA', $final->numero_remision);
        $this->assertNotNull($final->pago_validado_at);
        $this->assertSame('nacional', $final->solicitud_preparacion_json['modalidad_entrega']);
        $this->assertArrayNotHasKey('conversion_modalidad', $final->solicitud_preparacion_json);
        $this->assertSame(0, PedidoBmaTareaPreparacion::query()->count());
        $tipos = \App\Models\PuntoVenta\ResguardoPdvEvento::query()->pluck('tipo_evento')->all();
        $this->assertEqualsCanonicalizing([
            \App\Models\PuntoVenta\ResguardoPdvEvento::TIPO_RECEPCION_ESPERADA_CREADA,
            \App\Models\PuntoVenta\ResguardoPdvEvento::TIPO_RECEPCION_COMPLETA,
            \App\Models\PuntoVenta\ResguardoPdvEvento::TIPO_CUSTODIA_COMPLETA,
            \App\Models\PuntoVenta\ResguardoPdvEvento::TIPO_ENTREGA_TITULAR,
        ], $tipos);
    }

    public function test_http_de_custodia_remision_cambio_y_cancelacion(): void
    {
        $ajeno = User::factory()->create();
        $envio = $this->pedido(CatalogoEstatusPedido::FASE_EN_CEDIS, ['numero_remision' => 'REM-HTTP-ENV']);
        $resguardo = $this->pedido(CatalogoEstatusPedido::FASE_EN_CEDIS, ['numero_remision' => 'REM-HTTP-RES', 'es_resguardo' => true, 'pago_validado_at' => now()]);

        $this->actingAs($ajeno)->postJson('/control-pedidos/'.$envio->id.'/custodia-nacional', [
            'accion' => 'vincular',
            'resguardo_id' => $resguardo->id,
        ])->assertForbidden();

        $this->actingAs($this->usuario)->postJson('/control-pedidos/'.$envio->id.'/custodia-nacional', [
            'accion' => 'vincular',
            'resguardo_id' => $resguardo->id,
        ])->assertOk();
        $this->actingAs($this->usuario)->postJson('/control-pedidos/'.$envio->id.'/custodia-nacional', [
            'accion' => 'vincular',
            'resguardo_id' => $resguardo->id,
        ])->assertOk();
        $this->assertSame(1, PedidoBma::query()->where('pedido_principal_id', $envio->id)->count());

        $suelto = $this->pedido(CatalogoEstatusPedido::FASE_EN_CEDIS, ['es_resguardo' => true, 'numero_remision' => 'REM-SUELTO']);
        $this->actingAs($this->usuario)->postJson('/control-pedidos/'.$suelto->id.'/custodia-nacional', [
            'accion' => 'salida',
        ])->assertStatus(422);
        $this->assertNull($suelto->fresh()->solicitud_preparacion_json['vinculo_resguardo']['estado'] ?? null);

        $salido = $this->pedido(CatalogoEstatusPedido::FASE_ENVIADO, ['numero_rastreo' => 'GUIA-SALIO', 'numero_remision' => 'REM-SALIO']);
        $otro = $this->pedido(CatalogoEstatusPedido::FASE_EN_CEDIS, ['es_resguardo' => true, 'numero_remision' => 'REM-OTRO']);
        $this->actingAs($this->usuario)->postJson('/control-pedidos/'.$salido->id.'/custodia-nacional', [
            'accion' => 'vincular',
            'resguardo_id' => $otro->id,
        ])->assertStatus(422);
        $this->assertNull($otro->fresh()->pedido_principal_id);
        $this->assertSame('GUIA-SALIO', $salido->fresh()->numero_rastreo);

        $caja = $this->caja($envio);
        $solicitud = (string) Str::uuid();
        $this->actingAs($ajeno)->postJson('/control-pedidos/'.$envio->id.'/remision-caja', [
            'caja_id' => $caja->id,
            'solicitud_id' => $solicitud,
            'folio_externo' => 'EXT-1',
            'revision_esperada' => (int) $envio->fresh()->revision_registro,
            'base_captura' => RevisionCapturaPedido::base($envio->fresh()),
        ])->assertForbidden();
        $alta = $this->actingAs($this->usuario)->postJson('/control-pedidos/'.$envio->id.'/remision-caja', [
            'caja_id' => $caja->id,
            'solicitud_id' => $solicitud,
            'folio_externo' => 'EXT-1',
            'revision_esperada' => (int) $envio->fresh()->revision_registro,
            'base_captura' => RevisionCapturaPedido::base($envio->fresh()),
        ]);
        $alta->assertOk();
        $this->actingAs($this->usuario)->postJson('/control-pedidos/'.$envio->id.'/remision-caja', [
            'caja_id' => $caja->id,
            'solicitud_id' => $solicitud,
            'folio_externo' => 'EXT-1',
            'revision_esperada' => 99,
            'base_captura' => str_repeat('a', 64),
        ])->assertOk();
        $this->assertSame(1, PedidoBmaCajaRemision::query()->count());
        $this->actingAs($this->usuario)->postJson('/control-pedidos/'.$envio->id.'/remision-caja', [
            'caja_id' => $caja->id,
            'solicitud_id' => (string) Str::uuid(),
            'folio_externo' => 'EXT-2',
            'revision_esperada' => 99,
            'base_captura' => str_repeat('b', 64),
        ])->assertStatus(409);
        $this->assertSame(1, PedidoBmaCajaRemision::query()->count());

        $recolectada = $this->pedido(CatalogoEstatusPedido::FASE_ENVIADO, ['numero_rastreo' => 'GUIA-CAJA-YA']);
        $cajaCerrada = $this->caja($recolectada, ['estatus_recoleccion' => PedidoBmaCaja::ESTATUS_RECOLECTADA, 'numero_rastreo' => 'GUIA-CAJA-YA']);
        $this->actingAs($this->usuario)->postJson('/control-pedidos/'.$recolectada->id.'/remision-caja', [
            'caja_id' => $cajaCerrada->id,
            'solicitud_id' => (string) Str::uuid(),
            'folio_externo' => 'EXT-CERRADA',
            'revision_esperada' => (int) $recolectada->fresh()->revision_registro,
            'base_captura' => RevisionCapturaPedido::base($recolectada->fresh()),
        ])->assertStatus(422);
        $this->assertSame(1, PedidoBmaCajaRemision::query()->count());
        $this->assertTrue(collect(\Illuminate\Support\Facades\Schema::getIndexes('pedido_bma_caja_remisiones'))
            ->contains(fn (array $indice) => ($indice['unique'] ?? false) && in_array('exclusividad_activa', $indice['columns'], true)));

        $sucursal = Sucursal::query()->create(['codigo' => 'SUC-'.uniqid(), 'nombre' => 'Sucursal HTTP', 'activo' => true]);
        $tipoPdv = \App\Models\CatalogoTipoAlmacen::query()->firstOrCreate(['nombre' => 'PDV'], ['activo' => true]);
        $almacenPdv = Almacen::query()->create([
            'codigo' => 'PDV-'.uniqid(),
            'nombre' => 'PDV HTTP',
            'activo' => true,
            'sucursal_id' => $sucursal->id,
            'tipo_almacen_id' => $tipoPdv->id,
            'permite_salida_pedidos' => true,
        ]);
        $cambio = $this->pedido(CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO, [
            'numero_remision' => 'REM-CAMBIO',
            'numero_rastreo' => 'GUIA-CAMBIO',
            'pago_validado_at' => now(),
            'empacado_at' => now(),
        ]);
        $base = $this->contexto($cambio, $cambio->catalogo_paqueteria_id);
        $this->actingAs($ajeno)->postJson('/control-pedidos/'.$cambio->id.'/cambio-tienda-nacional', [
            'sucursal_destino_id' => $sucursal->id,
            'solicitud_id' => $base['solicitud_id'],
            'revision_esperada' => $base['revision_esperada'],
            'base_captura' => $base['base_captura'],
        ])->assertForbidden();
        $this->actingAs($this->usuario)->postJson('/control-pedidos/'.$cambio->id.'/cambio-tienda-nacional', [
            'sucursal_destino_id' => $sucursal->id,
            'solicitud_id' => $base['solicitud_id'],
            'revision_esperada' => $base['revision_esperada'],
            'base_captura' => $base['base_captura'],
        ])->assertOk();
        $this->actingAs($this->usuario)->postJson('/control-pedidos/'.$cambio->id.'/cambio-tienda-nacional', [
            'sucursal_destino_id' => $sucursal->id,
            'solicitud_id' => $base['solicitud_id'],
            'revision_esperada' => 0,
            'base_captura' => str_repeat('c', 64),
        ])->assertOk();
        $this->assertSame(1, \App\Models\PuntoVenta\ResguardoPdv::query()->where('pedido_bma_id', $cambio->id)->count());
        $this->assertSame('pendiente_recepcion', $cambio->fresh()->solicitud_preparacion_json['cambio_a_tienda']['estado']);
        $this->assertNotSame(\App\Support\ControlPedidos\EstatusSucursalPedidoBma::EN_CUSTODIA, $cambio->fresh()->estatus_sucursal);
        $piso = $this->contexto($cambio->fresh(), $cambio->catalogo_paqueteria_id);
        foreach (['recibir', 'custodiar', 'entregar'] as $accionPiso) {
            $this->actingAs($this->usuario)->postJson('/control-pedidos/'.$cambio->id.'/cambio-tienda-nacional', [
                'sucursal_destino_id' => $sucursal->id,
                'solicitud_id' => (string) Str::uuid(),
                'revision_esperada' => $piso['revision_esperada'],
                'base_captura' => $piso['base_captura'],
                'accion' => $accionPiso,
                'almacen_id' => $almacenPdv->id,
                'ubicacion' => 'Anaquel 1',
            ])->assertForbidden();
        }
        $this->assertSame(\App\Models\PuntoVenta\ResguardoPdv::ESTADO_PENDIENTE_RECEPCION, \App\Models\PuntoVenta\ResguardoPdv::query()->where('pedido_bma_id', $cambio->id)->value('estado'));
        $this->assertSame('pendiente_recepcion', $cambio->fresh()->solicitud_preparacion_json['cambio_a_tienda']['estado']);
        $this->assertSame('REM-CAMBIO', $cambio->fresh()->numero_remision);
        $this->assertSame('nacional', $cambio->fresh()->solicitud_preparacion_json['modalidad_entrega']);
        $this->assertNull($cambio->fresh()->numero_rastreo);

        $ya = $this->pedido(CatalogoEstatusPedido::FASE_ENVIADO, ['numero_rastreo' => 'GUIA-NO-CAMBIA', 'numero_remision' => 'REM-NO']);
        $baseYa = $this->contexto($ya, $ya->catalogo_paqueteria_id);
        $this->actingAs($this->usuario)->postJson('/control-pedidos/'.$ya->id.'/cambio-tienda-nacional', [
            'sucursal_destino_id' => $sucursal->id,
            'solicitud_id' => $baseYa['solicitud_id'],
            'revision_esperada' => $baseYa['revision_esperada'],
            'base_captura' => $baseYa['base_captura'],
        ])->assertStatus(422);
        $this->assertSame('GUIA-NO-CAMBIA', $ya->fresh()->numero_rastreo);
        $this->assertSame(0, \App\Models\PuntoVenta\ResguardoPdv::query()->where('pedido_bma_id', $ya->id)->count());

        $cancelable = $this->pedido(CatalogoEstatusPedido::FASE_EN_CEDIS, ['numero_remision' => 'REM-CAN', 'total_mercancia' => 30]);
        $hecho = $this->contexto($cancelable, $cancelable->catalogo_paqueteria_id);
        $this->actingAs($ajeno)->postJson('/control-pedidos/'.$cancelable->id.'/cancelacion-nacional', [
            'solicitud_id' => $hecho['solicitud_id'],
            'motivo' => 'Cliente desiste',
            'cedis_notificado' => 0,
            'liberar_reserva' => 0,
            'revision_esperada' => $hecho['revision_esperada'],
            'base_captura' => $hecho['base_captura'],
        ])->assertForbidden();
        $this->actingAs($this->usuario)->postJson('/control-pedidos/'.$cancelable->id.'/cancelacion-nacional', [
            'solicitud_id' => $hecho['solicitud_id'],
            'motivo' => 'Cliente desiste',
            'cedis_notificado' => 0,
            'liberar_reserva' => 1,
            'revision_esperada' => $hecho['revision_esperada'],
            'base_captura' => $hecho['base_captura'],
        ])->assertStatus(422);
        $this->assertNull($cancelable->fresh()->cancelado_at);
        $vigente = $this->contexto($cancelable->fresh(), $cancelable->catalogo_paqueteria_id);
        $this->actingAs($this->usuario)->postJson('/control-pedidos/'.$cancelable->id.'/cancelacion-nacional', [
            'solicitud_id' => $vigente['solicitud_id'],
            'motivo' => 'Cliente desiste',
            'cedis_notificado' => 1,
            'liberar_reserva' => 0,
            'revision_esperada' => 99,
            'base_captura' => str_repeat('d', 64),
        ])->assertStatus(409);
        $this->actingAs($this->usuario)->postJson('/control-pedidos/'.$cancelable->id.'/cancelacion-nacional', [
            'solicitud_id' => $vigente['solicitud_id'],
            'motivo' => 'Cliente desiste',
            'cedis_notificado' => 1,
            'liberar_reserva' => 0,
            'revision_esperada' => $vigente['revision_esperada'],
            'base_captura' => $vigente['base_captura'],
        ])->assertOk();
        $this->actingAs($this->usuario)->postJson('/control-pedidos/'.$cancelable->id.'/cancelacion-nacional', [
            'solicitud_id' => $vigente['solicitud_id'],
            'motivo' => 'Cliente desiste',
            'cedis_notificado' => 1,
            'liberar_reserva' => 0,
            'revision_esperada' => 0,
            'base_captura' => str_repeat('e', 64),
        ])->assertOk();
        $this->assertSame($vigente['solicitud_id'], $cancelable->fresh()->solicitud_preparacion_json['cancelacion_nacional']['solicitud_id']);
        $this->assertNull($cancelable->fresh()->cancelado_at);

        $cerrado = $this->pedido(CatalogoEstatusPedido::FASE_ENVIADO, ['numero_rastreo' => 'GUIA-CERRADA', 'numero_remision' => 'REM-CERRADA']);
        $baseCerrado = $this->contexto($cerrado, $cerrado->catalogo_paqueteria_id);
        $this->actingAs($this->usuario)->postJson('/control-pedidos/'.$cerrado->id.'/cancelacion-nacional', [
            'solicitud_id' => $baseCerrado['solicitud_id'],
            'motivo' => 'Ya salió',
            'cedis_notificado' => 1,
            'liberar_reserva' => 1,
            'revision_esperada' => $baseCerrado['revision_esperada'],
            'base_captura' => $baseCerrado['base_captura'],
        ])->assertStatus(422);
        $this->assertNull($cerrado->fresh()->cancelado_at);
        $this->assertSame('GUIA-CERRADA', $cerrado->fresh()->numero_rastreo);
    }

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

        return PedidoBma::query()->create(array_merge([
            'folio' => 'NAC-'.uniqid(),
            'folio_remision' => 'WIZ-'.uniqid(),
            'fecha' => now()->toDateString(),
            'vendedor_id' => $this->usuario->id,
            'origen_id' => $origen->id,
            'almacen_id' => $almacen->id,
            'catalogo_estatus_pedido_id' => $estatus->id,
            'catalogo_paqueteria_id' => CatalogoPaqueteriaPedido::query()->firstOrCreate(
                ['nombre' => 'DHL Ola3 '.uniqid()],
                ['categoria' => CatalogoPaqueteriaPedido::CATEGORIA_COMERCIAL, 'requiere_peso' => true, 'activo' => true, 'modalidad_tarifa' => CatalogoPaqueteriaPedido::MODALIDAD_FIJA]
            )->id,
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
            'largo' => 10,
            'ancho' => 10,
            'alto' => 10,
            'estado_operativo' => PedidoBmaCaja::ESTADO_ACTIVA,
            'estatus_recoleccion' => PedidoBmaCaja::ESTATUS_PENDIENTE,
        ], $extra));
    }

    private function hijoDe(PedidoBma $principal): ?PedidoBma
    {
        return PedidoBma::query()->where('solicitud_preparacion_json->complemento_nacional->pedido_origen_id', $principal->id)->first();
    }

    private function anexosDe(PedidoBma $principal): int
    {
        return PedidoBma::query()->where('solicitud_preparacion_json->complemento_nacional->pedido_origen_id', $principal->id)->count();
    }

    /** @return array<string, mixed> */
    private function datosComplemento(PedidoBma $pedido, float $monto, string $sku): array
    {
        $fresco = $pedido->fresh();

        return [
            'solicitud_id' => (string) Str::uuid(),
            'folio_wizerp' => 'WIZ-ANX-'.uniqid(),
            'piezas' => [['sku' => $sku, 'descripcion' => 'Pieza nueva', 'cantidad' => 1]],
            'total_mercancia_nueva' => $monto,
            'revision_esperada' => (int) $fresco->revision_registro,
            'base_captura' => RevisionCapturaPedido::base($fresco),
            'evidencia' => UploadedFile::fake()->image('anexo.jpg'),
        ];
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

    private function esperaRecepcion(PedidoBma $envio): void
    {
        $rechazo = $this->rechazo(fn () => BarreraRecepcionComplementoNacional::assert($envio->fresh()));
        $this->assertNotNull($rechazo);
        $this->assertStringContainsString('integración', $rechazo->getMessage());
    }

    private function activarDetalle(): void
    {
        \App\Models\ConfiguracionSistema::query()->updateOrCreate(
            ['clave' => \App\Services\ControlPedidos\EnviosPedidoBmaConfig::CLAVE_DETALLE],
            ['valor' => '1', 'tipo' => 'boolean', 'descripcion' => 'detalle', 'grupo' => 'control_pedidos']
        );
        app(\App\Services\ControlPedidos\EnviosPedidoBmaConfig::class)->olvidarCache();
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
}
