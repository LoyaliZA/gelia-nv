<?php

namespace Tests\Feature\PuntoVenta;

use App\Models\Almacen;
use App\Models\CatalogoTipoAlmacen;
use App\Models\ConfiguracionSistema;
use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\CatalogoOrigenPedido;
use App\Models\ControlPedidos\CatalogoPaqueteriaPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvBulto;
use App\Models\PuntoVenta\ResguardoPdvEvento;
use App\Models\PuntoVenta\ResguardoPdvIncidencia;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\ControlPedidos\MarcarEnviadoPedidoBmaService;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Support\PuntoVenta\Resguardos\BandejaResguardoPdv;
use App\Support\PuntoVenta\Resguardos\BultosEsperadosResguardoPdv;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CustodiaMunicipalPdvTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    private User $gerencia;

    private Sucursal $sucursal;

    private Sucursal $otraSucursal;

    private Almacen $cedis;

    private Almacen $piso;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('Super Admin', 'web');
        $this->withoutMiddleware([ValidateCsrfToken::class, PreventRequestForgery::class]);
        $this->withHeader('Accept', 'application/json');
        Storage::fake('local');
        ConfiguracionSistema::query()->updateOrCreate(['clave' => PuntoVentaModulo::CLAVE_FLAG], ['valor' => '1']);
        foreach (PuntoVentaModulo::permisosIniciales() as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }

        $this->usuario = User::factory()->create();
        $this->sucursal = Sucursal::factory()->create(['activo' => true, 'nombre' => 'Sucursal Norte']);
        $this->otraSucursal = Sucursal::factory()->create(['activo' => true, 'nombre' => 'Sucursal Sur']);
        $tipo = CatalogoTipoAlmacen::query()->firstOrCreate(['nombre' => 'CEDIS'], ['activo' => true]);
        $this->cedis = Almacen::query()->create([
            'nombre' => 'CEDIS-REM', 'codigo' => 'CEDIS-REM', 'tipo_almacen_id' => $tipo->id,
            'activo' => true, 'visible_en_pedidos' => true,
        ]);
        $this->piso = Almacen::query()->create([
            'codigo' => 'PISO-1', 'nombre' => 'Piso recepción', 'sucursal_id' => $this->sucursal->id, 'activo' => true,
        ]);
        $this->seedCatalogos();

        $this->gerencia = $this->usuarioPdv($this->sucursal);
    }

    public function test_gerencia_confirma_recepcion_y_pasa_a_custodia(): void
    {
        $resguardo = $this->resguardoMunicipalPendiente();
        $folios = BultosEsperadosResguardoPdv::desdeResguardo($resguardo);

        $this->actingAs($this->gerencia)->put(route('punto_venta.resguardos.recepcion', $resguardo), $this->payloadRecepcion($resguardo, 'mun:rec'))
            ->assertOk()->assertJsonPath('resguardo.estado', ResguardoPdv::ESTADO_RECIBIDO);

        $resguardo->refresh();
        $this->actingAs($this->gerencia)->put(route('punto_venta.resguardos.pasar_recepcion', $resguardo), $this->payloadRecepcion($resguardo, 'mun:pasar'))
            ->assertOk()->assertJsonPath('resguardo.estado', ResguardoPdv::ESTADO_EN_RECEPCION);

        $resguardo->refresh();
        $this->actingAs($this->gerencia)->put(route('punto_venta.resguardos.custodia', $resguardo), [
            'version' => (int) $resguardo->version,
            'idempotency_key' => 'mun:custodia',
            'almacen_id' => $this->piso->id,
            'bultos' => [[
                'folio' => $folios[0]['folio'], 'tipo' => ResguardoPdvBulto::TIPO_CAJA, 'condicion' => 'bueno', 'piezas' => 1,
            ]],
        ])->assertOk()
            ->assertJsonPath('resguardo.estado', ResguardoPdv::ESTADO_EN_CUSTODIA)
            ->assertJsonPath('resguardo.custodia_completa', true);

        $resguardo->refresh();
        $this->assertSame('municipal', $resguardo->snapshot_json['origen']);
        $this->assertNotNull($resguardo->custodia_confirmada_at);
    }

    public function test_municipal_en_custodia_aparece_en_bandeja_de_la_sucursal(): void
    {
        $resguardo = $this->resguardoMunicipalPendiente();
        $resguardo->update(['estado' => ResguardoPdv::ESTADO_EN_CUSTODIA, 'custodia_confirmada_at' => now()]);

        $ids = collect($this->actingAs($this->gerencia)->getJson(route('punto_venta.resguardos.listado', [
            'bandeja' => BandejaResguardoPdv::EN_CUSTODIA,
        ]))->assertOk()->json('resguardos.data'))->pluck('id');
        $this->assertTrue($ids->contains($resguardo->id));

        $ajeno = $this->usuarioPdv($this->otraSucursal);
        $idsAjeno = collect($this->actingAs($ajeno)->getJson(route('punto_venta.resguardos.listado', [
            'bandeja' => BandejaResguardoPdv::EN_CUSTODIA,
        ]))->assertOk()->json('resguardos.data'))->pluck('id');
        $this->assertFalse($idsAjeno->contains($resguardo->id));
    }

    public function test_recepcion_parcial_o_incidencia_abierta_no_pasa_a_custodia(): void
    {
        $resguardo = $this->resguardoMunicipalPendiente(['cantidad_bultos_esperada' => 2]);
        $payload = $this->payloadRecepcion($resguardo, 'mun:parcial');
        $payload['folios'] = [$payload['folios'][0]];
        $payload['condicion'] = 'danado';
        $payload['observacion'] = 'Caja rota';
        $this->actingAs($this->gerencia)->put(route('punto_venta.resguardos.recepcion', $resguardo), $payload)
            ->assertOk()->assertJsonPath('resguardo.estado', ResguardoPdv::ESTADO_PENDIENTE_RECEPCION);

        $this->assertSame(1, ResguardoPdvIncidencia::query()->where('resguardo_id', $resguardo->id)
            ->where('estado', ResguardoPdvIncidencia::ESTADO_ABIERTA)->count());

        $resguardo->refresh();
        $this->actingAs($this->gerencia)->put(route('punto_venta.resguardos.pasar_recepcion', $resguardo), $this->payloadRecepcion($resguardo, 'mun:pasar-parcial'))
            ->assertUnprocessable();
        $this->assertSame(ResguardoPdv::ESTADO_PENDIENTE_RECEPCION, $resguardo->fresh()->estado);
        $this->assertNull($resguardo->fresh()->custodia_confirmada_at);
    }

    public function test_usuario_de_otra_sucursal_no_confirma(): void
    {
        $resguardo = $this->resguardoMunicipalPendiente();
        $ajeno = $this->usuarioPdv($this->otraSucursal);

        $this->actingAs($ajeno)->put(route('punto_venta.resguardos.recepcion', $resguardo), $this->payloadRecepcion($resguardo, 'mun:ajeno'))
            ->assertForbidden();

        $this->assertSame(ResguardoPdv::ESTADO_PENDIENTE_RECEPCION, $resguardo->fresh()->estado);
        $this->assertSame(0, $resguardo->bultos()->count());
    }

    public function test_detalle_de_municipal_en_custodia_usa_etiqueta_compartida_y_conserva_eventos(): void
    {
        $resguardo = $this->resguardoMunicipalPendiente();
        $folios = BultosEsperadosResguardoPdv::desdeResguardo($resguardo);
        $this->actingAs($this->gerencia)->put(route('punto_venta.resguardos.recepcion', $resguardo), $this->payloadRecepcion($resguardo, 'mun:d-rec'))->assertOk();
        $resguardo->refresh();
        $this->put(route('punto_venta.resguardos.pasar_recepcion', $resguardo), $this->payloadRecepcion($resguardo, 'mun:d-pasar'))->assertOk();
        $resguardo->refresh();
        $this->put(route('punto_venta.resguardos.custodia', $resguardo), [
            'version' => (int) $resguardo->version,
            'idempotency_key' => 'mun:d-custodia',
            'almacen_id' => $this->piso->id,
            'bultos' => [['folio' => $folios[0]['folio'], 'tipo' => ResguardoPdvBulto::TIPO_CAJA, 'condicion' => 'bueno', 'piezas' => 1]],
        ])->assertOk();

        $this->getJson(route('punto_venta.resguardos.show', $resguardo))
            ->assertOk()
            ->assertJsonPath('resguardo.estado', ResguardoPdv::ESTADO_EN_CUSTODIA)
            ->assertJsonPath('resguardo.estado_etiqueta', 'En custodia')
            ->assertJsonPath('resguardo.custodia_completa', true)
            ->assertJsonPath('resguardo.integracion_municipal', null);

        $tipos = ResguardoPdvEvento::query()->where('resguardo_id', $resguardo->id)->pluck('tipo_evento')->all();
        foreach ([
            ResguardoPdvEvento::TIPO_RECEPCION_ESPERADA_CREADA,
            ResguardoPdvEvento::TIPO_RECEPCION_COMPLETA,
            ResguardoPdvEvento::TIPO_PASADO_A_RECEPCION,
            ResguardoPdvEvento::TIPO_CUSTODIA_COMPLETA,
        ] as $tipo) {
            $this->assertContains($tipo, $tipos);
        }
    }

    private function usuarioPdv(Sucursal $sucursal): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_RESGUARDOS_VER,
            PuntoVentaModulo::PERMISO_RESGUARDOS_CONFIRMAR_LLEGADA,
            PuntoVentaModulo::PERMISO_RESGUARDOS_ENVIAR_A_CUSTODIA,
            PuntoVentaModulo::PERMISO_RESGUARDOS_CONFIRMAR_CUSTODIA,
        ]);
        $user->concederAccesoSucursal($sucursal, esPrincipal: true);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function resguardoMunicipalPendiente(array $extra = []): ResguardoPdv
    {
        $pedido = $this->pedido(['numero_cajas' => $extra['cantidad_bultos_esperada'] ?? 1]);
        app(MarcarEnviadoPedidoBmaService::class)->ejecutar($pedido->fresh(), $this->usuario->id);
        $resguardo = ResguardoPdv::query()->where('pedido_bma_id', $pedido->id)->firstOrFail();
        if ($extra !== []) {
            $resguardo->update($extra);
        }

        $resguardo = $resguardo->fresh();
        $this->assertSame('municipal', $resguardo->snapshot_json['origen']);

        return $resguardo;
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadRecepcion(ResguardoPdv $resguardo, string $clave): array
    {
        return [
            'condicion' => 'bueno',
            'foto_paquete' => UploadedFile::fake()->image('llegada.jpg'),
            'folios' => array_column(BultosEsperadosResguardoPdv::desdeResguardo($resguardo), 'folio'),
            'version' => (int) $resguardo->version,
            'idempotency_key' => $clave,
        ];
    }

    private function pedido(array $extra = []): PedidoBma
    {
        $comercial = CatalogoPaqueteriaPedido::query()->where('categoria', CatalogoPaqueteriaPedido::CATEGORIA_COMERCIAL)->first();

        return PedidoBma::query()->create(array_merge([
            'folio' => 'PED-CUS-'.uniqid(),
            'folio_remision' => 'REM-CUS-'.uniqid(),
            'fecha' => now()->toDateString(),
            'vendedor_id' => $this->usuario->id,
            'cliente_id' => DB::table('clientes')->value('id'),
            'origen_id' => CatalogoOrigenPedido::query()->where('nombre', 'Envío Foráneo')->value('id'),
            'almacen_id' => $this->cedis->id,
            'sucursal_destino_id' => $this->sucursal->id,
            'catalogo_banco_id' => DB::table('catalogo_bancos')->value('id'),
            'catalogo_tipo_caja_id' => DB::table('catalogo_tipos_caja_pedido')->value('id'),
            'numero_cajas' => 1,
            'peso_real_kg' => 1.5,
            'catalogo_paqueteria_id' => $comercial->id,
            'catalogo_tipo_guia_id' => DB::table('catalogo_tipos_guia_pedido')->value('id'),
            'catalogo_zona_id' => DB::table('catalogo_zonas_pedido')->value('id'),
            'total_mercancia' => 1000,
            'costo_envio' => 0,
            'catalogo_estatus_pedido_id' => CatalogoEstatusPedido::porFase(CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO)->id,
            'es_resguardo' => false,
            'pago_validado_at' => now(),
            'pago_validado_por_id' => $this->usuario->id,
            'empacado_at' => now(),
            'solicitud_preparacion_json' => ['modalidad_entrega' => 'municipal', 'recorrido_fisico_municipal' => true],
        ], $extra));
    }

    private function seedCatalogos(): void
    {
        $now = now();
        foreach ([
            ['BORRADOR', 'BORRADOR', 1], ['AMARILLO', 'EN_CEDIS', 3],
            ['PENDIENTE_ENVIO', 'PENDIENTE_DE_ENVIO', 10], ['ENTREGADO', 'ENTREGADO', 8], ['ENVIADO', 'ENVIADO', 9],
        ] as [$codigo, $fase, $orden]) {
            CatalogoEstatusPedido::query()->firstOrCreate(['codigo_interno' => $codigo], [
                'nombre_visual' => $codigo, 'color_hex' => '#3B82F6', 'fase_ciclo' => $fase, 'orden' => $orden, 'activo' => true,
            ]);
        }
        CatalogoOrigenPedido::query()->firstOrCreate(['nombre' => 'Envío Foráneo'], ['requiere_logistica' => true, 'activo' => true]);
        DB::table('catalogo_bancos')->insertOrIgnore(['nombre' => 'BBVA', 'activo' => true, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('catalogo_listas_descuento')->insertOrIgnore(['nombre' => 'Lista Test', 'activo' => true, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('catalogo_paqueterias_pedido')->insertOrIgnore(['nombre' => 'PAQ COMERCIAL', 'categoria' => CatalogoPaqueteriaPedido::CATEGORIA_COMERCIAL, 'activo' => true, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('catalogo_tipos_caja_pedido')->insertOrIgnore(['nombre' => 'CAJA TEST', 'peso_volumetrico' => 1, 'activo' => true, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('catalogo_tipos_guia_pedido')->insertOrIgnore(['nombre' => 'Terrestre', 'activo' => true, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('catalogo_zonas_pedido')->insertOrIgnore(['nombre' => 'Zona 1', 'activo' => true, 'created_at' => $now, 'updated_at' => $now]);
        $lista = DB::table('catalogo_listas_descuento')->value('id');
        DB::table('clientes')->insertOrIgnore([
            'numero_cliente' => 'CUS-1', 'nombre' => 'Cliente', 'lista_actual_id' => $lista, 'vendedor_id' => $this->usuario->id,
            'monto_venta_actual' => 0, 'created_at' => $now, 'updated_at' => $now,
        ]);
    }
}
