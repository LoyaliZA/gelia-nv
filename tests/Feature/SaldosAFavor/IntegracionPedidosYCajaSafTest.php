<?php

namespace Tests\Feature\SaldosAFavor;

use App\Models\CatalogoListaDescuento;
use App\Models\Cliente;
use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\SaldosAFavor\SafComprobanteCaja;
use App\Models\SaldosAFavor\SafCredito;
use App\Models\SaldosAFavor\SafMotivo;
use App\Models\SaldosAFavor\SafPedidoAplicacion;
use App\Models\User;
use App\Services\SaldosAFavor\AplicarReservaSafService;
use App\Services\SaldosAFavor\ConsultarCuentaClienteSafService;
use App\Services\SaldosAFavor\GenerarCreditoSafService;
use App\Services\SaldosAFavor\RegistrarPagoPedidoBmaService;
use App\Services\SaldosAFavor\ReservarSaldoSafService;
use App\Services\SaldosAFavor\SincronizarAplicacionesPedidoSafService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class IntegracionPedidosYCajaSafTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $lista = CatalogoListaDescuento::firstOrCreate(
            ['nombre' => 'PUBLICO GENERAL'],
            ['monto_requerido' => 0, 'activo' => true]
        );
        $this->user = User::factory()->create();
        $this->cliente = Cliente::create([
            'numero_cliente' => '90002',
            'nombre' => 'Cliente Pedidos SAF',
            'lista_actual_id' => $lista->id,
            'monto_venta_actual' => 0,
        ]);
        SafMotivo::firstOrCreate(
            ['codigo' => 'pago_de_mas'],
            ['nombre' => 'De más', 'categoria' => 'diferencias_pago', 'requiere_detalle' => false, 'activo' => true, 'orden' => 1]
        );
    }

    private function credito(float $monto): SafCredito
    {
        return app(GenerarCreditoSafService::class)->handle([
            'cliente_id' => $this->cliente->id,
            'monto' => $monto,
            'saf_motivo_id' => SafMotivo::where('codigo', 'pago_de_mas')->value('id'),
            'canal_origen' => 'bellaroma',
            'generado_por_id' => $this->user->id,
        ]);
    }

    private function pedidoStub(): PedidoBma
    {
        $estatus = CatalogoEstatusPedido::firstOrCreate(
            ['codigo_interno' => 'BORRADOR'],
            [
                'nombre_visual' => 'Borrador',
                'color_hex' => '#94a3b8',
                'fase_ciclo' => CatalogoEstatusPedido::FASE_BORRADOR,
                'orden' => 1,
                'activo' => true,
            ]
        );

        return PedidoBma::create([
            'folio' => 'BMA-TEST-'.uniqid(),
            'vendedor_id' => $this->user->id,
            'cliente_id' => $this->cliente->id,
            'catalogo_estatus_pedido_id' => $estatus->id,
            'total_mercancia' => 1000,
            'costo_envio' => 100,
            'aplica_seguro' => false,
            'costo_seguro' => 0,
            'saldo_a_favor' => 0,
            'total_a_cobrar' => 1100,
            'fecha' => now()->toDateString(),
        ]);
    }

    public function test_reservar_y_aplicar_en_pedido_deriva_saldo_a_favor(): void
    {
        $c = $this->credito(500);
        $pedido = $this->pedidoStub();
        $svc = app(SincronizarAplicacionesPedidoSafService::class);

        $total = $svc->reservarParaPedido($pedido, [
            ['saf_credito_id' => $c->id, 'monto' => 250],
        ], $this->user->id);

        $this->assertEquals(250.0, $total);
        $pedido->refresh();
        $this->assertEquals(250.0, (float) $pedido->saldo_a_favor);
        $this->assertDatabaseHas('saf_pedido_aplicaciones', [
            'pedido_bma_id' => $pedido->id,
            'estado' => SafPedidoAplicacion::ESTADO_RESERVADO,
        ]);

        $svc->aplicarReservasPedido($pedido, $this->user->id);
        $c->refresh();
        $this->assertEquals(250.0, (float) $c->monto_aplicado);
        $this->assertEquals(250.0, (float) $c->monto_disponible);
    }

    public function test_rechazo_libera_reserva_del_pedido(): void
    {
        $c = $this->credito(400);
        $pedido = $this->pedidoStub();
        $svc = app(SincronizarAplicacionesPedidoSafService::class);
        $svc->reservarParaPedido($pedido, [['saf_credito_id' => $c->id, 'monto' => 100]], $this->user->id);
        $svc->liberarReservasPendientes($pedido, $this->user->id);

        $c->refresh();
        $this->assertEquals(0.0, (float) $c->monto_reservado);
        $this->assertEquals(400.0, (float) $c->monto_disponible);
    }

    public function test_exhibiciones_multi_banco_y_excedente(): void
    {
        $pedido = $this->pedidoStub();
        $pagos = app(RegistrarPagoPedidoBmaService::class);
        $file = UploadedFile::fake()->image('pago.jpg');
        $pagos->handle($pedido, ['monto' => 700, 'forma_pago' => 'transferencia'], $file, $this->user->id);
        $pagos->handle($pedido, ['monto' => 500, 'forma_pago' => 'transferencia'], UploadedFile::fake()->image('pago2.jpg'), $this->user->id);

        $resumen = $pagos->resumenPago($pedido);
        $this->assertEquals(1200.0, $resumen['total_recibido']);
        $this->assertEquals(100.0, $resumen['excedente']);
        $this->assertSame('con_excedente', $resumen['cobertura']);
        $this->assertSame('sobrepagado', $resumen['estado_pago']);
        $this->assertSame('pendiente', $resumen['revision']);
    }

    public function test_caja_aplica_sin_duplicar_en_reimpresion(): void
    {
        $c = $this->credito(300);
        $antes = app(ConsultarCuentaClienteSafService::class)->handle($this->cliente->id)['disponible'];

        app(ReservarSaldoSafService::class)->handle($this->cliente->id, 150, $this->user->id, [
            ['saf_credito_id' => $c->id, 'monto' => 150],
        ]);
        app(AplicarReservaSafService::class)->handle($c->id, 150, $this->user->id);

        $despues = app(ConsultarCuentaClienteSafService::class)->handle($this->cliente->id)['disponible'];
        $this->assertEquals(150.0, round($antes - $despues, 2));

        $comp = SafComprobanteCaja::create([
            'folio' => 'SAF-PDV-000001',
            'cliente_id' => $this->cliente->id,
            'saf_cuenta_id' => $c->saf_cuenta_id,
            'saldo_anterior' => $antes,
            'monto_aplicado' => 150,
            'saldo_restante' => $despues,
            'creditos_detalle' => [['folio' => $c->folio, 'monto' => 150]],
            'estado' => SafComprobanteCaja::ESTADO_PENDIENTE_FIRMA,
            'generado_por_id' => $this->user->id,
            'aplicado_at' => now(),
        ]);

        $this->assertSame(1, SafComprobanteCaja::count());
        $this->assertSame('SAF-PDV-000001', $comp->folio);
        $c->refresh();
        $this->assertEquals(150.0, (float) $c->monto_aplicado);
    }

    public function test_uso_cruzado_origen_pdv_en_cuenta_unificada(): void
    {
        $pdv = app(GenerarCreditoSafService::class)->handle([
            'cliente_id' => $this->cliente->id,
            'monto' => 80,
            'canal_origen' => 'punto_venta',
            'generado_por_id' => $this->user->id,
            'saf_motivo_id' => SafMotivo::where('codigo', 'pago_de_mas')->value('id'),
        ]);
        $online = $this->credito(20);

        $cuenta = app(ConsultarCuentaClienteSafService::class)->handle($this->cliente->id);
        $this->assertEquals(100.0, $cuenta['disponible']);
        $this->assertTrue($cuenta['creditos_usables']->contains('id', $pdv->id));
        $this->assertTrue($cuenta['creditos_usables']->contains('id', $online->id));
    }

    public function test_importacion_csv_solo_filas_conciliadas(): void
    {
        SafMotivo::firstOrCreate(
            ['codigo' => 'migracion_historica'],
            ['nombre' => 'Migración', 'categoria' => 'sistema', 'requiere_detalle' => false, 'activo' => true, 'orden' => 900]
        );

        $csv = "numero_cliente,monto_original,monto_aplicado,fecha_generacion,documento_origen,remision_aplicacion,motivo\n"
            ."90002,100,40,2025-01-01,REM-1,REM-2,pago de mas\n"
            ."99999,50,0,2025-01-01,REM-X,,sin cliente\n";

        $file = UploadedFile::fake()->createWithContent('saf.csv', $csv);
        $controller = app(\App\Http\Controllers\SaldosAFavor\MigrarSaldosAFavorController::class);
        $ref = new \ReflectionClass($controller);
        $parse = $ref->getMethod('parseCsv');
        $parse->setAccessible(true);
        $clasificar = $ref->getMethod('clasificar');
        $clasificar->setAccessible(true);

        $filas = $parse->invoke($controller, $file->getRealPath());
        $resultado = $clasificar->invoke($controller, $filas, 'bellaroma');

        $this->assertSame(1, $resultado['resumen']['ok']);
        $this->assertSame(1, $resultado['resumen']['excepciones']);
    }
}
