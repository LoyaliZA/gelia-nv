<?php

namespace Tests\Feature\Reportes;

use App\Models\CatalogoBanco;
use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\Reportes\PedidoBmaCierrePago;
use App\Models\Reportes\PedidoBmaCierrePagoItem;
use App\Models\SaldosAFavor\PedidoBmaPago;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ReporteVouchersValidadosTest extends TestCase
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

    private function crearCierre(PedidoBma $pedido, User $validador): PedidoBmaCierrePago
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
            'saf_aplicado' => 0,
            'total_a_cobrar' => 1000,
            'pagos_validos' => 1000,
            'diferencia' => 0,
            'excedente' => 0,
            'tolerancia_aplicada' => 0.44,
            'estado_cobertura' => 'cubierto',
            'folio_snapshot' => $pedido->folio,
            'cliente_id' => $pedido->cliente_id,
            'vendedor_id' => $pedido->vendedor_id,
        ]);
    }

    private function crearItemTransferencia(
        PedidoBmaCierrePago $cierre,
        CatalogoBanco $banco,
        float $monto,
        string $referencia,
    ): void {
        $pago = PedidoBmaPago::query()->create([
            'pedido_bma_id' => $cierre->pedido_bma_id,
            'numero_exhibicion' => (int) PedidoBmaPago::query()->where('pedido_bma_id', $cierre->pedido_bma_id)->count() + 1,
            'monto' => $monto,
            'forma_pago' => 'transferencia',
            'estado_revision' => PedidoBmaPago::REVISION_VERIFICADO,
            'activo_para_cobertura' => true,
            'capturado_por_id' => $cierre->validado_por_id,
        ]);

        PedidoBmaCierrePagoItem::query()->create([
            'pedido_bma_cierre_pago_id' => $cierre->id,
            'pedido_bma_pago_id' => $pago->id,
            'numero_exhibicion' => $pago->numero_exhibicion,
            'monto_snapshot' => $monto,
            'forma_pago_snapshot' => 'transferencia',
            'catalogo_banco_id' => $banco->id,
            'banco_snapshot' => $banco->nombre,
            'referencia_snapshot' => $referencia,
            'fecha_pago_snapshot' => now(),
            'estado_revision_snapshot' => PedidoBmaPago::REVISION_VERIFICADO,
            'activo_para_cobertura_snapshot' => true,
            'capturado_por_id' => $cierre->validado_por_id,
            'capturado_at_snapshot' => now(),
            'ruta_archivo_snapshot' => 'pagos/test/voucher.jpg',
        ]);
    }

    public function test_index_vouchers_devuelve_inertia_con_grupos(): void
    {
        Permission::findOrCreate('reportes.pagos_pedidos.ver');

        $usuario = User::factory()->create();
        $usuario->givePermissionTo('reportes.pagos_pedidos.ver');

        $banco = CatalogoBanco::query()->create(['nombre' => 'Banco A', 'activo' => true]);
        $pedido = $this->crearPedido($usuario);
        $cierre = $this->crearCierre($pedido, $usuario);
        $this->crearItemTransferencia($cierre, $banco, 400, 'REF-1');
        $this->crearItemTransferencia($cierre, $banco, 600, 'REF-2');

        $response = $this->actingAs($usuario)->get(route('reportes.pagos_pedidos.index', [
            'tipo_reporte' => 'vouchers',
        ]));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Reportes/PagosPedidos/Index')
                ->where('vouchers_disponible', true)
                ->where('tipo_reporte', 'vouchers')
                ->has('grupos_vouchers', 1)
                ->has('metricas_vouchers')
                ->where('metricas_vouchers.total_ingreso_bancario', '1000.00')
            );
    }

    public function test_cambio_agrupar_por_no_altera_total_ingreso_bancario(): void
    {
        Permission::findOrCreate('reportes.pagos_pedidos.ver');

        $usuario = User::factory()->create();
        $usuario->givePermissionTo('reportes.pagos_pedidos.ver');

        $banco1 = CatalogoBanco::query()->create(['nombre' => 'Banco Uno', 'activo' => true]);
        $banco2 = CatalogoBanco::query()->create(['nombre' => 'Banco Dos', 'activo' => true]);

        $pedido = $this->crearPedido($usuario);
        $cierre = $this->crearCierre($pedido, $usuario);
        $this->crearItemTransferencia($cierre, $banco1, 250, 'REF-A');
        $this->crearItemTransferencia($cierre, $banco2, 750, 'REF-B');

        $movimiento = $this->actingAs($usuario)->get(route('reportes.pagos_pedidos.index', [
            'tipo_reporte' => 'vouchers',
            'agrupar_por' => 'movimiento',
        ]));

        $movimiento->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('agrupar_por_vouchers', 'movimiento')
                ->where('metricas_vouchers.total_ingreso_bancario', '1000.00')
            );

        $banco = $this->actingAs($usuario)->get(route('reportes.pagos_pedidos.index', [
            'tipo_reporte' => 'vouchers',
            'agrupar_por' => 'banco',
        ]));

        $banco->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('agrupar_por_vouchers', 'banco')
                ->where('metricas_vouchers.total_ingreso_bancario', '1000.00')
                ->has('grupos_vouchers', 2)
            );
    }
}
