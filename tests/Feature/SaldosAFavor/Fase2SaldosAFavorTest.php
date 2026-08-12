<?php

namespace Tests\Feature\SaldosAFavor;

use App\Models\CatalogoListaDescuento;
use App\Models\Cliente;
use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\SaldosAFavor\PedidoBmaPago;
use App\Models\SaldosAFavor\SafComprobanteCaja;
use App\Models\SaldosAFavor\SafCredito;
use App\Models\SaldosAFavor\SafIncidencia;
use App\Models\SaldosAFavor\SafMotivo;
use App\Models\SaldosAFavor\SafMovimiento;
use App\Models\SaldosAFavor\SafPedidoAplicacion;
use App\Models\User;
use App\Notifications\SaldoFavorProximoVencerNotification;
use App\Services\ControlPedidos\ValidarPagoPedidoBmaService;
use App\Services\SaldosAFavor\AplicarReservaSafService;
use App\Services\SaldosAFavor\GenerarCreditoSafService;
use App\Services\SaldosAFavor\ReactivarCreditoSafService;
use App\Services\SaldosAFavor\ReconciliarTotalPedidoSafService;
use App\Services\SaldosAFavor\ReservarSaldoSafService;
use App\Services\SaldosAFavor\RevertirAplicacionSafService;
use App\Services\SaldosAFavor\SincronizarAplicacionesPedidoSafService;
use App\Services\SaldosAFavor\VencerCreditosSafService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class Fase2SaldosAFavorTest extends TestCase
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
        foreach (['saldos_favor.ver', 'saldos_favor.revisar', 'saldos_favor.ajustar', 'saldos_favor.caja'] as $perm) {
            Permission::findOrCreate($perm);
            $this->user->givePermissionTo($perm);
        }
        $this->cliente = Cliente::create([
            'numero_cliente' => '90003',
            'nombre' => 'Cliente Fase2 SAF',
            'lista_actual_id' => $lista->id,
            'monto_venta_actual' => 0,
        ]);
        foreach (['pago_de_mas', 'sobrante_envio', 'ajuste_admin'] as $codigo) {
            SafMotivo::firstOrCreate(
                ['codigo' => $codigo],
                ['nombre' => $codigo, 'categoria' => 'ajustes', 'requiere_detalle' => false, 'activo' => true, 'orden' => 1]
            );
        }
    }

    private function generar(float $monto): SafCredito
    {
        return app(GenerarCreditoSafService::class)->handle([
            'cliente_id' => $this->cliente->id,
            'monto' => $monto,
            'saf_motivo_id' => SafMotivo::where('codigo', 'pago_de_mas')->value('id'),
            'canal_origen' => 'bellaroma',
            'generado_por_id' => $this->user->id,
        ]);
    }

    private function pedidoStub(array $extra = []): PedidoBma
    {
        $estatus = CatalogoEstatusPedido::firstOrCreate(
            ['codigo_interno' => 'PENDIENTE_AUX'],
            [
                'nombre_visual' => 'Pendiente auxiliar',
                'color_hex' => '#f59e0b',
                'fase_ciclo' => CatalogoEstatusPedido::FASE_PENDIENTE_AUXILIAR,
                'orden' => 2,
                'activo' => true,
            ]
        );

        return PedidoBma::create(array_merge([
            'folio' => 'BMA-F2-'.uniqid(),
            'vendedor_id' => $this->user->id,
            'cliente_id' => $this->cliente->id,
            'catalogo_estatus_pedido_id' => $estatus->id,
            'total_mercancia' => 1000,
            'costo_envio' => 200,
            'aplica_seguro' => false,
            'costo_seguro' => 0,
            'saldo_a_favor' => 0,
            'total_a_cobrar' => 1200,
            'fecha' => now()->toDateString(),
        ], $extra));
    }

    public function test_reconciliar_total_baja_genera_credito_excedente(): void
    {
        $pedido = $this->pedidoStub();
        PedidoBmaPago::create([
            'pedido_bma_id' => $pedido->id,
            'numero_exhibicion' => 1,
            'monto' => 1200,
            'estado_revision' => PedidoBmaPago::REVISION_CONFIRMADO,
            'capturado_por_id' => $this->user->id,
        ]);

        $pedido->update([
            'costo_envio' => 100,
            'total_a_cobrar' => 1100,
        ]);

        $result = app(ReconciliarTotalPedidoSafService::class)->handle(
            $pedido->fresh(),
            1200,
            $this->user->id,
            'sobrante_envio'
        );

        $this->assertNotNull($result['credito_id']);
        $this->assertEquals(100.0, $result['excedente']);
        $this->assertDatabaseHas('saf_creditos', [
            'id' => $result['credito_id'],
            'pedido_bma_id' => $pedido->id,
        ]);
    }

    public function test_reconciliar_total_aumento_abre_incidencia(): void
    {
        $pedido = $this->pedidoStub(['total_a_cobrar' => 1000, 'costo_envio' => 0]);
        PedidoBmaPago::create([
            'pedido_bma_id' => $pedido->id,
            'numero_exhibicion' => 1,
            'monto' => 1000,
            'estado_revision' => PedidoBmaPago::REVISION_CONFIRMADO,
            'capturado_por_id' => $this->user->id,
        ]);

        $pedido->update([
            'costo_envio' => 150,
            'total_a_cobrar' => 1150,
        ]);

        $result = app(ReconciliarTotalPedidoSafService::class)->handle(
            $pedido->fresh(),
            1000,
            $this->user->id
        );

        $this->assertNotNull($result['incidencia_id']);
        $this->assertDatabaseHas('saf_incidencias', [
            'id' => $result['incidencia_id'],
            'tipo' => 'total_aumento',
            'estado' => SafIncidencia::ESTADO_ABIERTA,
        ]);
    }

    public function test_reactivar_credito_vencido(): void
    {
        $credito = $this->generar(300);
        $credito->update([
            'fecha_vencimiento' => now()->subDay()->toDateString(),
        ]);
        app(VencerCreditosSafService::class)->handle(now());
        $credito->refresh();
        $this->assertSame(SafCredito::ESTADO_VENCIDO, $credito->estado_financiero);

        $reactivado = app(ReactivarCreditoSafService::class)->handle($credito->id, $this->user->id, 'Reactivo test');
        $this->assertSame(SafCredito::ESTADO_DISPONIBLE, $reactivado->estado_financiero);
        $this->assertEquals(300.0, (float) $reactivado->monto_disponible);
        $this->assertDatabaseHas('saf_movimientos', [
            'saf_credito_id' => $credito->id,
            'tipo' => SafMovimiento::TIPO_REACTIVACION,
        ]);
    }

    public function test_revertir_aplicacion_pedido(): void
    {
        $credito = $this->generar(400);
        $pedido = $this->pedidoStub();
        $sync = app(SincronizarAplicacionesPedidoSafService::class);
        $sync->reservarParaPedido($pedido, [
            ['saf_credito_id' => $credito->id, 'monto' => 150],
        ], $this->user->id);
        $sync->aplicarReservasPedido($pedido->fresh(), $this->user->id);

        $app = SafPedidoAplicacion::where('pedido_bma_id', $pedido->id)->first();
        $this->assertSame(SafPedidoAplicacion::ESTADO_APLICADO, $app->estado);

        app(RevertirAplicacionSafService::class)->handle($app->id, null, null, $this->user->id, 'Reversión test');

        $credito->refresh();
        $app->refresh();
        $this->assertEquals(400.0, (float) $credito->monto_disponible);
        $this->assertSame(SafPedidoAplicacion::ESTADO_LIBERADO, $app->estado);
        $this->assertDatabaseHas('saf_movimientos', [
            'saf_credito_id' => $credito->id,
            'tipo' => SafMovimiento::TIPO_REVERSION,
        ]);
    }

    public function test_validar_pago_exige_cobertura_y_acepta_excedente(): void
    {
        $pedido = $this->pedidoStub(['total_a_cobrar' => 1000]);
        PedidoBmaPago::create([
            'pedido_bma_id' => $pedido->id,
            'numero_exhibicion' => 1,
            'monto' => 400,
            'ruta_archivo' => 'pedidos_bma/pagos/parcial.jpg',
            'estado_revision' => PedidoBmaPago::REVISION_PENDIENTE,
            'capturado_por_id' => $this->user->id,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('cubrir el total');
        app(ValidarPagoPedidoBmaService::class)->ejecutar($pedido, $this->user->id);
    }

    public function test_validar_pago_con_excedente_ok(): void
    {
        $pedido2 = $this->pedidoStub(['total_a_cobrar' => 500, 'folio' => 'BMA-F2-EXC-'.uniqid()]);
        PedidoBmaPago::create([
            'pedido_bma_id' => $pedido2->id,
            'numero_exhibicion' => 1,
            'monto' => 700,
            'ruta_archivo' => 'pedidos_bma/pagos/exc.jpg',
            'estado_revision' => PedidoBmaPago::REVISION_PENDIENTE,
            'capturado_por_id' => $this->user->id,
        ]);
        $res2 = app(ValidarPagoPedidoBmaService::class)->ejecutar($pedido2, $this->user->id);
        $this->assertEquals(200.0, (float) $res2['resumen']['excedente']);
        $this->assertNull($res2['incidencia_id']);
        $this->assertNotNull($pedido2->fresh()->pago_validado_at);
    }

    public function test_evidencia_firmada_caja_sin_duplicar_aplicacion(): void
    {
        $credito = $this->generar(250);
        app(ReservarSaldoSafService::class)->handle(
            $this->cliente->id,
            100,
            $this->user->id,
            [['saf_credito_id' => $credito->id, 'monto' => 100]]
        );
        app(AplicarReservaSafService::class)->handle($credito->id, 100, $this->user->id);

        $comp = SafComprobanteCaja::create([
            'folio' => 'SAF-PDV-000099',
            'cliente_id' => $this->cliente->id,
            'saf_cuenta_id' => $credito->saf_cuenta_id,
            'saldo_anterior' => 250,
            'monto_aplicado' => 100,
            'saldo_restante' => 150,
            'creditos_detalle' => [['saf_credito_id' => $credito->id, 'folio' => $credito->folio, 'monto' => 100]],
            'estado' => SafComprobanteCaja::ESTADO_PENDIENTE_FIRMA,
            'generado_por_id' => $this->user->id,
            'aplicado_at' => now(),
        ]);

        $disponibleAntes = (float) $credito->fresh()->monto_disponible;

        $this->actingAs($this->user)->post(route('saldos_favor.caja.firmar', $comp), [
            'evidencia_firmada' => UploadedFile::fake()->image('firma.jpg'),
        ])->assertRedirect();

        $comp->refresh();
        $this->assertSame(SafComprobanteCaja::ESTADO_FIRMADO_PENDIENTE_REVISION, $comp->estado);
        $this->assertNotNull($comp->ruta_evidencia_firmada);
        $this->assertEquals($disponibleAntes, (float) $credito->fresh()->monto_disponible);
    }

    public function test_bandeja_filtros_y_cola_incidencias(): void
    {
        $this->generar(80);
        $this->generar(20);
        SafIncidencia::create([
            'cliente_id' => $this->cliente->id,
            'tipo' => 'total_aumento',
            'descripcion' => 'Prueba incidencia',
            'estado' => SafIncidencia::ESTADO_ABIERTA,
            'creado_por_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('saldos_favor.index', [
                'tab' => 'incidencias',
                'canal_origen' => 'bellaroma',
                'monto_min' => 50,
            ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('SaldosAFavor/Index', false)
            ->has('cuentas.data', 1)
            ->where('cuentas.data.0.cliente_id', $this->cliente->id)
            ->where('cuentas.data.0.disponible', 100)
            ->where('cuentas.data.0.saldos_disponibles', 2)
            ->where('cuentas.data.0.pendientes_revision', 2)
            ->has('colas.incidencias', 1)
            ->where('filtros.canal_origen', 'bellaroma')
        );
    }

    public function test_notificar_proximo_a_vencer(): void
    {
        Notification::fake();
        $credito = $this->generar(120);
        $credito->update(['fecha_vencimiento' => now()->addDays(10)->toDateString()]);

        Artisan::call('saldos-favor:notificar-vencimientos', ['--dias' => 30]);

        Notification::assertSentTo($this->user, SaldoFavorProximoVencerNotification::class);
    }
}
