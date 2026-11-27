<?php

namespace Tests\Feature\ControlPedidos;

use App\Models\Almacen;
use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\CatalogoOrigenPedido;
use App\Models\ControlPedidos\CatalogoPaqueteriaPedido;
use App\Models\ControlPedidos\CatalogoTipoCajaPedido;
use App\Models\ControlPedidos\CatalogoZonaPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaCaja;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/** Vista previa de la cotización nacional: calcula con el código de guardado y no escribe. */
class NacionalVistaPreviaCotizacionTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([ValidateCsrfToken::class, PreventRequestForgery::class]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->usuario = User::factory()->create();
        $this->usuario->givePermissionTo(Permission::findOrCreate('control_pedidos.crear', 'web'));
        CatalogoEstatusPedido::query()->firstOrCreate(
            ['fase_ciclo' => CatalogoEstatusPedido::FASE_PESAJE_RESPONDIDO],
            ['codigo_interno' => 'PESAJE_NAC', 'nombre_visual' => 'Pesaje', 'color_hex' => '#999999', 'orden' => 3, 'activo' => true]
        );
    }

    public function test_estafeta_cobra_2_5_por_ciento_sobre_venta_mas_guia(): void
    {
        [$pedido, $caja] = $this->pedidoConCaja('ESTAFETA');

        $this->vistaPrevia($pedido, $caja, 200, true)
            ->assertOk()
            ->assertJsonPath('venta', 1000)
            ->assertJsonPath('flete', 200)
            ->assertJsonPath('seguro', 30)
            ->assertJsonPath('total', 1230)
            ->assertJsonPath('cobertura_seguro', true);
    }

    public function test_dhl_cobra_2_por_ciento_mas_51(): void
    {
        [$pedido, $caja] = $this->pedidoConCaja('DHL');

        $this->vistaPrevia($pedido, $caja, 200, true)->assertOk()->assertJsonPath('seguro', 75);
    }

    public function test_el_seguro_apagado_no_se_cobra(): void
    {
        [$pedido, $caja] = $this->pedidoConCaja('FEDEX');

        $this->vistaPrevia($pedido, $caja, 200, false)
            ->assertOk()
            ->assertJsonPath('seguro', 0)
            ->assertJsonPath('total', 1200);
    }

    public function test_una_paqueteria_sin_cobertura_no_cobra_seguro_aunque_se_active(): void
    {
        [$pedido, $caja] = $this->pedidoConCaja('PAQUETEXPRESS');

        $this->vistaPrevia($pedido, $caja, 200, true)
            ->assertOk()
            ->assertJsonPath('seguro', 0)
            ->assertJsonPath('cobertura_seguro', false);
    }

    public function test_la_reexpedicion_se_muestra_aparte_y_suma_a_la_guia(): void
    {
        [$pedido, $caja] = $this->pedidoConCaja('ESTAFETA');
        $zona = CatalogoZonaPedido::query()->create(['nombre' => 'Entrega directa', 'costo_adicional' => 50, 'activo' => true]);

        $this->vistaPrevia($pedido, $caja, 200, true, $zona->id)
            ->assertOk()
            ->assertJsonPath('reexpedicion', 50)
            ->assertJsonPath('guia', 250)
            ->assertJsonPath('seguro', 30)
            ->assertJsonPath('total', 1280);
    }

    public function test_no_modifica_el_pedido_ni_las_cajas(): void
    {
        [$pedido, $caja] = $this->pedidoConCaja('DHL');

        $this->vistaPrevia($pedido, $caja, 200, true)->assertOk();

        $this->assertSame(0.0, (float) $pedido->fresh()->costo_envio);
        $this->assertFalse((bool) $pedido->fresh()->aplica_seguro);
        $this->assertNull($caja->fresh()->costo_envio);
        $this->assertSame((int) $pedido->revision_registro, (int) $pedido->fresh()->revision_registro);
    }

    public function test_un_usuario_sin_permiso_recibe_403(): void
    {
        [$pedido, $caja] = $this->pedidoConCaja('DHL');
        $this->usuario->revokePermissionTo('control_pedidos.crear');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->vistaPrevia($pedido, $caja, 200, true)->assertForbidden();
    }

    public function test_un_flete_vacio_responde_sin_error(): void
    {
        [$pedido, $caja] = $this->pedidoConCaja('DHL');

        $this->vistaPrevia($pedido, $caja, 0, true)->assertOk()->assertJsonPath('flete', 0);
    }

    public function test_dhl_con_flete_cero_cobra_seguro_sobre_mercancia(): void
    {
        [$pedido, $caja] = $this->pedidoConCaja('DHL');

        $this->vistaPrevia($pedido, $caja, 0, true)
            ->assertOk()
            ->assertJsonPath('venta', 1000)
            ->assertJsonPath('flete', 0)
            ->assertJsonPath('seguro', 71)
            ->assertJsonPath('total', 1071);
    }

    public function test_el_catalogo_marca_que_paqueterias_tienen_cobertura_de_seguro(): void
    {
        $this->pedidoConCaja('DHL');
        $this->pedidoConCaja('PAQUETEXPRESS');

        $paqueterias = app(\App\Services\ControlPedidos\ObtenerCatalogosPedidoBmaService::class)->ejecutar()['paqueterias'];

        $this->assertTrue($paqueterias->firstWhere('nombre', 'DHL')->cobertura_seguro);
        $this->assertFalse($paqueterias->firstWhere('nombre', 'PAQUETEXPRESS')->cobertura_seguro);
    }

    private function vistaPrevia(PedidoBma $pedido, PedidoBmaCaja $caja, float $flete, bool $seguro, ?int $zonaId = null)
    {
        return $this->actingAs($this->usuario)->postJson('/control-pedidos/'.$pedido->id.'/cotizacion-nacional/vista-previa', [
            'catalogo_paqueteria_id' => $pedido->catalogo_paqueteria_id,
            'catalogo_zona_id' => $zonaId,
            'aplica_seguro' => $seguro,
            'lineas' => [['uuid_operativo' => $caja->uuid_operativo, 'costo_envio' => $flete]],
        ]);
    }

    /** @return array{0: PedidoBma, 1: PedidoBmaCaja} */
    private function pedidoConCaja(string $paqueteria): array
    {
        $origen = CatalogoOrigenPedido::query()->create(['codigo' => 'NAC-'.uniqid(), 'nombre' => 'Nacional '.uniqid(), 'requiere_logistica' => true, 'activo' => true]);
        $almacen = Almacen::query()->create(['codigo' => 'CEDIS-'.uniqid(), 'nombre' => 'CEDIS', 'activo' => true, 'permite_salida_pedidos' => true]);
        $catalogo = CatalogoPaqueteriaPedido::query()->updateOrCreate(['nombre' => $paqueteria], [
            'sin_logistica' => false,
            'categoria' => CatalogoPaqueteriaPedido::CATEGORIA_COMERCIAL,
            'requiere_peso' => true,
            'activo' => true,
            'modalidad_tarifa' => CatalogoPaqueteriaPedido::MODALIDAD_FIJA,
        ]);
        $pedido = PedidoBma::query()->create([
            'folio' => 'NAC-'.uniqid(),
            'folio_remision' => 'WIZ-'.uniqid(),
            'fecha' => now()->toDateString(),
            'vendedor_id' => $this->usuario->id,
            'origen_id' => $origen->id,
            'almacen_id' => $almacen->id,
            'catalogo_estatus_pedido_id' => CatalogoEstatusPedido::query()->where('fase_ciclo', CatalogoEstatusPedido::FASE_PESAJE_RESPONDIDO)->value('id'),
            'catalogo_paqueteria_id' => $catalogo->id,
            'solicitud_preparacion_json' => ['modalidad_entrega' => 'nacional', 'captura_nacional' => 'compartida', 'codigo_modalidad' => 'ENVIO_BODEGA_NORMAL'],
            'total_mercancia' => 1000,
            'costo_envio' => 0,
            'saldo_a_favor' => 0,
            'costo_seguro' => 0,
            'total_a_cobrar' => 1000,
            'aplica_seguro' => false,
            'es_resguardo' => false,
        ]);
        $caja = PedidoBmaCaja::query()->create([
            'pedido_bma_id' => $pedido->id,
            'uuid_operativo' => (string) Str::uuid(),
            'catalogo_tipo_caja_id' => CatalogoTipoCajaPedido::query()->firstOrCreate(['nombre' => 'Caja nacional'], ['activo' => true])->id,
            'orden' => 1,
            'estado_operativo' => PedidoBmaCaja::ESTADO_ACTIVA,
            'estatus_recoleccion' => PedidoBmaCaja::ESTATUS_PENDIENTE,
        ]);

        return [$pedido, $caja];
    }
}
