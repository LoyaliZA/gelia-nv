<?php

namespace Tests\Unit\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\CatalogoPaqueteriaPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaHistorialEstado;
use App\Models\User;
use App\Notifications\AlertaPedidoBma;
use App\Services\ControlPedidos\EvaluarRetrasosPedidoBmaService;
use App\Services\ControlPedidos\PlazosRetrasoPedidoBmaConfig;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ControlPedidosEvaluarRetrasosTest extends TestCase
{
    use RefreshDatabase;

    private User $vendedora;

    private User $gerente;

    private User $cedis;

    private CatalogoEstatusPedido $enCedis;

    private CatalogoEstatusPedido $pendienteEnvio;

    private CatalogoEstatusPedido $enviado;

    private int $paqueteriaComercialId;

    private int $paqueteriaLocalId;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['control_pedidos.cedis', 'control_pedidos.delegado'] as $perm) {
            Permission::findOrCreate($perm, 'web');
        }

        $this->vendedora = User::factory()->create(['name' => 'Vendedora SLA']);
        $this->gerente = User::factory()->create(['name' => 'Gerente SLA']);
        $this->cedis = User::factory()->create(['name' => 'CEDIS SLA']);
        $this->cedis->givePermissionTo('control_pedidos.cedis');
        $this->vendedora->gerentes()->attach($this->gerente->id);

        $this->enCedis = CatalogoEstatusPedido::create([
            'codigo_interno' => 'AMARILLO_SLA',
            'nombre_visual' => 'En CEDIS',
            'color_hex' => '#EAB308',
            'fase_ciclo' => CatalogoEstatusPedido::FASE_EN_CEDIS,
            'orden' => 10,
            'activo' => true,
        ]);
        $this->pendienteEnvio = CatalogoEstatusPedido::create([
            'codigo_interno' => 'PEND_ENV_SLA',
            'nombre_visual' => 'Pendiente envío',
            'color_hex' => '#0EA5E9',
            'fase_ciclo' => CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO,
            'orden' => 11,
            'activo' => true,
        ]);
        $this->enviado = CatalogoEstatusPedido::create([
            'codigo_interno' => 'ENVIADO_SLA',
            'nombre_visual' => 'Enviado',
            'color_hex' => '#22C55E',
            'fase_ciclo' => CatalogoEstatusPedido::FASE_ENVIADO,
            'orden' => 12,
            'activo' => true,
        ]);

        $this->paqueteriaComercialId = (int) CatalogoPaqueteriaPedido::create([
            'nombre' => 'FEDEX SLA',
            'categoria' => CatalogoPaqueteriaPedido::CATEGORIA_COMERCIAL,
            'activo' => true,
        ])->id;

        $this->paqueteriaLocalId = (int) CatalogoPaqueteriaPedido::create([
            'nombre' => 'Taxi SLA',
            'categoria' => CatalogoPaqueteriaPedido::CATEGORIA_LOCAL_REGIONAL,
            'activo' => true,
        ])->id;

        app(PlazosRetrasoPedidoBmaConfig::class)->guardar([
            'activo' => true,
            'hora_corte' => '18:00',
            'dias_habiles' => [1, 2, 3, 4, 5, 6],
            'temporada_alta' => false,
            'dias_extra_temporada_alta' => 1,
            'comercial' => ['dias_empaque' => 1, 'dias_recoleccion' => 1],
            'local_regional' => ['dias_empaque' => 1, 'dias_recoleccion' => 1],
        ]);
    }

    public function test_dispara_retraso_empaque_y_no_recoleccion_si_no_empacado(): void
    {
        Notification::fake();

        // Pago lunes 10:00 → deadline martes 18:00. Evaluamos miércoles.
        $pedido = $this->crearPedido([
            'catalogo_estatus_pedido_id' => $this->enCedis->id,
            'catalogo_paqueteria_id' => $this->paqueteriaComercialId,
            'pago_validado_at' => Carbon::parse('2026-08-10 10:00:00'),
            'empacado_at' => null,
        ]);

        $resultado = app(EvaluarRetrasosPedidoBmaService::class)->ejecutar(
            Carbon::parse('2026-08-12 12:00:00')
        );

        $this->assertSame(1, $resultado['empaque']);
        $this->assertSame(0, $resultado['recoleccion']);

        $pedido->refresh();
        $this->assertNotNull($pedido->retraso_empaque_alertado_at);
        $this->assertNull($pedido->retraso_recoleccion_alertado_at);

        $this->assertDatabaseHas('pedido_bma_historial_estados', [
            'pedido_bma_id' => $pedido->id,
            'accion' => AccionesHistorialPedidoBma::RETRASO_EMPAQUE,
            'usuario_id' => null,
        ]);

        Notification::assertSentTo($this->vendedora, AlertaPedidoBma::class, function (AlertaPedidoBma $n) {
            return $n->tipoAlerta === 'pedido_retraso_empaque';
        });
        Notification::assertSentTo($this->gerente, AlertaPedidoBma::class);
        Notification::assertSentTo($this->cedis, AlertaPedidoBma::class);
    }

    public function test_dispara_retraso_recoleccion_distinto_cuando_listo_para_envio(): void
    {
        Notification::fake();

        $pedido = $this->crearPedido([
            'catalogo_estatus_pedido_id' => $this->pendienteEnvio->id,
            'catalogo_paqueteria_id' => $this->paqueteriaLocalId,
            'pago_validado_at' => Carbon::parse('2026-08-10 10:00:00'),
            'empacado_at' => Carbon::parse('2026-08-10 12:00:00'),
        ]);

        $resultado = app(EvaluarRetrasosPedidoBmaService::class)->ejecutar(
            Carbon::parse('2026-08-12 12:00:00')
        );

        $this->assertSame(0, $resultado['empaque']);
        $this->assertSame(1, $resultado['recoleccion']);

        $pedido->refresh();
        $this->assertNull($pedido->retraso_empaque_alertado_at);
        $this->assertNotNull($pedido->retraso_recoleccion_alertado_at);

        $this->assertDatabaseHas('pedido_bma_historial_estados', [
            'pedido_bma_id' => $pedido->id,
            'accion' => AccionesHistorialPedidoBma::RETRASO_RECOLECCION,
        ]);

        Notification::assertSentTo($this->vendedora, AlertaPedidoBma::class, function (AlertaPedidoBma $n) {
            return $n->tipoAlerta === 'pedido_retraso_recoleccion';
        });
    }

    public function test_no_duplica_alerta_empaque(): void
    {
        Notification::fake();

        $pedido = $this->crearPedido([
            'catalogo_estatus_pedido_id' => $this->enCedis->id,
            'catalogo_paqueteria_id' => $this->paqueteriaComercialId,
            'pago_validado_at' => Carbon::parse('2026-08-10 10:00:00'),
            'retraso_empaque_alertado_at' => Carbon::parse('2026-08-11 19:00:00'),
        ]);

        $resultado = app(EvaluarRetrasosPedidoBmaService::class)->ejecutar(
            Carbon::parse('2026-08-13 12:00:00')
        );

        $this->assertSame(0, $resultado['empaque']);
        Notification::assertNothingSent();
        $this->assertSame(
            0,
            PedidoBmaHistorialEstado::where('pedido_bma_id', $pedido->id)
                ->where('accion', AccionesHistorialPedidoBma::RETRASO_EMPAQUE)
                ->count()
        );
    }

    public function test_incidencia_sin_empacar_dispara_retraso_empaque(): void
    {
        Notification::fake();

        $incidencia = CatalogoEstatusPedido::create([
            'codigo_interno' => 'ROJO_SLA',
            'nombre_visual' => 'Incidencia',
            'color_hex' => '#EF4444',
            'fase_ciclo' => CatalogoEstatusPedido::FASE_INCIDENCIA_CEDIS,
            'orden' => 13,
            'activo' => true,
        ]);

        $pedido = $this->crearPedido([
            'catalogo_estatus_pedido_id' => $incidencia->id,
            'catalogo_paqueteria_id' => $this->paqueteriaComercialId,
            'pago_validado_at' => Carbon::parse('2026-08-10 10:00:00'),
        ]);

        $resultado = app(EvaluarRetrasosPedidoBmaService::class)->ejecutar(
            Carbon::parse('2026-08-13 12:00:00')
        );

        $this->assertSame(1, $resultado['empaque']);
        $this->assertSame(0, $resultado['recoleccion']);
        $this->assertNotNull($pedido->fresh()->retraso_empaque_alertado_at);
        Notification::assertSentTo($this->vendedora, AlertaPedidoBma::class, function (AlertaPedidoBma $n) {
            return $n->tipoAlerta === 'pedido_retraso_empaque';
        });
    }

    public function test_mensajes_voz_tipos_retraso_distintos(): void
    {
        $pedido = new PedidoBma([
            'id' => 7,
            'folio' => 'PED-7',
            'folio_remision' => 'REM-7',
        ]);
        $pedido->setRelation('vendedor', null);
        $pedido->setRelation('cliente', null);
        $pedido->setRelation('estatus', null);
        $user = new User(['name' => 'Ana']);

        $empaque = (new AlertaPedidoBma($pedido, 'pedido_retraso_empaque', 'base'))->toBroadcast($user)->data;
        $reco = (new AlertaPedidoBma($pedido, 'pedido_retraso_recoleccion', 'base'))->toBroadcast($user)->data;

        $this->assertStringContainsString('retraso de empaque', $empaque['mensaje_voz']);
        $this->assertStringContainsString('retraso de recolección', $reco['mensaje_voz']);
        $this->assertNotSame($empaque['titulo'], $reco['titulo']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function crearPedido(array $overrides): PedidoBma
    {
        return PedidoBma::create(array_merge([
            'folio' => 'PED-SLA-'.uniqid(),
            'folio_remision' => 'REM-SLA-'.uniqid(),
            'fecha' => now()->toDateString(),
            'vendedor_id' => $this->vendedora->id,
            'total_mercancia' => 100,
            'costo_envio' => 0,
            'es_resguardo' => false,
        ], $overrides));
    }
}
