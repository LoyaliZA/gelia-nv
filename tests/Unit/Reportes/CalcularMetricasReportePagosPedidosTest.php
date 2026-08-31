<?php

namespace Tests\Unit\Reportes;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\Reportes\PedidoBmaCierrePago;
use App\Models\Reportes\PedidoBmaCierrePagoItem;
use App\Models\SaldosAFavor\PedidoBmaPago;
use App\Models\User;
use App\Services\Reportes\PagosPedidos\CalcularMetricasReportePagosPedidosService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CalcularMetricasReportePagosPedidosTest extends TestCase
{
    use RefreshDatabase;

    private function seedMinimo(): void
    {
        $now = now();
        if (! DB::table('catalogo_listas_descuento')->exists()) {
            DB::table('catalogo_listas_descuento')->insert([
                'nombre' => 'Lista Test', 'activo' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        if (! DB::table('clientes')->exists()) {
            DB::table('clientes')->insert([
                'numero_cliente' => '1001',
                'nombre' => 'Cliente Test',
                'lista_actual_id' => DB::table('catalogo_listas_descuento')->value('id'),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function estatus(): CatalogoEstatusPedido
    {
        return CatalogoEstatusPedido::query()->firstOrCreate(
            ['fase_ciclo' => 'validado'],
            [
                'codigo_interno' => 'validado_test',
                'nombre_visual' => 'Validado',
                'color_hex' => '#64748B',
                'activo' => true,
                'orden' => 1,
            ]
        );
    }

    private function crearPedido(User $vendedor): PedidoBma
    {
        $this->seedMinimo();

        return PedidoBma::query()->create([
            'folio' => 'T-'.uniqid(),
            'fecha' => now()->toDateString(),
            'cliente_id' => DB::table('clientes')->value('id'),
            'vendedor_id' => $vendedor->id,
            'catalogo_estatus_pedido_id' => $this->estatus()->id,
            'total_mercancia' => 1000,
            'costo_envio' => 0,
            'aplica_seguro' => false,
            'costo_seguro' => 0,
            'saldo_a_favor' => 0,
            'total_a_cobrar' => 1000,
            'numero_cajas' => 1,
            'estatus_envio' => PedidoBma::ESTATUS_ENVIO_COMPLETO,
            'es_resguardo' => false,
        ]);
    }

    private function crearCierre(
        PedidoBma $pedido,
        User $validador,
        string $estadoCobertura,
        float $saf = 0,
        float $totalPedido = 1000,
    ): PedidoBmaCierrePago {
        return PedidoBmaCierrePago::query()->create([
            'pedido_bma_id' => $pedido->id,
            'version' => 1,
            'estado' => PedidoBmaCierrePago::ESTADO_VIGENTE,
            'origen' => PedidoBmaCierrePago::ORIGEN_FLUJO,
            'pedido_fecha' => $pedido->fecha,
            'validado_at' => now(),
            'validado_por_id' => $validador->id,
            'monto_venta' => $totalPedido,
            'monto_envio' => 0,
            'monto_seguro' => 0,
            'total_pedido' => $totalPedido,
            'saf_aplicado' => $saf,
            'total_a_cobrar' => max(0, $totalPedido - $saf),
            'pagos_validos' => max(0, $totalPedido - $saf),
            'diferencia' => 0,
            'excedente' => 0,
            'tolerancia_aplicada' => 0.44,
            'estado_cobertura' => $estadoCobertura,
            'folio_snapshot' => $pedido->folio,
            'cliente_id' => $pedido->cliente_id,
            'vendedor_id' => $pedido->vendedor_id,
        ]);
    }

    private function crearPago(PedidoBma $pedido): PedidoBmaPago
    {
        return PedidoBmaPago::query()->create([
            'pedido_bma_id' => $pedido->id,
            'numero_exhibicion' => 1,
            'monto' => 500,
            'forma_pago' => 'transferencia',
            'estado_revision' => PedidoBmaPago::REVISION_VERIFICADO,
            'activo_para_cobertura' => true,
            'ruta_archivo' => 'pagos/test/voucher.jpg',
            'nombre_original' => 'voucher.jpg',
            'mime_type' => 'image/jpeg',
            'capturado_por_id' => $pedido->vendedor_id,
        ]);
    }

    public function test_contadores_cobertura_y_remisiones(): void
    {
        Permission::findOrCreate('reportes.pagos_pedidos.ver');

        $usuario = User::factory()->create();
        $usuario->givePermissionTo('reportes.pagos_pedidos.ver');

        $pedidoCubierto = $this->crearPedido($usuario);
        $pedidoCubiertoSaf = $this->crearPedido($usuario);
        $pedidoParcial = $this->crearPedido($usuario);
        $pedidoExcedente = $this->crearPedido($usuario);

        $cierreCubierto = $this->crearCierre($pedidoCubierto, $usuario, 'cubierto', 0, 800);
        $cierreCubiertoSaf = $this->crearCierre($pedidoCubiertoSaf, $usuario, 'cubierto', 100, 900);
        $cierreParcial = $this->crearCierre($pedidoParcial, $usuario, 'parcial', 0, 700);
        $cierreExcedente = $this->crearCierre($pedidoExcedente, $usuario, 'con_excedente', 0, 600);

        $pago = $this->crearPago($pedidoCubierto);
        PedidoBmaCierrePagoItem::query()->create([
            'pedido_bma_cierre_pago_id' => $cierreCubierto->id,
            'pedido_bma_pago_id' => $pago->id,
            'numero_exhibicion' => 1,
            'monto_snapshot' => 800,
            'estado_revision_snapshot' => PedidoBmaPago::REVISION_VERIFICADO,
            'activo_para_cobertura_snapshot' => true,
            'ruta_archivo_snapshot' => 'pagos/test/voucher.jpg',
        ]);

        $metricas = app(CalcularMetricasReportePagosPedidosService::class)->ejecutar($usuario, [
            'estado_cierre' => 'vigente',
            'tipo_reporte' => 'pedido',
        ]);

        $this->assertSame(4, $metricas['pedidos_validados']);
        $this->assertSame('3000.00', $metricas['total_remisiones']);
        $this->assertSame(2, $metricas['pedidos_cubiertos']);
        $this->assertSame(1, $metricas['pedidos_cubiertos_con_saf']);
        $this->assertSame(1, $metricas['pedidos_parciales']);
        $this->assertSame(1, $metricas['pedidos_con_excedente']);
        $this->assertSame(2, $metricas['pedidos_observaciones']);
        $this->assertSame(1, $metricas['cantidad_vouchers']);
    }
}
