<?php

namespace Tests\Feature\ControlPedidos;

use App\Models\Almacen;
use App\Models\CatalogoTipoAlmacen;
use App\Models\Cliente;
use App\Models\ConfiguracionSistema;
use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\CatalogoModalidadPreparacionPedido;
use App\Models\ControlPedidos\CatalogoOrigenPedido;
use App\Models\ControlPedidos\CatalogoPaqueteriaPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Models\Departamento;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\ControlPedidos\NotificarPedidoBmaService;
use App\Services\ControlPedidos\PreparacionTiendaConfig;
use App\Support\ControlPedidos\Conversion\CodigosConversionModalidad;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Confirmar la conversión Tienda → Municipal no resuelve la sucursal que recibe el envío:
 * esa resolución pertenece solo al guardado de la solicitud municipal.
 */
class ConversionMunicipalSucursalDestinoTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    private Departamento $departamento;

    private CatalogoPaqueteriaPedido $transporte;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([ValidateCsrfToken::class, PreventRequestForgery::class]);
        Storage::fake('local');
        Storage::fake('public');
        Cache::flush();
        $this->mock(NotificarPedidoBmaService::class)->shouldReceive('ejecutar')->zeroOrMoreTimes();

        $this->departamento = Departamento::query()->create(['nombre' => 'Ventas', 'activo' => true]);
        $this->usuario = User::factory()->create(['departamento_id' => $this->departamento->id]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (['control_pedidos.crear', 'control_pedidos.tienda.ver', 'clientes.expediente.ver', 'clientes.expediente.editar', 'clientes.expediente.documentos'] as $permiso) {
            $this->usuario->givePermissionTo(Permission::findOrCreate($permiso, 'web'));
        }
        CatalogoEstatusPedido::query()->firstOrCreate(
            ['fase_ciclo' => CatalogoEstatusPedido::FASE_BORRADOR],
            ['codigo_interno' => 'BORRADOR', 'nombre_visual' => 'Borrador', 'color_hex' => '#999999', 'orden' => 1, 'activo' => true]
        );
        foreach ([CatalogoModalidadPreparacionPedido::CODIGO_RECOGE_TIENDA => 'Recoge en tienda', CatalogoModalidadPreparacionPedido::CODIGO_ENVIO_MUNICIPIO => 'Envío municipio'] as $codigo => $nombre) {
            CatalogoModalidadPreparacionPedido::query()->firstOrCreate(['codigo' => $codigo], ['nombre' => $nombre, 'activo' => true]);
        }
        $this->transporte = CatalogoPaqueteriaPedido::query()->create([
            'nombre' => 'Transporte ficticio', 'sin_logistica' => false,
            'categoria' => CatalogoPaqueteriaPedido::CATEGORIA_LOCAL_REGIONAL, 'activo' => true,
            'permite_costo_diferido' => true, 'habilitado_envio_municipio' => true,
            'permite_por_cobrar' => false, 'reglas_municipio_pendientes' => false,
        ]);
    }

    public function test_tienda_con_sucursal_fuera_de_la_lista_piloto_confirma_conversion_a_municipal(): void
    {
        $piloto = Sucursal::factory()->create(['activo' => true]);
        $fuera = Sucursal::factory()->create(['activo' => true]);
        $pdvPiloto = $this->almacenPdv('PDV-PILOTO', $piloto);
        $this->almacenPdv('PDV-FUERA', $fuera);
        ConfiguracionSistema::query()->updateOrCreate(
            ['clave' => PreparacionTiendaConfig::CLAVE_FLAG],
            ['valor' => json_encode(['activo' => true, 'almacenes_habilitados' => [$pdvPiloto->id]])]
        );
        app(PreparacionTiendaConfig::class)->olvidarCache();

        $pedido = $this->pedidoTienda($fuera);
        $this->confirmar($pedido)->assertOk()
            ->assertJsonPath('modalidad_entrega', CodigosConversionModalidad::DESTINO_MUNICIPAL);

        $fresco = $pedido->fresh();
        $this->assertSame('municipal', $fresco->solicitud_preparacion_json['modalidad_entrega']);
        $this->assertSame($fuera->id, (int) $fresco->sucursal_destino_id);
    }

    public function test_tienda_con_catalogo_pdv_vacio_confirma_conversion_a_municipal(): void
    {
        $sucursal = Sucursal::factory()->create(['activo' => true]);
        $pedido = $this->pedidoTienda($sucursal);

        $this->confirmar($pedido)->assertOk()
            ->assertJsonPath('modalidad_entrega', CodigosConversionModalidad::DESTINO_MUNICIPAL);
        $this->assertSame('municipal', $pedido->fresh()->solicitud_preparacion_json['modalidad_entrega']);
    }

    private function almacenPdv(string $codigo, Sucursal $sucursal): Almacen
    {
        $tipo = CatalogoTipoAlmacen::query()->firstOrCreate(['nombre' => 'PDV'], ['activo' => true]);

        return Almacen::query()->create([
            'codigo' => $codigo, 'nombre' => $codigo, 'activo' => true, 'visible_en_pedidos' => true,
            'permite_salida_pedidos' => true, 'tipo_almacen_id' => $tipo->id, 'sucursal_id' => $sucursal->id,
        ]);
    }

    private function pedidoTienda(Sucursal $sucursal): PedidoBma
    {
        $listaId = DB::table('catalogo_listas_descuento')->insertGetId([
            'nombre' => 'Lista '.uniqid(), 'activo' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $cliente = Cliente::query()->create([
            'numero_cliente' => (string) random_int(10000, 99999), 'nombre' => 'Cliente ficticio',
            'lista_actual_id' => $listaId, 'vendedor_id' => $this->usuario->id,
        ]);
        $origen = CatalogoOrigenPedido::query()->create([
            'codigo' => 'ORI-'.uniqid(), 'nombre' => 'Mostrador '.uniqid(), 'requiere_logistica' => true, 'activo' => true,
        ]);
        $salida = Almacen::query()->create([
            'codigo' => 'CED-'.uniqid(), 'nombre' => 'Salida general', 'activo' => true,
            'visible_en_pedidos' => true, 'permite_salida_pedidos' => true,
        ]);
        $pedido = PedidoBma::query()->create([
            'folio' => 'INT-'.uniqid(), 'fecha' => now()->toDateString(),
            'vendedor_id' => $this->usuario->id, 'cliente_id' => $cliente->id,
            'departamento_operacion_id' => $this->departamento->id, 'origen_id' => $origen->id,
            'almacen_id' => $salida->id, 'sucursal_destino_id' => $sucursal->id,
            'catalogo_estatus_pedido_id' => CatalogoEstatusPedido::porFase(CatalogoEstatusPedido::FASE_BORRADOR)->id,
            'total_mercancia' => 100, 'costo_envio' => 0, 'saldo_a_favor' => 0, 'costo_seguro' => 0,
            'total_a_cobrar' => 100, 'aplica_seguro' => false, 'es_resguardo' => false, 'prioridad_md' => false,
            'solicitud_preparacion_json' => ['version' => 1, 'modalidad_entrega' => 'tienda'],
        ]);
        PedidoBmaDocumento::query()->create([
            'pedido_bma_id' => $pedido->id, 'tipo' => PedidoBmaDocumento::TIPO_PDF_PEDIDO,
            'ruta_archivo' => 'pedidos_bma/pedido-ficticio.pdf', 'nombre_original' => 'pedido-ficticio.pdf',
            'mime_type' => 'application/pdf', 'tamano_bytes' => 1200, 'orden' => 1, 'activo' => true,
        ]);

        return $pedido;
    }

    private function confirmar(PedidoBma $pedido): \Illuminate\Testing\TestResponse
    {
        $instancia = (string) Str::uuid();
        $this->actingAs($this->usuario)->postJson("/control-pedidos/{$pedido->id}/conversion-modalidad/reserva", [
            'instancia_id' => $instancia,
        ])->assertOk();
        $consignatario = $this->actingAs($this->usuario)->postJson(
            route('control_pedidos.municipal.expediente.store', $pedido->cliente_id),
            ['nombre' => 'Persona ficticia', 'telefono' => '3311111111', 'pedido_id' => $pedido->id]
        )->assertCreated()->json('id');
        $pedido = $pedido->fresh();

        return $this->actingAs($this->usuario)->post("/control-pedidos/{$pedido->id}/conversion-modalidad", [
            'destino' => CodigosConversionModalidad::DESTINO_MUNICIPAL,
            'instancia_id' => $instancia,
            'motivo' => 'El cliente pide envío municipal',
            'error_captura' => true,
            'catalogo_paqueteria_id' => $this->transporte->id,
            'consignatario_id' => (int) $consignatario,
            'almacen_id' => null,
            'leyenda_terminal' => 'PAGADO',
            'costo_transporte' => 'diferido',
            'costo_envio' => null,
            'revision_esperada' => (int) $pedido->revision_registro,
            'conversion_version' => 0,
            'clave_idempotencia' => 'idem-suc-'.uniqid(),
        ], ['Accept' => 'application/json']);
    }
}
