<?php

namespace Tests\Feature\PuntoVenta;

use App\Models\Almacen;
use App\Models\CatalogoTipoAlmacen;
use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\CatalogoModalidadPreparacionPedido;
use App\Models\ControlPedidos\CatalogoOrigenPedido;
use App\Models\ControlPedidos\CatalogoPaqueteriaPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Models\ControlPedidos\PedidoBmaHistorialEstado;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvEvento;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\ControlPedidos\MarcarEnviadoPedidoBmaService;
use App\Services\PuntoVenta\Resguardos\CrearRecepcionEsperadaPdvService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RecepcionEsperadaMunicipalTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    private Sucursal $sucursal;

    private Almacen $cedis;

    protected function setUp(): void
    {
        parent::setUp();

        $this->usuario = User::factory()->create();
        $this->sucursal = Sucursal::factory()->create(['activo' => true]);
        $tipo = CatalogoTipoAlmacen::query()->firstOrCreate(['nombre' => 'CEDIS'], ['activo' => true]);
        $this->cedis = Almacen::query()->create([
            'nombre' => 'CEDIS-REM', 'codigo' => 'CEDIS-REM', 'tipo_almacen_id' => $tipo->id,
            'activo' => true, 'visible_en_pedidos' => true,
        ]);
        $this->seedCatalogos();
    }

    public function test_salida_de_cedis_crea_recepcion_esperada_municipal(): void
    {
        $pedido = $this->pedido();

        app(MarcarEnviadoPedidoBmaService::class)->ejecutar($pedido->fresh(), $this->usuario->id);

        $resguardo = ResguardoPdv::query()->where('pedido_bma_id', $pedido->id)->firstOrFail();
        $this->assertSame(ResguardoPdv::ESTADO_PENDIENTE_RECEPCION, $resguardo->estado);
        $this->assertSame($this->sucursal->id, (int) $resguardo->sucursal_id);
        $this->assertSame('municipal', $resguardo->snapshot_json['origen']);
        $this->assertFalse($resguardo->esOrigenNacional());
        $this->assertSame(1, (int) $resguardo->cantidad_bultos_esperada);
        $this->assertTrue(ResguardoPdvEvento::query()->where('resguardo_id', $resguardo->id)
            ->where('idempotency_key', CrearRecepcionEsperadaPdvService::claveIdempotencia($pedido->id, $this->sucursal->id))->exists());
        $this->assertSame(CatalogoEstatusPedido::FASE_ENVIADO, $pedido->fresh('estatus')->estatus->fase_ciclo);
    }

    public function test_reintento_no_duplica_resguardo(): void
    {
        $pedido = $this->pedido();
        app(MarcarEnviadoPedidoBmaService::class)->ejecutar($pedido->fresh(), $this->usuario->id);

        app(MarcarEnviadoPedidoBmaService::class)->ejecutar($pedido->fresh(), $this->usuario->id);
        app(CrearRecepcionEsperadaPdvService::class)->ejecutar($pedido->fresh(), $this->usuario->id);

        $this->assertSame(1, ResguardoPdv::query()->where('pedido_bma_id', $pedido->id)->count());
    }

    public function test_salida_no_pide_guia_ni_rastreo(): void
    {
        $pedido = $this->pedido();
        $this->assertNull($pedido->numero_rastreo);

        app(MarcarEnviadoPedidoBmaService::class)->ejecutar($pedido->fresh(), $this->usuario->id);

        $pedido = $pedido->fresh('estatus');
        $this->assertSame(CatalogoEstatusPedido::FASE_ENVIADO, $pedido->estatus->fase_ciclo);
        $this->assertNull($pedido->numero_rastreo);
        $this->assertSame(0, PedidoBmaDocumento::query()->where('pedido_bma_id', $pedido->id)->where('tipo', PedidoBmaDocumento::TIPO_GUIA)->count());
    }

    public function test_historial_dice_salida_a_pdv_no_envio_a_terminal(): void
    {
        $pedido = $this->pedido();

        app(MarcarEnviadoPedidoBmaService::class)->ejecutar($pedido->fresh(), $this->usuario->id);

        $textos = PedidoBmaHistorialEstado::query()->where('pedido_bma_id', $pedido->id)->get()
            ->map(fn ($h) => json_encode($h->toArray(), JSON_UNESCAPED_UNICODE))->implode(' ');
        $this->assertStringContainsString('Salida de CEDIS hacia PDV', $textos);
        $this->assertSame('Salida de CEDIS hacia PDV', AccionesHistorialPedidoBma::etiqueta(
            PedidoBmaHistorialEstado::query()->where('pedido_bma_id', $pedido->id)->latest('id')->value('accion')
        ));
        $this->assertStringNotContainsStringIgnoringCase('entregado', $textos);
        $this->assertStringNotContainsStringIgnoringCase('terminal', $textos);
        $this->assertStringNotContainsStringIgnoringCase('Paquetería recogió', $textos);

        $notif = DB::table('notifications')->get()->map(fn ($n) => $n->data)->implode(' ');
        $this->assertStringNotContainsString('Paquetería recogió', $notif);
        $this->assertStringNotContainsStringIgnoringCase('terminal', $notif);
    }

    public function test_municipal_ya_en_flujo_viejo_con_tarea_no_crea_segundo_resguardo(): void
    {
        $pedido = $this->pedido();
        $modalidad = CatalogoModalidadPreparacionPedido::query()->where('codigo', CatalogoModalidadPreparacionPedido::CODIGO_ENVIO_MUNICIPIO)->firstOrFail();
        PedidoBmaTareaPreparacion::query()->create([
            'pedido_bma_id' => $pedido->id, 'catalogo_modalidad_preparacion_id' => $modalidad->id,
            'almacen_id' => $this->cedis->id, 'estado' => PedidoBmaTareaPreparacion::ESTADO_PENDIENTE,
            'area_responsable_codigo' => 'TIENDA', 'version' => 1,
        ]);

        try {
            app(CrearRecepcionEsperadaPdvService::class)->ejecutar($pedido->fresh(), $this->usuario->id);
            $this->fail('No debía crear recepción esperada.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('modalidad', $e->errors());
        }
        $this->assertSame(0, ResguardoPdv::query()->where('pedido_bma_id', $pedido->id)->count());
    }

    private function pedido(array $extra = [], bool $marca = true): PedidoBma
    {
        $json = ['modalidad_entrega' => 'municipal'];
        if ($marca) {
            $json['recorrido_fisico_municipal'] = true;
        }
        $comercial = CatalogoPaqueteriaPedido::query()->where('categoria', CatalogoPaqueteriaPedido::CATEGORIA_COMERCIAL)->first();

        return PedidoBma::query()->create(array_merge([
            'folio' => 'PED-REM-'.uniqid(),
            'folio_remision' => 'REM-REM-'.uniqid(),
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
            'solicitud_preparacion_json' => $json,
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
            'numero_cliente' => 'REM-1', 'nombre' => 'Cliente', 'lista_actual_id' => $lista, 'vendedor_id' => $this->usuario->id,
            'monto_venta_actual' => 0, 'created_at' => $now, 'updated_at' => $now,
        ]);
    }
}
