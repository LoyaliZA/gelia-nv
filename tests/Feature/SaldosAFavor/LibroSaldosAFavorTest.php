<?php

namespace Tests\Feature\SaldosAFavor;

use App\Models\CatalogoListaDescuento;
use App\Models\Cliente;
use App\Models\SaldosAFavor\SafCredito;
use App\Models\SaldosAFavor\SafMotivo;
use App\Models\SaldosAFavor\SafMovimiento;
use App\Models\User;
use App\Services\SaldosAFavor\AplicarReservaSafService;
use App\Services\SaldosAFavor\CancelarCreditoSafService;
use App\Services\SaldosAFavor\ConsultarCuentaClienteSafService;
use App\Services\SaldosAFavor\GenerarCreditoSafService;
use App\Services\SaldosAFavor\LiberarReservaSafService;
use App\Services\SaldosAFavor\ReservarSaldoSafService;
use App\Services\SaldosAFavor\RevisarCreditoSafService;
use App\Services\SaldosAFavor\VencerCreditosSafService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LibroSaldosAFavorTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();
        $lista = CatalogoListaDescuento::firstOrCreate(
            ['nombre' => 'PUBLICO GENERAL'],
            ['monto_requerido' => 0, 'activo' => true]
        );
        $this->user = User::factory()->create();
        $this->cliente = Cliente::create([
            'numero_cliente' => '90001',
            'nombre' => 'Cliente SAF',
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

    public function test_generar_credito_disponible_pendiente_revision_con_vigencia_365(): void
    {
        $credito = $this->generar(1000);

        $this->assertSame(SafCredito::ESTADO_DISPONIBLE, $credito->estado_financiero);
        $this->assertSame(SafCredito::REVISION_PENDIENTE, $credito->estado_revision);
        $this->assertEquals(1000.0, (float) $credito->monto_disponible);
        $this->assertTrue(
            $credito->fecha_generacion->copy()->addDays(365)->isSameDay($credito->fecha_vencimiento)
        );
        $this->assertDatabaseHas('saf_movimientos', [
            'saf_credito_id' => $credito->id,
            'tipo' => SafMovimiento::TIPO_GENERACION,
        ]);
    }

    public function test_usar_antes_de_revisar_administrativa(): void
    {
        $credito = $this->generar(500);
        $reservas = app(ReservarSaldoSafService::class)->handle(
            $this->cliente->id,
            200,
            $this->user->id,
            [['saf_credito_id' => $credito->id, 'monto' => 200]]
        );
        app(AplicarReservaSafService::class)->handle($credito->id, 200, $this->user->id);

        $credito->refresh();
        $this->assertEquals(300.0, (float) $credito->monto_disponible);
        $this->assertEquals(200.0, (float) $credito->monto_aplicado);
        $this->assertSame(SafCredito::ESTADO_PARCIAL, $credito->estado_financiero);
        $this->assertSame(SafCredito::REVISION_PENDIENTE, $credito->estado_revision);
        $this->assertCount(1, $reservas);
    }

    public function test_aplicacion_parcial_conserva_remanente_en_mismo_credito(): void
    {
        $credito = $this->generar(1282.51);
        app(ReservarSaldoSafService::class)->handle($this->cliente->id, 647.48, $this->user->id, [
            ['saf_credito_id' => $credito->id, 'monto' => 647.48],
        ]);
        app(AplicarReservaSafService::class)->handle($credito->id, 647.48, $this->user->id);

        $credito->refresh();
        $this->assertEquals(635.03, (float) $credito->monto_disponible);
        $this->assertSame(1, SafCredito::where('cliente_id', $this->cliente->id)->count());
    }

    public function test_varios_creditos_en_una_operacion_fifo_por_vencimiento(): void
    {
        $c1 = $this->generar(300, ['fecha_generacion' => now()->subDays(10)]);
        $c2 = $this->generar(500, ['fecha_generacion' => now()]);

        $reservas = app(ReservarSaldoSafService::class)->handle($this->cliente->id, 450, $this->user->id);
        $this->assertCount(2, $reservas);
        $this->assertSame($c1->id, $reservas[0]['credito']->id);
        $this->assertEquals(300.0, $reservas[0]['monto']);
        $this->assertSame($c2->id, $reservas[1]['credito']->id);
        $this->assertEquals(150.0, $reservas[1]['monto']);
    }

    public function test_un_credito_en_varias_operaciones(): void
    {
        $credito = $this->generar(1000);
        foreach ([200, 300, 100] as $monto) {
            app(ReservarSaldoSafService::class)->handle($this->cliente->id, $monto, $this->user->id, [
                ['saf_credito_id' => $credito->id, 'monto' => $monto],
            ]);
            app(AplicarReservaSafService::class)->handle($credito->id, $monto, $this->user->id);
        }
        $credito->refresh();
        $this->assertEquals(400.0, (float) $credito->monto_disponible);
        $this->assertEquals(600.0, (float) $credito->monto_aplicado);
    }

    public function test_liberar_reserva_por_cancelacion(): void
    {
        $credito = $this->generar(800);
        app(ReservarSaldoSafService::class)->handle($this->cliente->id, 250, $this->user->id, [
            ['saf_credito_id' => $credito->id, 'monto' => 250],
        ]);
        app(LiberarReservaSafService::class)->handle($credito->id, 250, $this->user->id);

        $credito->refresh();
        $this->assertEquals(0.0, (float) $credito->monto_reservado);
        $this->assertEquals(800.0, (float) $credito->monto_disponible);
    }

    public function test_revisar_no_modifica_disponible(): void
    {
        $credito = $this->generar(100);
        app(RevisarCreditoSafService::class)->handle(
            $credito->id,
            SafCredito::REVISION_REVISADO,
            $this->user->id,
            'OK'
        );
        $credito->refresh();
        $this->assertEquals(100.0, (float) $credito->monto_disponible);
        $this->assertSame(SafCredito::REVISION_REVISADO, $credito->estado_revision);
    }

    public function test_vencer_creditos_por_fecha(): void
    {
        $credito = $this->generar(200, ['fecha_generacion' => now()->subDays(400)]);
        $this->assertTrue($credito->fecha_vencimiento->lt(now()->startOfDay()));

        $n = app(VencerCreditosSafService::class)->handle();
        $this->assertSame(1, $n);
        $credito->refresh();
        $this->assertSame(SafCredito::ESTADO_VENCIDO, $credito->estado_financiero);
        $this->assertEquals(0.0, app(ConsultarCuentaClienteSafService::class)->handle($this->cliente->id)['disponible']);
    }

    public function test_cancelar_credito_sin_borrar_historial(): void
    {
        $credito = $this->generar(50);
        app(CancelarCreditoSafService::class)->handle($credito->id, $this->user->id, 'Duplicado');
        $credito->refresh();
        $this->assertSame(SafCredito::ESTADO_CANCELADO, $credito->estado_financiero);
        $this->assertTrue(
            SafMovimiento::where('saf_credito_id', $credito->id)->where('tipo', SafMovimiento::TIPO_CANCELACION)->exists()
        );
    }

    public function test_impedir_reserva_mayor_al_disponible(): void
    {
        $this->generar(100);
        $this->expectException(\InvalidArgumentException::class);
        app(ReservarSaldoSafService::class)->handle($this->cliente->id, 150, $this->user->id);
    }
}
