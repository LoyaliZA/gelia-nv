<?php

namespace Tests\Feature\ControlPedidos;

use App\Models\Almacen;
use App\Models\CatalogoTipoAlmacen;
use App\Models\ConfiguracionSistema;
use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\CatalogoOrigenPedido;
use App\Models\ControlPedidos\CatalogoPaqueteriaPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaRevisionProducto;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvBulto;
use App\Models\PuntoVenta\ResguardoPdvEvidencia;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\ControlPedidos\Nacional\CambiarNacionalATienda;
use App\Services\ControlPedidos\Nacional\CustodiaComplementoNacional;
use App\Services\ControlPedidos\Nacional\RegistrarComplementoNacional;
use App\Services\PuntoVenta\PuntoVentaModulo;
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

class NacionalOla3CierreTest extends TestCase
{
    use RefreshDatabase;

    private User $ventas;

    private User $cedis;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', config('database.default'));
        $this->withoutMiddleware([ValidateCsrfToken::class, PreventRequestForgery::class]);
        $this->mock(\App\Services\ControlPedidos\NotificarPedidoBmaService::class)->shouldReceive('ejecutar')->zeroOrMoreTimes();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->ventas = User::factory()->create();
        $this->ventas->givePermissionTo(Permission::findOrCreate('control_pedidos.crear', 'web'));
        $this->cedis = User::factory()->create();
        $this->cedis->givePermissionTo([
            Permission::findOrCreate('control_pedidos.cedis', 'web'),
            Permission::findOrCreate('control_pedidos.cedis.ver_todos', 'web'),
        ]);
        $orden = 1;
        foreach ([
            CatalogoEstatusPedido::FASE_BORRADOR => 'Borrador',
            CatalogoEstatusPedido::FASE_EN_CEDIS => 'En CEDIS',
            CatalogoEstatusPedido::FASE_PENDIENTE_DE_GUIA => 'Pendiente de guía',
            CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO => 'Pendiente de envío',
            CatalogoEstatusPedido::FASE_ENVIADO => 'Enviado',
        ] as $fase => $nombre) {
            CatalogoEstatusPedido::query()->firstOrCreate(
                ['fase_ciclo' => $fase],
                ['codigo_interno' => 'N3C-'.$orden, 'nombre_visual' => $nombre, 'color_hex' => '#999999', 'orden' => $orden, 'activo' => true]
            );
            $orden++;
        }
    }

    public function test_el_complemento_identifica_sku_y_captura_sin_copiar_el_principal(): void
    {
        Storage::fake('public');
        $pedido = $this->pedidoNacional([
            'numero_remision' => 'REM-ID',
            'total_mercancia' => 100,
            'pago_validado_at' => now(),
        ]);
        $pedido->revisionesProducto()->create([
            'descripcion_producto' => 'Pieza ya pagada',
            'sku' => 'SKU-VIEJO',
            'cantidad_solicitada' => 2,
            'estado_fisico' => PedidoBmaRevisionProducto::ESTADO_BUENO,
            'orden' => 1,
        ]);
        $datos = $this->contexto($pedido);
        $captura = UploadedFile::fake()->image('captura.jpg');

        $hijo = app(RegistrarComplementoNacional::class)->ejecutar($pedido, $this->ventas, [
            ...$datos,
            'total_mercancia_nueva' => 40,
            'piezas' => [
                ['sku' => 'SKU-NUEVA', 'descripcion' => 'Crema nueva', 'cantidad' => 1],
                ['sku' => '', 'descripcion' => 'Aroma de captura', 'cantidad' => 2, 'evidencia' => $captura],
            ],
        ], [UploadedFile::fake()->image('soporte.jpg')]);

        $this->assertSame('REM-ID', $pedido->fresh()->numero_remision);
        $this->assertSame('100.00', number_format((float) $pedido->fresh()->total_mercancia, 2, '.', ''));
        $this->assertSame(1, $pedido->fresh()->revisionesProducto()->count());
        $this->assertSame('SKU-VIEJO', $pedido->fresh()->revisionesProducto()->value('sku'));
        $this->assertSame(0, $pedido->documentos()->count());
        $this->assertSame('40.00', number_format((float) $hijo->total_mercancia, 2, '.', ''));
        $piezas = $hijo->solicitud_preparacion_json['complemento_nacional']['piezas'];
        $this->assertSame('SKU-NUEVA', $piezas[0]['sku']);
        $this->assertSame('Crema nueva', $piezas[0]['descripcion']);
        $this->assertSame('', $piezas[1]['sku']);
        $this->assertSame('Aroma de captura', $piezas[1]['descripcion']);
        $this->assertNotEquals('Complemento', $piezas[1]['descripcion']);
        $this->assertNotEmpty($piezas[1]['documento_ids'] ?? []);
        $this->assertSame(2, $hijo->revisionesProducto()->count());
        $this->assertSame('SKU-NUEVA', $hijo->revisionesProducto()->orderBy('id')->value('sku'));
        $capturada = $hijo->revisionesProducto()->where('sku', '')->first();
        $this->assertSame('Aroma de captura', $capturada->descripcion_producto);
        $this->assertNull($capturada->cantidad_encontrada);
        $this->assertGreaterThan(1, $hijo->documentos()->count());
        $this->assertTrue($hijo->documentos()->where('relacion_id', $capturada->id)->exists());

        $misma = $datos['solicitud_id'];
        $otro = app(RegistrarComplementoNacional::class)->ejecutar($pedido->fresh(), $this->ventas, [
            ...$datos,
            'solicitud_id' => $misma,
            'total_mercancia_nueva' => 40,
            'piezas' => [['sku' => 'SKU-NUEVA', 'descripcion' => 'Crema nueva', 'cantidad' => 1]],
        ], [UploadedFile::fake()->image('soporte.jpg')]);
        $this->assertSame($hijo->id, $otro->id);
        $this->assertSame(1, PedidoBma::query()->where('pedido_principal_id', $pedido->id)->count());
    }

    public function test_rechaza_pieza_sin_sku_descripcion_ni_archivo(): void
    {
        Storage::fake('public');
        $pedido = $this->pedidoNacional(['numero_remision' => 'REM-VACIA', 'pago_validado_at' => now()]);

        $this->expectException(\InvalidArgumentException::class);
        app(RegistrarComplementoNacional::class)->ejecutar($pedido, $this->ventas, [
            ...$this->contexto($pedido),
            'total_mercancia_nueva' => 10,
            'piezas' => [['sku' => '', 'descripcion' => '', 'cantidad' => 1]],
        ], [UploadedFile::fake()->image('soporte.jpg')]);
    }

    public function test_cedis_responde_las_piezas_del_hijo_sin_mezclar_al_padre(): void
    {
        Storage::fake('public');
        $pedido = $this->pedidoNacional([
            'numero_remision' => 'REM-CEDIS',
            'pago_validado_at' => now(),
        ]);
        $hijo = app(RegistrarComplementoNacional::class)->ejecutar($pedido, $this->ventas, [
            ...$this->contexto($pedido),
            'total_mercancia_nueva' => 25,
            'piezas' => [
                ['sku' => 'SKU-A', 'descripcion' => 'Pieza A', 'cantidad' => 1],
                ['sku' => '', 'descripcion' => 'Pieza B', 'cantidad' => 1, 'evidencia' => UploadedFile::fake()->image('b.jpg')],
            ],
        ], [UploadedFile::fake()->image('soporte.jpg')]);
        $revision = $hijo->revisionesProducto()->where('sku', 'SKU-A')->first();
        $ajeno = User::factory()->create();
        $payload = [
            'solicitud_id' => (string) Str::uuid(),
            'piezas' => [[
                'revision_id' => $revision->id,
                'cantidad_encontrada' => 1,
                'estado_fisico' => PedidoBmaRevisionProducto::ESTADO_BUENO,
            ]],
            'revision_esperada' => (int) $pedido->fresh()->revision_registro,
            'base_captura' => RevisionCapturaPedido::base($pedido->fresh()),
        ];

        $this->actingAs($ajeno)->postJson('/control-pedidos/cedis/'.$pedido->id.'/responder-complemento-nacional', $payload)
            ->assertForbidden();
        $this->actingAs($this->ventas)->postJson('/control-pedidos/cedis/'.$pedido->id.'/responder-complemento-nacional', $payload)
            ->assertForbidden();
        $this->actingAs($this->cedis)->postJson('/control-pedidos/cedis/'.$pedido->id.'/responder-complemento-nacional', [
            ...$payload,
            'revision_esperada' => 99,
            'base_captura' => str_repeat('x', 64),
        ])->assertStatus(409);
        $this->actingAs($this->cedis)->postJson('/control-pedidos/cedis/'.$pedido->id.'/responder-complemento-nacional', $payload)
            ->assertOk();
        $this->actingAs($this->cedis)->postJson('/control-pedidos/cedis/'.$pedido->id.'/responder-complemento-nacional', $payload)
            ->assertOk();

        $this->assertSame(1, (int) $revision->fresh()->cantidad_encontrada);
        $this->assertNull($hijo->revisionesProducto()->where('sku', '')->value('cantidad_encontrada'));
        $this->assertSame(0, $pedido->fresh()->revisionesProducto()->where('sku', 'SKU-A')->count());
        $this->assertSame(0, $pedido->documentos()->count());
        $this->assertSame(1, $hijo->revisionesProducto()->whereNotNull('cantidad_encontrada')->count());
    }

    public function test_la_sucursal_recorre_recepcion_custodia_y_entrega_con_foto(): void
    {
        Storage::fake('local');
        ConfiguracionSistema::query()->updateOrCreate(['clave' => PuntoVentaModulo::CLAVE_FLAG], ['valor' => '1']);
        foreach (PuntoVentaModulo::permisosIniciales() as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }
        $sucursal = Sucursal::query()->create(['codigo' => 'SUC-'.uniqid(), 'nombre' => 'Sucursal cierre', 'activo' => true]);
        $tipo = CatalogoTipoAlmacen::query()->firstOrCreate(['nombre' => 'PDV'], ['activo' => true]);
        $almacen = Almacen::query()->create([
            'codigo' => 'PDV-'.uniqid(),
            'nombre' => 'PDV cierre',
            'activo' => true,
            'sucursal_id' => $sucursal->id,
            'tipo_almacen_id' => $tipo->id,
            'permite_salida_pedidos' => true,
        ]);
        $pedido = $this->pedidoNacional([
            'numero_remision' => 'REM-PDV',
            'total_mercancia' => 80,
            'pago_validado_at' => now(),
            'costo_envio' => 55,
        ]);
        $pdv = User::factory()->create();
        $pdv->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_RESGUARDOS_VER,
            PuntoVentaModulo::PERMISO_RESGUARDOS_CONFIRMAR_LLEGADA,
            PuntoVentaModulo::PERMISO_RESGUARDOS_CONFIRMAR_CUSTODIA,
            PuntoVentaModulo::PERMISO_RESGUARDOS_ENTREGAR,
        ]);
        $pdv->concederAccesoSucursal($sucursal, esPrincipal: true);
        $soloVer = User::factory()->create();
        $soloVer->givePermissionTo([PuntoVentaModulo::PERMISO_ACCEDER, PuntoVentaModulo::PERMISO_RESGUARDOS_VER]);
        $soloVer->concederAccesoSucursal($sucursal, esPrincipal: true);

        $abierto = app(CambiarNacionalATienda::class)->ejecutar($pedido, $this->ventas, $sucursal->id, $this->contexto($pedido));
        $resguardo = ResguardoPdv::query()->where('pedido_bma_id', $abierto->id)->firstOrFail();
        $this->assertSame(ResguardoPdv::ESTADO_PENDIENTE_RECEPCION, $resguardo->estado);
        $this->assertNull($abierto->estatus_sucursal);
        $this->assertSame('55.00', number_format((float) $abierto->costo_envio, 2, '.', ''));

        $this->actingAs($soloVer)->putJson(route('punto_venta.resguardos.recepcion', $resguardo), [
            'version' => (int) $resguardo->version,
            'idempotency_key' => 'nac-rec-ver',
        ])->assertForbidden();

        $this->actingAs($pdv)->putJson(route('punto_venta.resguardos.recepcion', $resguardo), [
            'version' => (int) $resguardo->version,
            'idempotency_key' => 'nac-rec-1',
        ])->assertOk();
        $this->assertSame(ResguardoPdv::ESTADO_RECIBIDO, $resguardo->fresh()->estado);
        $this->assertSame(0, ResguardoPdvBulto::query()->where('resguardo_id', $resguardo->id)->count());

        $this->actingAs($pdv)->putJson(route('punto_venta.resguardos.custodia', $resguardo), [
            'version' => (int) $resguardo->fresh()->version,
            'idempotency_key' => 'nac-cus-1',
            'almacen_id' => $almacen->id,
            'ubicacion' => 'Anaquel 4',
        ])->assertOk();
        $this->assertSame(ResguardoPdv::ESTADO_EN_CUSTODIA, $resguardo->fresh()->estado);

        $this->actingAs($pdv)->putJson(route('punto_venta.resguardos.entrega', $resguardo->fresh()), [
            'version' => (int) $resguardo->fresh()->version,
            'idempotency_key' => 'nac-ent-sin',
            'relacion' => 'titular',
            'nombre_quien_retira' => 'Cliente',
            'metodo_validacion' => 'fotografia_t02',
        ])->assertStatus(422);
        $this->assertSame(ResguardoPdv::ESTADO_EN_CUSTODIA, $resguardo->fresh()->estado);

        $this->actingAs($pdv)->put(route('punto_venta.resguardos.entrega', $resguardo->fresh()), [
            'version' => (int) $resguardo->fresh()->version,
            'idempotency_key' => 'nac-ent-1',
            'relacion' => 'titular',
            'nombre_quien_retira' => 'Cliente',
            'metodo_validacion' => 'fotografia_t02',
            'evidencias' => [UploadedFile::fake()->image('entrega.jpg')],
        ])->assertOk();
        $this->assertSame(ResguardoPdv::ESTADO_ENTREGADO, $resguardo->fresh()->estado);
        $this->assertSame('REM-PDV', $pedido->fresh()->numero_remision);
        $this->assertSame('nacional', $pedido->fresh()->solicitud_preparacion_json['modalidad_entrega']);
        $this->assertGreaterThan(0, ResguardoPdvEvidencia::query()->where('resguardo_id', $resguardo->id)->count());
    }

    public function test_ventas_no_confirma_recepcion_custodia_ni_entrega_por_cambio_tienda_nacional(): void
    {
        ConfiguracionSistema::query()->updateOrCreate(['clave' => PuntoVentaModulo::CLAVE_FLAG], ['valor' => '1']);
        foreach (PuntoVentaModulo::permisosIniciales() as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }
        $sucursal = Sucursal::query()->create(['codigo' => 'SUC-'.uniqid(), 'nombre' => 'Sucursal auth', 'activo' => true]);
        $tipo = CatalogoTipoAlmacen::query()->firstOrCreate(['nombre' => 'PDV'], ['activo' => true]);
        $almacen = Almacen::query()->create([
            'codigo' => 'PDV-'.uniqid(),
            'nombre' => 'PDV auth',
            'activo' => true,
            'sucursal_id' => $sucursal->id,
            'tipo_almacen_id' => $tipo->id,
            'permite_salida_pedidos' => true,
        ]);
        $pedido = $this->pedidoNacional([
            'numero_remision' => 'REM-AUTH',
            'total_mercancia' => 80,
            'pago_validado_at' => now(),
            'empacado_at' => now(),
        ]);
        $pdv = User::factory()->create();
        $pdv->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_RESGUARDOS_VER,
            PuntoVentaModulo::PERMISO_RESGUARDOS_CONFIRMAR_LLEGADA,
            PuntoVentaModulo::PERMISO_RESGUARDOS_CONFIRMAR_CUSTODIA,
            PuntoVentaModulo::PERMISO_RESGUARDOS_ENTREGAR,
        ]);
        $pdv->concederAccesoSucursal($sucursal, esPrincipal: true);

        $this->actingAs($this->ventas)->postJson('/control-pedidos/'.$pedido->id.'/cambio-tienda-nacional', array_merge(
            $this->contexto($pedido),
            ['sucursal_destino_id' => $sucursal->id]
        ))->assertOk();
        $pedido = $pedido->fresh();
        $resguardo = ResguardoPdv::query()->where('pedido_bma_id', $pedido->id)->firstOrFail();
        $this->assertSame(ResguardoPdv::ESTADO_PENDIENTE_RECEPCION, $resguardo->estado);

        $piso = array_merge($this->contexto($pedido), [
            'sucursal_destino_id' => $sucursal->id,
            'almacen_id' => $almacen->id,
            'ubicacion' => 'Anaquel 9',
        ]);
        foreach (['recibir', 'custodiar', 'entregar'] as $accion) {
            $this->actingAs($this->ventas)->postJson('/control-pedidos/'.$pedido->id.'/cambio-tienda-nacional', array_merge($piso, [
                'solicitud_id' => (string) Str::uuid(),
                'accion' => $accion,
            ]))->assertForbidden();
        }
        $this->assertSame(ResguardoPdv::ESTADO_PENDIENTE_RECEPCION, $resguardo->fresh()->estado);
        $this->assertSame('pendiente_recepcion', $pedido->fresh()->solicitud_preparacion_json['cambio_a_tienda']['estado']);

        $this->actingAs($pdv)->putJson(route('punto_venta.resguardos.recepcion', $resguardo), [
            'version' => (int) $resguardo->version,
            'idempotency_key' => 'auth-rec-1',
        ])->assertOk();
        $this->assertSame(ResguardoPdv::ESTADO_RECIBIDO, $resguardo->fresh()->estado);
    }

    public function test_cedis_recibe_y_libera_el_vinculo_sin_habilitar_antes(): void
    {
        $envio = $this->pedidoNacional(['numero_remision' => 'REM-ENV-V']);
        $resguardo = $this->pedidoNacional(['numero_remision' => 'REM-RES-V', 'es_resguardo' => true, 'pago_validado_at' => now()]);
        app(CustodiaComplementoNacional::class)->vincular($envio, $resguardo, $this->ventas->id);

        $this->actingAs($this->cedis)->postJson('/control-pedidos/cedis/'.$resguardo->id.'/custodia-nacional', [
            'accion' => 'recepcion',
        ])->assertStatus(422);
        $this->actingAs($this->ventas)->postJson('/control-pedidos/'.$resguardo->id.'/custodia-nacional', [
            'accion' => 'salida',
        ])->assertOk();
        $this->assertSame('en_traslado', $resguardo->fresh()->solicitud_preparacion_json['vinculo_resguardo']['estado']);
        $this->actingAs($this->ventas)->postJson('/control-pedidos/cedis/'.$resguardo->id.'/custodia-nacional', [
            'accion' => 'recepcion',
        ])->assertForbidden();
        $this->actingAs($this->cedis)->postJson('/control-pedidos/cedis/'.$resguardo->id.'/custodia-nacional', [
            'accion' => 'liberar',
        ])->assertStatus(422);
        $this->actingAs($this->cedis)->postJson('/control-pedidos/cedis/'.$resguardo->id.'/custodia-nacional', [
            'accion' => 'recepcion',
        ])->assertOk();
        $this->assertSame('recibido_cedis', $resguardo->fresh()->solicitud_preparacion_json['vinculo_resguardo']['estado']);
        $this->assertFalse($resguardo->fresh()->solicitud_preparacion_json['vinculo_resguardo']['integrado']);
        $this->actingAs($this->cedis)->postJson('/control-pedidos/cedis/'.$resguardo->id.'/custodia-nacional', [
            'accion' => 'recepcion',
        ])->assertOk();
        $this->actingAs($this->cedis)->postJson('/control-pedidos/cedis/'.$resguardo->id.'/custodia-nacional', [
            'accion' => 'liberar',
        ])->assertOk();
        $this->assertTrue($resguardo->fresh()->solicitud_preparacion_json['vinculo_resguardo']['integrado']);
    }

    /** @param  array<string, mixed>  $extra */
    private function pedidoNacional(array $extra = []): PedidoBma
    {
        $estatus = CatalogoEstatusPedido::query()->where('fase_ciclo', CatalogoEstatusPedido::FASE_EN_CEDIS)->firstOrFail();
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
            'vendedor_id' => $this->ventas->id,
            'origen_id' => $origen->id,
            'almacen_id' => $almacen->id,
            'catalogo_estatus_pedido_id' => $estatus->id,
            'catalogo_paqueteria_id' => CatalogoPaqueteriaPedido::query()->firstOrCreate(
                ['nombre' => 'DHL Cierre '.uniqid()],
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

    /** @return array<string, mixed> */
    private function contexto(PedidoBma $pedido): array
    {
        $fresco = $pedido->fresh();

        return [
            'solicitud_id' => (string) Str::uuid(),
            'revision_esperada' => (int) $fresco->revision_registro,
            'base_captura' => RevisionCapturaPedido::base($fresco),
        ];
    }
}
