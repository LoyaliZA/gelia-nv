<?php

namespace Tests\Unit\Reportes;

use App\Models\CatalogoBanco;
use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\Reportes\PedidoBmaCierrePago;
use App\Models\Reportes\PedidoBmaCierrePagoItem;
use App\Models\SaldosAFavor\PedidoBmaPago;
use App\Models\User;
use App\Services\Reportes\PagosPedidos\CalcularMetricasReporteVouchersValidadosService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CalcularMetricasReporteVouchersValidadosTest extends TestCase
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

    private function crearCierre(PedidoBma $pedido, User $validador, float $saf = 0): PedidoBmaCierrePago
    {
        return PedidoBmaCierrePago::query()->create([
            'pedido_bma_id' => $pedido->id,
            'version' => 1,
            'estado' => PedidoBmaCierrePago::ESTADO_VIGENTE,
            'origen' => PedidoBmaCierrePago::ORIGEN_FLUJO,
            'pedido_fecha' => $pedido->fecha,
            'validado_at' => now(),
            'validado_por_id' => $validador->id,
            'monto_venta' => 1000,
            'monto_envio' => 0,
            'monto_seguro' => 0,
            'total_pedido' => 1000,
            'saf_aplicado' => $saf,
            'total_a_cobrar' => max(0, 1000 - $saf),
            'pagos_validos' => max(0, 1000 - $saf),
            'diferencia' => 0,
            'excedente' => 0,
            'tolerancia_aplicada' => 0.44,
            'estado_cobertura' => 'cubierto',
            'folio_snapshot' => $pedido->folio,
            'cliente_id' => $pedido->cliente_id,
            'vendedor_id' => $pedido->vendedor_id,
        ]);
    }

    private function crearItem(
        PedidoBmaCierrePago $cierre,
        string $forma,
        float $monto,
        string $estado,
        ?CatalogoBanco $banco = null,
        ?string $referencia = null,
    ): PedidoBmaCierrePagoItem {
        $pago = PedidoBmaPago::query()->create([
            'pedido_bma_id' => $cierre->pedido_bma_id,
            'numero_exhibicion' => (int) PedidoBmaPago::query()->where('pedido_bma_id', $cierre->pedido_bma_id)->count() + 1,
            'monto' => $monto,
            'forma_pago' => $forma,
            'estado_revision' => $estado,
            'activo_para_cobertura' => true,
            'capturado_por_id' => $cierre->validado_por_id,
        ]);

        return PedidoBmaCierrePagoItem::query()->create([
            'pedido_bma_cierre_pago_id' => $cierre->id,
            'pedido_bma_pago_id' => $pago->id,
            'numero_exhibicion' => $pago->numero_exhibicion,
            'monto_snapshot' => $monto,
            'forma_pago_snapshot' => $forma,
            'catalogo_banco_id' => $banco?->id,
            'banco_snapshot' => $banco?->nombre,
            'referencia_snapshot' => $referencia,
            'fecha_pago_snapshot' => now(),
            'estado_revision_snapshot' => $estado,
            'activo_para_cobertura_snapshot' => true,
            'capturado_por_id' => $cierre->validado_por_id,
            'capturado_at_snapshot' => now(),
            'ruta_archivo_snapshot' => 'pagos/test/voucher.jpg',
        ]);
    }

    public function test_saf_no_suma_al_total_bancario_y_efectivo_pendiente_excluidos(): void
    {
        Permission::findOrCreate('reportes.pagos_pedidos.ver');

        $usuario = User::factory()->create();
        $usuario->givePermissionTo('reportes.pagos_pedidos.ver');

        $banco = CatalogoBanco::query()->create(['nombre' => 'Banco Test', 'activo' => true]);

        $pedido = $this->crearPedido($usuario);
        $cierre = $this->crearCierre($pedido, $usuario, 150);

        $this->crearItem($cierre, 'transferencia', 500, PedidoBmaPago::REVISION_VERIFICADO, $banco, 'REF-A');
        $this->crearItem($cierre, 'efectivo', 200, PedidoBmaPago::REVISION_VERIFICADO);
        $this->crearItem($cierre, 'transferencia', 300, PedidoBmaPago::REVISION_PENDIENTE, $banco, 'REF-B');

        $metricas = app(CalcularMetricasReporteVouchersValidadosService::class)->ejecutar($usuario, [
            'tipo_reporte' => 'vouchers',
            'estado_cierre' => 'vigente',
        ]);

        $this->assertSame('500.00', $metricas['total_ingreso_bancario']);
        $this->assertSame(1, $metricas['vouchers_validados']);
        $this->assertSame('150.00', $metricas['total_saf_relacionado']);
        $this->assertSame(1, $metricas['remisiones_con_saf']);
    }

    public function test_usuario_con_permiso_reporte_ve_vouchers_de_otras_vendedoras(): void
    {
        Permission::findOrCreate('reportes.pagos_pedidos.ver');

        $vendedora = User::factory()->create();
        $lector = User::factory()->create();
        $lector->givePermissionTo('reportes.pagos_pedidos.ver');

        $banco = CatalogoBanco::query()->create(['nombre' => 'Banco Test', 'activo' => true]);
        $pedido = $this->crearPedido($vendedora);
        $cierre = $this->crearCierre($pedido, $vendedora);
        $this->crearItem($cierre, 'transferencia', 750, PedidoBmaPago::REVISION_VERIFICADO, $banco, 'REF-OTRA');

        $metricas = app(CalcularMetricasReporteVouchersValidadosService::class)->ejecutar($lector, [
            'tipo_reporte' => 'vouchers',
            'estado_cierre' => 'vigente',
        ]);

        $this->assertSame('750.00', $metricas['total_ingreso_bancario']);
        $this->assertSame(1, $metricas['vouchers_validados']);
    }
}
