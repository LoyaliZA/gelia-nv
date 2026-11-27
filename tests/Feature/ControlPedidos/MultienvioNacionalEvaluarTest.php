<?php

namespace Tests\Feature\ControlPedidos;

use App\Models\Almacen;
use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\CatalogoOrigenPedido;
use App\Models\ControlPedidos\CatalogoPaqueteriaPedido;
use App\Models\ControlPedidos\CatalogoTipoCajaPedido;
use App\Models\ControlPedidos\MultienvioNacional;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaCaja;
use App\Models\User;
use App\Services\ControlPedidos\Nacional\Multienvio\EvaluarMultienvioNacional;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MultienvioNacionalEvaluarTest extends TestCase
{
    use RefreshDatabase;

    private User $ventas;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', config('database.default'));
        $this->ventas = User::factory()->create();
        CatalogoEstatusPedido::query()->firstOrCreate(
            ['fase_ciclo' => CatalogoEstatusPedido::FASE_EN_CEDIS],
            ['codigo_interno' => 'MEV-1', 'nombre_visual' => 'En CEDIS', 'color_hex' => '#999999', 'orden' => 1, 'activo' => true]
        );
    }

    public function test_una_caja_no_es_elegible(): void
    {
        $pedido = $this->pedidoNacional();
        $this->caja($pedido, 10);

        $r = app(EvaluarMultienvioNacional::class)->ejecutar($pedido);

        $this->assertFalse($r['elegible']);
        $this->assertSame(1, $r['n_cajas']);
        $this->assertNotNull($r['motivo']);
    }

    public function test_tres_cajas_con_consulta_cerrada_es_elegible(): void
    {
        $pedido = $this->pedidoNacional();
        foreach ([10, 12, 14] as $peso) {
            $this->caja($pedido, $peso);
        }

        $r = app(EvaluarMultienvioNacional::class)->ejecutar($pedido);

        $this->assertTrue($r['elegible']);
        $this->assertNull($r['motivo']);
        $this->assertSame(3, $r['n_cajas']);
        $this->assertNull($r['estado']);
        $this->assertNull($r['multienvio_id']);
        $this->assertSame(40, strlen($r['revision']));
    }

    public function test_devuelve_estado_del_grupo_existente(): void
    {
        $pedido = $this->pedidoNacional();
        $this->caja($pedido, 10);
        $this->caja($pedido, 11);
        $grupo = MultienvioNacional::query()->create([
            'uuid' => (string) Str::uuid(),
            'pedido_origen_id' => $pedido->id,
            'revision_consulta' => 'x',
            'estado' => MultienvioNacional::ESTADO_PENDIENTE_DECISION,
        ]);

        $r = app(EvaluarMultienvioNacional::class)->ejecutar($pedido);

        $this->assertSame(MultienvioNacional::ESTADO_PENDIENTE_DECISION, $r['estado']);
        $this->assertSame($grupo->id, $r['multienvio_id']);
    }

    public function test_consulta_abierta_no_es_elegible(): void
    {
        $pedido = $this->pedidoNacional(['consulta_cerrada_at' => null]);
        $this->caja($pedido, 10);
        $this->caja($pedido, 11);

        $r = app(EvaluarMultienvioNacional::class)->ejecutar($pedido);

        $this->assertFalse($r['elegible']);
        $this->assertSame(2, $r['n_cajas']);
    }

    public function test_tienda_o_municipal_no_es_elegible(): void
    {
        foreach (['tienda', 'municipal'] as $modalidad) {
            $pedido = $this->pedidoNacional(['solicitud_preparacion_json' => ['modalidad_entrega' => $modalidad]]);
            $this->caja($pedido, 10);
            $this->caja($pedido, 11);

            $this->assertFalse(app(EvaluarMultienvioNacional::class)->ejecutar($pedido)['elegible'], $modalidad);
        }
    }

    public function test_complemento_no_es_elegible(): void
    {
        $principal = $this->pedidoNacional();
        $hijo = $this->pedidoNacional(['pedido_principal_id' => $principal->id]);
        $this->caja($hijo, 10);
        $this->caja($hijo, 11);

        $this->assertFalse(app(EvaluarMultienvioNacional::class)->ejecutar($hijo)['elegible']);
    }

    public function test_caja_retirada_no_cuenta(): void
    {
        $pedido = $this->pedidoNacional();
        $this->caja($pedido, 10);
        $this->caja($pedido, 11);
        $this->caja($pedido, 12, PedidoBmaCaja::ESTADO_RETIRADA);

        $r = app(EvaluarMultienvioNacional::class)->ejecutar($pedido);

        $this->assertSame(2, $r['n_cajas']);
        $this->assertTrue($r['elegible']);
    }

    public function test_la_revision_cambia_si_cambia_una_caja(): void
    {
        $pedido = $this->pedidoNacional();
        $this->caja($pedido, 10);
        $caja = $this->caja($pedido, 11);
        $servicio = app(EvaluarMultienvioNacional::class);

        $antes = $servicio->ejecutar($pedido)['revision'];
        $this->assertSame($antes, $servicio->ejecutar($pedido)['revision']);

        $caja->update(['peso_cobrado_kg' => 15]);

        $this->assertNotSame($antes, $servicio->ejecutar($pedido->fresh())['revision']);
    }

    public function test_un_miembro_con_dos_cajas_no_es_elegible_como_origen_de_otro_grupo(): void
    {
        $miembro = $this->miembroConDosCajas();

        $r = app(EvaluarMultienvioNacional::class)->ejecutar($miembro);

        $this->assertFalse($r['elegible']);
        $this->assertSame('El pedido ya es un envío de un multienvío.', $r['motivo']);
        $this->assertSame(2, $r['n_cajas']);
    }

    public function test_el_origen_de_un_grupo_activo_explica_que_ya_esta_activo(): void
    {
        $origen = $this->pedidoNacional();
        $this->caja($origen, 10);
        $grupo = MultienvioNacional::query()->create([
            'uuid' => (string) Str::uuid(), 'pedido_origen_id' => $origen->id, 'revision_consulta' => 'x',
            'estado' => MultienvioNacional::ESTADO_ACTIVO,
        ]);
        $origen->forceFill(['multienvio_nacional_id' => $grupo->id, 'multienvio_orden' => 1])->save();

        $r = app(EvaluarMultienvioNacional::class)->ejecutar($origen->fresh());

        $this->assertFalse($r['elegible']);
        $this->assertSame('El multienvío ya está activo.', $r['motivo']);
    }

    public function miembroConDosCajas(): PedidoBma
    {
        $origen = $this->pedidoNacional();
        $grupo = MultienvioNacional::query()->create([
            'uuid' => (string) Str::uuid(), 'pedido_origen_id' => $origen->id, 'revision_consulta' => 'x',
            'estado' => MultienvioNacional::ESTADO_ACTIVO,
        ]);
        $miembro = $this->pedidoNacional();
        $miembro->forceFill(['multienvio_nacional_id' => $grupo->id, 'multienvio_orden' => 2])->save();
        $this->caja($miembro, 10);
        $this->caja($miembro, 12);

        return $miembro->fresh();
    }

    private function caja(PedidoBma $pedido, float $peso, string $estado = PedidoBmaCaja::ESTADO_ACTIVA): PedidoBmaCaja
    {
        $tipo = CatalogoTipoCajaPedido::query()->firstOrCreate(
            ['nombre' => 'CAJA #202'],
            ['peso_volumetrico' => 10, 'largo' => 46, 'ancho' => 32, 'alto' => 35, 'activo' => true]
        );

        return $pedido->cajas()->create([
            'uuid_operativo' => (string) Str::uuid(),
            'catalogo_tipo_caja_id' => $tipo->id,
            'orden' => $pedido->cajas()->count(),
            'largo' => 46,
            'ancho' => 32,
            'alto' => 35,
            'peso_real_kg' => $peso,
            'peso_volumetrico_kg' => 10.304,
            'peso_cobrado_kg' => $peso,
            'estado_operativo' => $estado,
        ]);
    }

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
                ['nombre' => 'DHL Multi '.uniqid()],
                ['categoria' => CatalogoPaqueteriaPedido::CATEGORIA_COMERCIAL, 'requiere_peso' => true, 'activo' => true, 'modalidad_tarifa' => CatalogoPaqueteriaPedido::MODALIDAD_FIJA]
            )->id,
            'solicitud_preparacion_json' => [
                'modalidad_entrega' => 'nacional',
                'captura_nacional' => 'compartida',
                'codigo_modalidad' => 'ENVIO_BODEGA_NORMAL',
            ],
            'consulta_cerrada_at' => now(),
            'total_mercancia' => 100,
            'costo_envio' => 0,
            'saldo_a_favor' => 0,
            'costo_seguro' => 0,
            'total_a_cobrar' => 100,
            'aplica_seguro' => false,
            'es_resguardo' => false,
        ], $extra));
    }
}
