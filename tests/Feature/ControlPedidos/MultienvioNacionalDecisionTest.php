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
use App\Services\ControlPedidos\Nacional\Multienvio\MultienvioNacionalException;
use App\Services\ControlPedidos\Nacional\Multienvio\RegistrarDecisionMultienvio;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MultienvioNacionalDecisionTest extends TestCase
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

    public function test_sin_seguro_persiste_y_no_crea_pedidos(): void
    {
        $pedido = $this->pedidoTresCajas();
        $doc = $pedido->documentos()->create(['tipo' => 'pdf_pedido', 'ruta_archivo' => 'x/a.pdf', 'nombre_original' => 'a.pdf', 'activo' => true]);
        $pedido->documentos()->create(['tipo' => 'pdf_pedido', 'ruta_archivo' => 'x/b.pdf', 'nombre_original' => 'b.pdf', 'activo' => false]);
        $antes = PedidoBma::query()->count();

        $grupo = $this->registrar($pedido, 'sin_seguro');

        $this->assertSame(MultienvioNacional::ESTADO_ESTANDAR_SIN_SEGURO, $grupo->estado);
        $this->assertSame($antes, PedidoBma::query()->count());
        $snap = $grupo->snapshot_origen_json;
        $this->assertSame($pedido->folio, $snap['folio']);
        $this->assertSame($pedido->folio_remision, $snap['folio_remision']);
        $this->assertEquals(100, $snap['total_mercancia']);
        $this->assertSame([$doc->id], $snap['documentos_ids']);
    }

    public function test_activar_deja_configurando_y_guarda_actor_y_revision(): void
    {
        $pedido = $this->pedidoTresCajas();
        $revision = app(EvaluarMultienvioNacional::class)->ejecutar($pedido)['revision'];

        $grupo = $this->registrar($pedido, 'activar', $revision);

        $this->assertSame(MultienvioNacional::ESTADO_CONFIGURANDO, $grupo->estado);
        $this->assertSame($this->ventas->id, (int) $grupo->decidido_por_id);
        $this->assertNotNull($grupo->decidido_at);
        $this->assertSame($revision, $grupo->revision_consulta);
    }

    public function test_repetir_la_misma_decision_devuelve_el_mismo_grupo(): void
    {
        $pedido = $this->pedidoTresCajas();

        $a = $this->registrar($pedido, 'sin_seguro');
        $b = $this->registrar($pedido, 'sin_seguro');

        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, MultienvioNacional::query()->count());
    }

    public function test_activar_despues_de_sin_seguro_reutiliza_el_grupo(): void
    {
        $pedido = $this->pedidoTresCajas();

        $a = $this->registrar($pedido, 'sin_seguro');
        $b = $this->registrar($pedido, 'activar');

        $this->assertSame($a->id, $b->id);
        $this->assertSame(MultienvioNacional::ESTADO_CONFIGURANDO, $b->estado);
    }

    public function test_no_retrocede_desde_activo(): void
    {
        $pedido = $this->pedidoTresCajas();
        $a = $this->registrar($pedido, 'activar');
        $a->update(['estado' => MultienvioNacional::ESTADO_ACTIVO]);

        $b = $this->registrar($pedido, 'sin_seguro');

        $this->assertSame(MultienvioNacional::ESTADO_ACTIVO, $b->estado);
    }

    public function test_revision_obsoleta_lanza_excepcion(): void
    {
        $pedido = $this->pedidoTresCajas();

        $this->expectExceptionObject(MultienvioNacionalException::revisionObsoleta());

        $this->registrar($pedido, 'activar', 'obsoleta');
    }

    public function test_usuario_ajeno_no_puede(): void
    {
        $pedido = $this->pedidoTresCajas();
        $ajeno = User::factory()->create();

        $this->expectException(AuthorizationException::class);

        app(RegistrarDecisionMultienvio::class)->ejecutar($pedido, $ajeno->id, 'activar');
    }

    public function test_una_caja_no_elegible_lanza_no_elegible(): void
    {
        $pedido = $this->pedidoNacional();
        $this->caja($pedido, 10);

        try {
            $this->registrar($pedido, 'activar');
            $this->fail('Debió lanzar noElegible.');
        } catch (MultienvioNacionalException $e) {
            $this->assertStringContainsString('no es elegible', $e->getMessage());
            $this->assertStringNotContainsString('cambió', $e->getMessage());
        }
    }

    public function test_configurando_a_sin_seguro_funciona(): void
    {
        $pedido = $this->pedidoTresCajas();
        $a = $this->registrar($pedido, 'activar');

        $b = $this->registrar($pedido, 'sin_seguro');

        $this->assertSame($a->id, $b->id);
        $this->assertSame(MultienvioNacional::ESTADO_ESTANDAR_SIN_SEGURO, $b->estado);
    }

    public function test_el_snapshot_no_se_sobrescribe_en_decisiones_posteriores(): void
    {
        $pedido = $this->pedidoTresCajas();
        $a = $this->registrar($pedido, 'sin_seguro');
        $original = $a->snapshot_origen_json;
        $pedido->update(['folio' => 'CAMBIADO']);
        $pedido->documentos()->create(['tipo' => 'pdf_pedido', 'ruta_archivo' => 'x/c.pdf', 'nombre_original' => 'c.pdf', 'activo' => true]);

        $b = $this->registrar($pedido->fresh(), 'activar');

        $this->assertSame($original, $b->snapshot_origen_json);
    }

    public function test_estados_terminales_se_devuelven_sin_cambios_aun_con_revision_obsoleta_y_una_caja(): void
    {
        foreach ([MultienvioNacional::ESTADO_ACTIVO, MultienvioNacional::ESTADO_REGULARIZACION_PENDIENTE] as $estado) {
            $pedido = $this->pedidoTresCajas();
            $grupo = $this->registrar($pedido, 'activar');
            $grupo->update(['estado' => $estado]);
            $pedido->cajas()->orderBy('id')->take(2)->update(['estado_operativo' => PedidoBmaCaja::ESTADO_RETIRADA]);

            foreach (['activar', 'sin_seguro'] as $decision) {
                $r = $this->registrar($pedido->fresh(), $decision, 'obsoleta');

                $this->assertSame($grupo->id, $r->id);
                $this->assertSame($estado, $r->estado);
            }
        }
        $this->assertSame(2, MultienvioNacional::query()->count());
    }

    public function test_un_miembro_con_dos_cajas_no_puede_activar_un_grupo_nuevo(): void
    {
        $origen = $this->pedidoTresCajas();
        $grupoA = $this->registrar($origen, 'activar');
        $grupoA->update(['estado' => MultienvioNacional::ESTADO_ACTIVO]);
        $miembro = $this->pedidoNacional();
        $miembro->forceFill(['multienvio_nacional_id' => $grupoA->id, 'multienvio_orden' => 2])->save();
        $this->caja($miembro, 10);
        $this->caja($miembro, 12);

        try {
            $this->registrar($miembro->fresh(), 'activar');
            $this->fail('Debió lanzar noElegible.');
        } catch (MultienvioNacionalException $e) {
            $this->assertStringContainsString('ya es un envío de un multienvío', $e->getMessage());
        }
        $this->assertSame(1, MultienvioNacional::query()->count());
        $this->assertSame($grupoA->id, (int) $miembro->fresh()->multienvio_nacional_id);
    }

    private function registrar(PedidoBma $pedido, string $decision, ?string $revision = null): MultienvioNacional
    {
        return app(RegistrarDecisionMultienvio::class)->ejecutar($pedido, $this->ventas->id, $decision, $revision);
    }

    private function pedidoTresCajas(): PedidoBma
    {
        $pedido = $this->pedidoNacional();
        foreach ([10, 12, 14] as $peso) {
            $this->caja($pedido, $peso);
        }

        return $pedido;
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
