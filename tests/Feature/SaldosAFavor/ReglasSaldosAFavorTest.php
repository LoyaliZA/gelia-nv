<?php

namespace Tests\Feature\SaldosAFavor;

use App\Models\CatalogoListaDescuento;
use App\Models\Cliente;
use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\SaldosAFavor\SafCredito;
use App\Models\SaldosAFavor\SafIncidencia;
use App\Models\SaldosAFavor\SafMotivo;
use App\Models\User;
use App\Services\SaldosAFavor\GenerarCreditoSafService;
use App\Services\SaldosAFavor\RegistrarIncidenciaSafService;
use App\Services\SaldosAFavor\ReservarSaldoSafService;
use App\Services\SaldosAFavor\SincronizarAplicacionesPedidoSafService;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use App\Support\SaldosAFavor\ReglasSaf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Tests\TestCase;

class ReglasSaldosAFavorTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::forget('saldos_favor.reglas');
        ReglasSaf::guardar([
            'monto_minimo' => 10,
            'vigencia_modo' => 'dias',
            'vigencia_dias' => 20,
            'fecha_limite' => null,
        ]);

        $lista = CatalogoListaDescuento::firstOrCreate(
            ['nombre' => 'PUBLICO GENERAL'],
            ['monto_requerido' => 0, 'activo' => true]
        );
        $this->user = User::factory()->create();
        $this->cliente = Cliente::create([
            'numero_cliente' => '90099',
            'nombre' => 'Cliente Reglas SAF',
            'lista_actual_id' => $lista->id,
            'monto_venta_actual' => 0,
        ]);
    }

    private function motivo(): SafMotivo
    {
        return SafMotivo::firstOrCreate(
            ['codigo' => 'pago_de_mas'],
            [
                'nombre' => 'Cliente depositó de más',
                'categoria' => 'diferencias_pago',
                'requiere_detalle' => false,
                'activo' => true,
                'orden' => 1,
            ]
        );
    }

    private function generar(float $monto, array $extra = []): SafCredito
    {
        return app(GenerarCreditoSafService::class)->handle(array_merge([
            'cliente_id' => $this->cliente->id,
            'monto' => $monto,
            'saf_motivo_id' => $this->motivo()->id,
            'canal_origen' => 'bellaroma',
            'generado_por_id' => $this->user->id,
        ], $extra));
    }

    public function test_rechaza_monto_menor_al_minimo_configurado(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->generar(9.99);
    }

    public function test_vigencia_por_fecha_limite(): void
    {
        ReglasSaf::guardar([
            'monto_minimo' => 10,
            'vigencia_modo' => 'fecha_limite',
            'vigencia_dias' => 20,
            'fecha_limite' => '2026-12-31',
        ]);

        $credito = $this->generar(50);
        $this->assertSame('2026-12-31', $credito->fecha_vencimiento->toDateString());
    }

    public function test_reserva_fifo_ignora_cherry_pick_y_marca_reservado(): void
    {
        $viejo = $this->generar(100, ['fecha_generacion' => now()->subDays(5)]);
        $nuevo = $this->generar(100, ['fecha_generacion' => now()]);

        // Intento cherry-pick del crédito nuevo: el servidor aplica FIFO al monto.
        $reservas = app(ReservarSaldoSafService::class)->handle(
            $this->cliente->id,
            80,
            $this->user->id,
            [['saf_credito_id' => $nuevo->id, 'monto' => 80]]
        );

        $this->assertCount(1, $reservas);
        $this->assertSame($viejo->id, $reservas[0]['credito']->id);

        $viejo->refresh();
        $this->assertEquals(20.0, (float) $viejo->monto_disponible);
        $this->assertSame(SafCredito::ESTADO_DISPONIBLE, $viejo->estado_financiero);

        app(ReservarSaldoSafService::class)->handle($this->cliente->id, 20, $this->user->id);
        $viejo->refresh();
        $this->assertSame(SafCredito::ESTADO_RESERVADO, $viejo->estado_financiero);
        $this->assertEquals(0.0, (float) $viejo->monto_disponible);
    }

    public function test_incidencia_saf_registra_bitacora_sin_cambiar_fase(): void
    {
        $estatus = CatalogoEstatusPedido::firstOrCreate(
            ['codigo_interno' => 'PENDIENTE_AUXILIAR'],
            [
                'nombre_visual' => 'Pendiente auxiliar',
                'color_hex' => '#f59e0b',
                'fase_ciclo' => CatalogoEstatusPedido::FASE_PENDIENTE_AUXILIAR,
                'orden' => 10,
                'activo' => true,
            ]
        );

        $pedido = PedidoBma::create([
            'folio' => 'BMA-SAF-TEST-1',
            'cliente_id' => $this->cliente->id,
            'vendedor_id' => $this->user->id,
            'catalogo_estatus_pedido_id' => $estatus->id,
            'fecha' => now()->toDateString(),
            'total_mercancia' => 100,
            'costo_envio' => 0,
            'aplica_seguro' => false,
            'costo_seguro' => 0,
            'saldo_a_favor' => 0,
            'total_a_cobrar' => 100,
        ]);

        $pedido->load('estatus');
        $faseAntes = $pedido->estatus?->fase_ciclo;

        $inc = app(RegistrarIncidenciaSafService::class)->handle(
            'descuadre_prueba',
            'Descuadre de saldo a favor de prueba',
            $this->cliente->id,
            $pedido->id,
            null,
            $this->user->id
        );

        $this->assertSame(SafIncidencia::ESTADO_ABIERTA, $inc->estado);
        $pedido->refresh()->load('estatus');
        $this->assertSame($faseAntes, $pedido->estatus?->fase_ciclo);
        $this->assertDatabaseHas('pedido_bma_historial_estados', [
            'pedido_bma_id' => $pedido->id,
            'accion' => AccionesHistorialPedidoBma::INCIDENCIA_SAF,
        ]);
    }

    public function test_no_aplica_credito_del_mismo_pedido(): void
    {
        $pedido = PedidoBma::create([
            'folio' => 'BMA-SAF-NEXT-1',
            'cliente_id' => $this->cliente->id,
            'vendedor_id' => $this->user->id,
            'catalogo_estatus_pedido_id' => CatalogoEstatusPedido::firstOrCreate(
                ['codigo_interno' => 'BORRADOR'],
                [
                    'nombre_visual' => 'Borrador',
                    'color_hex' => '#94a3b8',
                    'fase_ciclo' => CatalogoEstatusPedido::FASE_BORRADOR,
                    'orden' => 1,
                    'activo' => true,
                ]
            )->id,
            'fecha' => now()->toDateString(),
            'total_mercancia' => 500,
            'costo_envio' => 0,
            'aplica_seguro' => false,
            'costo_seguro' => 0,
            'saldo_a_favor' => 0,
            'total_a_cobrar' => 500,
        ]);

        $origen = $this->generar(80, ['pedido_bma_id' => $pedido->id]);
        $otro = $this->generar(50);

        $total = app(SincronizarAplicacionesPedidoSafService::class)->reservarParaPedido(
            $pedido,
            [['monto' => 50]],
            $this->user->id
        );

        $this->assertEquals(50.0, $total);
        $this->assertDatabaseHas('saf_pedido_aplicaciones', [
            'pedido_bma_id' => $pedido->id,
            'saf_credito_id' => $otro->id,
        ]);
        $this->assertDatabaseMissing('saf_pedido_aplicaciones', [
            'pedido_bma_id' => $pedido->id,
            'saf_credito_id' => $origen->id,
        ]);
    }

    public function test_no_mezcla_canal_ni_sucursal_al_reservar(): void
    {
        $pdv = $this->generar(50, ['canal_origen' => 'punto_venta', 'sucursal' => 'Norte']);
        $mismo = $this->generar(40, ['canal_origen' => 'bellaroma']);

        $pedido = PedidoBma::create([
            'folio' => 'BMA-SAF-ISO-1',
            'cliente_id' => $this->cliente->id,
            'vendedor_id' => $this->user->id,
            'catalogo_estatus_pedido_id' => CatalogoEstatusPedido::firstOrCreate(
                ['codigo_interno' => 'BORRADOR'],
                [
                    'nombre_visual' => 'Borrador',
                    'color_hex' => '#94a3b8',
                    'fase_ciclo' => CatalogoEstatusPedido::FASE_BORRADOR,
                    'orden' => 1,
                    'activo' => true,
                ]
            )->id,
            'fecha' => now()->toDateString(),
            'total_mercancia' => 500,
            'costo_envio' => 0,
            'aplica_seguro' => false,
            'costo_seguro' => 0,
            'saldo_a_favor' => 0,
            'total_a_cobrar' => 500,
        ]);

        $total = app(SincronizarAplicacionesPedidoSafService::class)->reservarParaPedido(
            $pedido,
            [['monto' => 40]],
            $this->user->id
        );
        $this->assertEquals(40.0, $total);
        $this->assertDatabaseHas('saf_pedido_aplicaciones', [
            'pedido_bma_id' => $pedido->id,
            'saf_credito_id' => $mismo->id,
        ]);
        $this->assertDatabaseMissing('saf_pedido_aplicaciones', [
            'pedido_bma_id' => $pedido->id,
            'saf_credito_id' => $pdv->id,
        ]);
    }
}
