<?php

namespace Tests\Feature\SaldosAFavor;

use App\Models\CatalogoListaDescuento;
use App\Models\Cliente;
use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Models\SaldosAFavor\PedidoBmaPago;
use App\Models\SaldosAFavor\SafCredito;
use App\Models\SaldosAFavor\SafMotivo;
use App\Models\User;
use App\Services\ControlPedidos\AprobarPedidoBmaService;
use App\Services\ControlPedidos\ValidarPagoPedidoBmaService;
use App\Services\SaldosAFavor\EliminarPagoPedidoBmaService;
use App\Services\SaldosAFavor\RegistrarPagoPedidoBmaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BlindarPagoTotalPedidoTest extends TestCase
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
            'numero_cliente' => '90088',
            'nombre' => 'Cliente Blindar Pago',
            'lista_actual_id' => $lista->id,
            'monto_venta_actual' => 0,
        ]);
        SafMotivo::firstOrCreate(
            ['codigo' => 'pago_de_mas'],
            ['nombre' => 'De más', 'categoria' => 'diferencias_pago', 'requiere_detalle' => false, 'activo' => true, 'orden' => 1]
        );
    }

    private function pedidoStub(array $extra = [], string $fase = CatalogoEstatusPedido::FASE_BORRADOR): PedidoBma
    {
        $estatus = CatalogoEstatusPedido::firstOrCreate(
            ['codigo_interno' => 'BLINDAR-'.$fase],
            [
                'nombre_visual' => $fase,
                'color_hex' => '#94a3b8',
                'fase_ciclo' => $fase,
                'orden' => 1,
                'activo' => true,
            ]
        );

        return PedidoBma::create(array_merge([
            'folio' => 'BMA-BL-'.uniqid(),
            'vendedor_id' => $this->user->id,
            'cliente_id' => $this->cliente->id,
            'catalogo_estatus_pedido_id' => $estatus->id,
            'total_mercancia' => 1000,
            'costo_envio' => 0,
            'aplica_seguro' => false,
            'costo_seguro' => 0,
            'saldo_a_favor' => 0,
            'total_a_cobrar' => 1000,
            'fecha' => now()->toDateString(),
        ], $extra));
    }

    private function exhibicion(PedidoBma $pedido, float $monto, string $revision = PedidoBmaPago::REVISION_PENDIENTE): PedidoBmaPago
    {
        return PedidoBmaPago::create([
            'pedido_bma_id' => $pedido->id,
            'numero_exhibicion' => ((int) PedidoBmaPago::where('pedido_bma_id', $pedido->id)->max('numero_exhibicion')) + 1,
            'monto' => $monto,
            'forma_pago' => 'efectivo',
            'ruta_archivo' => 'pedidos_bma/pagos/bl.jpg',
            'estado_revision' => $revision,
            'capturado_por_id' => $this->user->id,
        ]);
    }

    public function test_enviar_con_pendiente_falla_con_monto_faltante(): void
    {
        $pedido = $this->pedidoStub();
        $this->exhibicion($pedido, 400);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Faltan $600.00');
        app(RegistrarPagoPedidoBmaService::class)->assertCubiertoParaEnviar($pedido);
    }

    public function test_saf_huerfano_sin_aplicaciones_no_deja_enviar(): void
    {
        $pedido = $this->pedidoStub(['saldo_a_favor' => 200, 'total_a_cobrar' => 800]);
        $this->exhibicion($pedido, 800);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('no corresponde');
        app(RegistrarPagoPedidoBmaService::class)->assertCubiertoParaEnviar($pedido);
    }

    public function test_validar_pago_exige_exhibiciones_verificadas(): void
    {
        $pedido = $this->pedidoStub([], CatalogoEstatusPedido::FASE_PENDIENTE_AUXILIAR);
        $this->exhibicion($pedido, 1000, PedidoBmaPago::REVISION_PENDIENTE);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('verificadas');
        app(ValidarPagoPedidoBmaService::class)->ejecutar($pedido, $this->user->id);
    }

    public function test_validar_pago_cubierto_verificado_con_excedente_ok(): void
    {
        $pedido = $this->pedidoStub([], CatalogoEstatusPedido::FASE_PENDIENTE_AUXILIAR);
        $this->exhibicion($pedido, 1200, PedidoBmaPago::REVISION_VERIFICADO);

        $res = app(ValidarPagoPedidoBmaService::class)->ejecutar($pedido, $this->user->id);

        $this->assertEquals(200.0, (float) $res['resumen']['excedente_generado']);
        $this->assertSame('con_excedente', $res['resumen']['cobertura']);
        $this->assertNotNull($pedido->fresh()->pago_validado_at);
    }

    public function test_aprobar_sin_pago_validado_falla_con_mensaje_especifico(): void
    {
        $pedido = $this->pedidoStub([], CatalogoEstatusPedido::FASE_PENDIENTE_AUXILIAR);
        $this->exhibicion($pedido, 1000, PedidoBmaPago::REVISION_VERIFICADO);
        PedidoBmaDocumento::create([
            'pedido_bma_id' => $pedido->id,
            'tipo' => PedidoBmaDocumento::TIPO_REMISION,
            'ruta_archivo' => 'pedidos_bma/remisiones/bl.pdf',
            'nombre_original' => 'remision.pdf',
            'mime_type' => 'application/pdf',
            'tamano_bytes' => 10,
            'orden' => 0,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Valide el pago antes de aprobar.');
        app(AprobarPedidoBmaService::class)->ejecutar($pedido->fresh(['estatus', 'remision']), $this->user->id);
    }

    public function test_eliminar_exhibicion_cancela_credito_excedente_huerfano(): void
    {
        $pedido = $this->pedidoStub();
        $file = UploadedFile::fake()->image('pago.jpg');
        $pago = app(RegistrarPagoPedidoBmaService::class)->handle(
            $pedido,
            ['monto' => 1200, 'forma_pago' => 'efectivo'],
            $file,
            $this->user->id
        );

        $credito = SafCredito::query()->where('pedido_bma_id', $pedido->id)->first();
        $this->assertNotNull($credito);
        $this->assertEquals(200.0, (float) $credito->monto_original);

        app(EliminarPagoPedidoBmaService::class)->handle($pago->fresh(), $this->user->id);

        $this->assertSame(SafCredito::ESTADO_CANCELADO, $credito->fresh()->estado_financiero);
        $resumen = app(RegistrarPagoPedidoBmaService::class)->resumenPago($pedido->fresh());
        $this->assertEquals(1000.0, $resumen['pendiente']);
        $this->assertEquals(0.0, $resumen['excedente_generado']);
    }
}
