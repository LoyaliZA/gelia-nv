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
use App\Services\ControlPedidos\Nacional\Multienvio\GuardarBorradorMultienvio;
use App\Services\ControlPedidos\Nacional\Multienvio\MultienvioNacionalException;
use App\Services\ControlPedidos\Nacional\Multienvio\RegistrarDecisionMultienvio;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

class MultienvioNacionalBorradorTest extends TestCase
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

    public function test_guarda_borrador_sin_crear_pedidos(): void
    {
        [$pedido, $grupo, $uuids] = $this->escenario();
        $antes = PedidoBma::query()->count();

        $r = $this->guardar($grupo, [
            $this->envio($uuids[0], 'F-1', 40, 'Pieza A'),
            $this->envio($uuids[1], null, 60),
        ]);

        $this->assertSame($antes, PedidoBma::query()->count());
        $this->assertCount(2, $r->borrador_json);
        $this->assertSame($uuids[0], $r->borrador_json[0]['uuid_operativo']);
        $this->assertSame('F-1', $r->borrador_json[0]['folio_remision']);
        $this->assertSame('Pieza A', $r->borrador_json[0]['contenido']);
        $this->assertNull($r->borrador_json[1]['folio_remision']);
        $this->assertSame(MultienvioNacional::ESTADO_CONFIGURANDO, $r->estado);
    }

    public function test_borrador_se_puede_reescribir(): void
    {
        [, $grupo, $uuids] = $this->escenario();
        $this->guardar($grupo, [$this->envio($uuids[0], 'F-1', 40), $this->envio($uuids[1], 'F-2', 60)]);

        $r = $this->guardar($grupo->fresh(), [$this->envio($uuids[2], 'F-3', 100)]);

        $this->assertCount(1, $r->borrador_json);
        $this->assertSame($uuids[2], $r->borrador_json[0]['uuid_operativo']);
    }

    public function test_uuid_ajeno_lanza_caja_no_pertenece(): void
    {
        [, $grupo] = $this->escenario();
        $ajeno = (string) Str::uuid();

        $this->expectExceptionObject(MultienvioNacionalException::cajaNoPertenece($ajeno));

        $this->guardar($grupo, [$this->envio($ajeno, null, 10)]);
    }

    public function test_caja_retirada_se_rechaza(): void
    {
        [$pedido, $grupo, $uuids] = $this->escenario();
        $pedido->cajas()->where('uuid_operativo', $uuids[0])->update(['estado_operativo' => PedidoBmaCaja::ESTADO_RETIRADA]);

        $this->expectExceptionObject(MultienvioNacionalException::cajaNoPertenece($uuids[0]));

        $this->guardar($grupo, [$this->envio($uuids[0], null, 10)]);
    }

    public function test_folio_repetido_en_el_borrador_lanza_folio_repetido(): void
    {
        [, $grupo, $uuids] = $this->escenario();

        $this->expectExceptionObject(MultienvioNacionalException::folioRepetido('F-1'));

        $this->guardar($grupo, [$this->envio($uuids[0], 'F-1', 40), $this->envio($uuids[1], ' F-1 ', 60)]);
    }

    public function test_folio_de_otro_pedido_activo_lanza_folio_repetido(): void
    {
        [, $grupo, $uuids] = $this->escenario();
        $this->pedidoNacional(['folio_remision' => 'OCUPADO-9']);

        $this->expectExceptionObject(MultienvioNacionalException::folioRepetido('OCUPADO-9'));

        $this->guardar($grupo, [$this->envio($uuids[0], 'OCUPADO-9', 40)]);
    }

    public function test_folio_del_origen_se_acepta_solo_si_viene_explicito(): void
    {
        [$pedido, $grupo, $uuids] = $this->escenario();

        $r = $this->guardar($grupo, [
            $this->envio($uuids[0], $pedido->folio_remision, 40),
            $this->envio($uuids[1], null, 60),
        ]);

        $this->assertSame($pedido->folio_remision, $r->borrador_json[0]['folio_remision']);
        $this->assertNull($r->borrador_json[1]['folio_remision']);
    }

    public function test_estado_distinto_de_configurando_lanza_no_elegible(): void
    {
        [, $grupo, $uuids] = $this->escenario();
        $grupo->update(['estado' => MultienvioNacional::ESTADO_ESTANDAR_SIN_SEGURO]);

        $this->expectException(MultienvioNacionalException::class);
        $this->expectExceptionMessage('no es elegible');

        $this->guardar($grupo, [$this->envio($uuids[0], null, 10)]);
    }

    public function test_usuario_ajeno_no_puede_guardar(): void
    {
        [, $grupo, $uuids] = $this->escenario();
        $ajeno = User::factory()->create();

        $this->expectException(AuthorizationException::class);

        app(GuardarBorradorMultienvio::class)->ejecutar($grupo, $ajeno->id, [$this->envio($uuids[0], null, 10)]);
    }

    public function test_monto_negativo_o_no_numerico_se_rechaza(): void
    {
        [, $grupo, $uuids] = $this->escenario();

        foreach ([-1, 'abc'] as $monto) {
            try {
                $this->guardar($grupo, [$this->envio($uuids[0], null, $monto)]);
                $this->fail('Debió rechazar el monto.');
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString('total_mercancia', $e->getMessage());
            }
        }
        $this->assertNull($grupo->fresh()->borrador_json);
    }

    public function test_montos_se_redondean_a_dos_decimales(): void
    {
        [, $grupo, $uuids] = $this->escenario();

        $r = $this->guardar($grupo, [$this->envio($uuids[0], null, '33.336'), $this->envio($uuids[1], null, 10.005)]);

        $this->assertSame(33.34, $r->borrador_json[0]['total_mercancia']);
        $this->assertSame(10.01, $r->borrador_json[1]['total_mercancia']);
    }

    /** @param  array<int, array<string, mixed>>  $envios */
    private function guardar(MultienvioNacional $grupo, array $envios): MultienvioNacional
    {
        return app(GuardarBorradorMultienvio::class)->ejecutar($grupo, $this->ventas->id, $envios);
    }

    /** @return array<string, mixed> */
    private function envio(string $uuid, ?string $folio, float|int|string $total, ?string $contenido = null): array
    {
        return ['uuid_operativo' => $uuid, 'folio_remision' => $folio, 'total_mercancia' => $total, 'contenido' => $contenido];
    }

    /** @return array{0: PedidoBma, 1: MultienvioNacional, 2: array<int, string>} */
    private function escenario(): array
    {
        $pedido = $this->pedidoNacional();
        $uuids = [];
        foreach ([10, 12, 14] as $peso) {
            $uuids[] = $this->caja($pedido, $peso)->uuid_operativo;
        }
        $grupo = app(RegistrarDecisionMultienvio::class)->ejecutar($pedido, $this->ventas->id, 'activar');

        return [$pedido, $grupo, $uuids];
    }

    private function caja(PedidoBma $pedido, float $peso): PedidoBmaCaja
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
            'estado_operativo' => PedidoBmaCaja::ESTADO_ACTIVA,
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
