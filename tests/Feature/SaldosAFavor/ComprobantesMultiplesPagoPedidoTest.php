<?php

namespace Tests\Feature\SaldosAFavor;

use App\Models\CatalogoListaDescuento;
use App\Models\Cliente;
use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\SaldosAFavor\PedidoBmaPago;
use App\Models\SaldosAFavor\PedidoBmaPagoArchivo;
use App\Models\SaldosAFavor\SafMotivo;
use App\Models\User;
use App\Services\SaldosAFavor\ActualizarPagoPedidoBmaService;
use App\Services\SaldosAFavor\CoberturaPagoPedidoBmaService;
use App\Services\SaldosAFavor\RegistrarPagoPedidoBmaService;
use App\Services\SaldosAFavor\SustituirPagoPedidoBmaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\TestCase;

class ComprobantesMultiplesPagoPedidoTest extends TestCase
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
            'numero_cliente' => '90120',
            'nombre' => 'Cliente Comprobantes',
            'lista_actual_id' => $lista->id,
            'monto_venta_actual' => 0,
        ]);
        SafMotivo::firstOrCreate(
            ['codigo' => 'pago_de_mas'],
            ['nombre' => 'De más', 'categoria' => 'diferencias_pago', 'requiere_detalle' => false, 'activo' => true, 'orden' => 1]
        );
    }

    public function test_registra_varios_comprobantes_y_no_los_duplica_al_reintentar(): void
    {
        $pedido = $this->pedido();
        $datos = [
            'monto' => 1000,
            'forma_pago' => 'efectivo',
            'idempotency_key' => 'multi-1',
        ];
        $servicio = app(RegistrarPagoPedidoBmaService::class);

        $pago = $servicio->handle($pedido, $datos, [
            UploadedFile::fake()->image('primero.jpg'),
            UploadedFile::fake()->image('segundo.jpg'),
        ], $this->user->id);

        $this->assertSame('primero.jpg', $pago->nombre_original);
        $this->assertCount(2, $pago->archivos);
        $this->assertSame(['primero.jpg', 'segundo.jpg'], $pago->archivos->pluck('nombre_original')->all());
        $this->assertSame($pago->ruta_archivo, $pago->archivos[0]->ruta_archivo);
        Storage::disk('public')->assertExists($pago->ruta_archivo);
        Storage::disk('public')->assertExists($pago->archivos[1]->ruta_archivo);

        $reintento = $servicio->handle($pedido->fresh(), $datos, [
            UploadedFile::fake()->image('otro.jpg'),
            UploadedFile::fake()->image('mas.jpg'),
        ], $this->user->id);

        $this->assertSame($pago->id, $reintento->id);
        $this->assertSame(2, $reintento->archivos()->count());
        $this->assertSame(1, PedidoBmaPago::query()->count());
    }

    public function test_rechaza_mas_de_cinco_comprobantes_sin_crear_el_pago(): void
    {
        $pedido = $this->pedido();
        $archivos = [];
        for ($i = 1; $i <= 6; $i++) {
            $archivos[] = UploadedFile::fake()->image("c{$i}.jpg");
        }

        try {
            app(RegistrarPagoPedidoBmaService::class)->handle($pedido, [
                'monto' => 1000,
                'forma_pago' => 'efectivo',
            ], $archivos, $this->user->id);
            $this->fail('Debió rechazar el sexto comprobante.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('5', $e->getMessage());
        }

        $this->assertSame(0, PedidoBmaPago::query()->count());
        $this->assertSame(0, PedidoBmaPagoArchivo::query()->count());
    }

    public function test_un_comprobante_sigue_cubriendo_el_pedido(): void
    {
        $pedido = $this->pedido();
        $pago = app(RegistrarPagoPedidoBmaService::class)->handle($pedido, [
            'monto' => 1000,
            'forma_pago' => 'efectivo',
        ], UploadedFile::fake()->image('unico.jpg'), $this->user->id);

        $this->assertSame(1, $pago->archivos()->count());
        $this->assertNotEmpty($pago->ruta_archivo);
        $resumen = app(CoberturaPagoPedidoBmaService::class)->calcular($pedido->fresh());
        $this->assertSame('cubierto', $resumen['cobertura']);
        app(RegistrarPagoPedidoBmaService::class)->assertCubiertoParaEnviar($pedido->fresh());
    }

    public function test_editar_agrega_comprobantes_sin_reemplazar_el_primero(): void
    {
        $pedido = $this->pedido();
        $pago = app(RegistrarPagoPedidoBmaService::class)->handle($pedido, [
            'monto' => 1000,
            'forma_pago' => 'efectivo',
        ], UploadedFile::fake()->image('primero.jpg'), $this->user->id);

        $actualizado = app(ActualizarPagoPedidoBmaService::class)->handle(
            $pago,
            [],
            [UploadedFile::fake()->image('extra.jpg')],
            $this->user->id
        );

        $this->assertSame('primero.jpg', $actualizado->nombre_original);
        $this->assertSame($pago->ruta_archivo, $actualizado->ruta_archivo);
        $this->assertSame(['primero.jpg', 'extra.jpg'], $actualizado->archivos->pluck('nombre_original')->all());
    }

    public function test_editar_un_pago_legado_conserva_el_archivo_original(): void
    {
        $pedido = $this->pedido();
        Storage::disk('public')->put('pedidos_bma/pagos/legacy.jpg', 'legado');
        $pago = PedidoBmaPago::query()->create([
            'pedido_bma_id' => $pedido->id,
            'numero_exhibicion' => 1,
            'monto' => 1000,
            'forma_pago' => 'efectivo',
            'ruta_archivo' => 'pedidos_bma/pagos/legacy.jpg',
            'nombre_original' => 'legacy.jpg',
            'estado_revision' => PedidoBmaPago::REVISION_PENDIENTE,
            'activo_para_cobertura' => true,
            'capturado_por_id' => $this->user->id,
        ]);

        $actualizado = app(ActualizarPagoPedidoBmaService::class)->handle(
            $pago,
            [],
            [UploadedFile::fake()->image('nuevo.jpg')],
            $this->user->id
        );

        $this->assertSame('legacy.jpg', $actualizado->nombre_original);
        $this->assertSame('pedidos_bma/pagos/legacy.jpg', $actualizado->ruta_archivo);
        $this->assertSame(['legacy.jpg', 'nuevo.jpg'], $actualizado->archivos->pluck('nombre_original')->all());
        $this->assertCount(2, $actualizado->comprobantes);
    }

    public function test_sustituir_acepta_varios_comprobantes(): void
    {
        $pedido = $this->pedido();
        $rechazado = PedidoBmaPago::query()->create([
            'pedido_bma_id' => $pedido->id,
            'numero_exhibicion' => 1,
            'monto' => 1000,
            'forma_pago' => 'efectivo',
            'ruta_archivo' => 'pedidos_bma/pagos/viejo.jpg',
            'nombre_original' => 'viejo.jpg',
            'estado_revision' => PedidoBmaPago::REVISION_RECHAZADO,
            'activo_para_cobertura' => true,
            'capturado_por_id' => $this->user->id,
        ]);

        $nuevo = app(SustituirPagoPedidoBmaService::class)->ejecutar(
            $rechazado,
            ['monto' => 1000, 'forma_pago' => 'efectivo'],
            [UploadedFile::fake()->image('n1.jpg'), UploadedFile::fake()->image('n2.jpg')],
            $this->user->id
        );

        $this->assertSame('n1.jpg', $nuevo->nombre_original);
        $this->assertSame(['n1.jpg', 'n2.jpg'], $nuevo->archivos->pluck('nombre_original')->all());
        $this->assertFalse((bool) $rechazado->fresh()->activo_para_cobertura);
    }

    private function pedido(): PedidoBma
    {
        $estatus = CatalogoEstatusPedido::firstOrCreate(
            ['codigo_interno' => 'COMP-BORRADOR'],
            [
                'nombre_visual' => 'Borrador',
                'color_hex' => '#94a3b8',
                'fase_ciclo' => CatalogoEstatusPedido::FASE_BORRADOR,
                'orden' => 1,
                'activo' => true,
            ]
        );

        return PedidoBma::create([
            'folio' => 'BMA-COMP-'.uniqid(),
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
        ]);
    }
}
