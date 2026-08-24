<?php

namespace Tests\Feature\SaldosAFavor;

use App\Models\CatalogoBanco;
use App\Models\CatalogoBancoDepartamento;
use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\Departamento;
use App\Models\SaldosAFavor\PedidoBmaPago;
use App\Models\User;
use App\Services\ControlPedidos\PagosPedidoBmaConfig;
use App\Services\ControlPedidos\ValidarPagoPedidoBmaService;
use App\Services\SaldosAFavor\CoberturaPagoPedidoBmaService;
use App\Services\SaldosAFavor\EliminarPagoPedidoBmaService;
use App\Services\SaldosAFavor\RechazarPagosPedidoBmaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class Fase1IntegridadPagosPedidoBmaTest extends TestCase
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
        if (! DB::table('catalogo_bancos')->exists()) {
            DB::table('catalogo_bancos')->insert([
                'nombre' => 'BBVA', 'activo' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    private function estatus(string $fase, string $codigo): CatalogoEstatusPedido
    {
        return CatalogoEstatusPedido::query()->firstOrCreate(
            ['fase_ciclo' => $fase],
            [
                'codigo_interno' => $codigo,
                'nombre_visual' => $fase,
                'color_hex' => '#64748B',
                'activo' => true,
                'orden' => 1,
            ]
        );
    }

    private function crearPedido(User $vendedor, CatalogoEstatusPedido $estatus, array $extra = []): PedidoBma
    {
        $this->seedMinimo();

        return PedidoBma::query()->create(array_merge([
            'folio' => 'T-'.uniqid(),
            'fecha' => now()->toDateString(),
            'cliente_id' => DB::table('clientes')->value('id'),
            'vendedor_id' => $vendedor->id,
            'catalogo_estatus_pedido_id' => $estatus->id,
            'total_mercancia' => 1000,
            'costo_envio' => 0,
            'aplica_seguro' => false,
            'costo_seguro' => 0,
            'saldo_a_favor' => 0,
            'total_a_cobrar' => 1000,
            'numero_cajas' => 1,
            'estatus_envio' => PedidoBma::ESTATUS_ENVIO_COMPLETO,
            'es_resguardo' => false,
        ], $extra));
    }

    public function test_pago_rechazado_no_suma_y_archivo_se_conserva(): void
    {
        Storage::fake('public');
        Permission::findOrCreate('control_pedidos.auditar', 'web');

        $estatusPend = $this->estatus(CatalogoEstatusPedido::FASE_PENDIENTE_AUXILIAR, 'AZUL');
        $this->estatus(CatalogoEstatusPedido::FASE_RECHAZADO_VENDEDORA, 'NARANJA');

        $vendedor = User::factory()->create();
        $auxiliar = User::factory()->create();
        $auxiliar->givePermissionTo('control_pedidos.auditar');

        $pedido = $this->crearPedido($vendedor, $estatusPend);
        $ruta = 'pedidos_bma/pagos/'.$pedido->id.'/comp.jpg';
        Storage::disk('public')->put($ruta, 'fake');

        $pago = PedidoBmaPago::query()->create([
            'pedido_bma_id' => $pedido->id,
            'numero_exhibicion' => 1,
            'monto' => 1000,
            'forma_pago' => 'efectivo',
            'ruta_archivo' => $ruta,
            'nombre_original' => 'comp.jpg',
            'estado_revision' => PedidoBmaPago::REVISION_PENDIENTE,
            'activo_para_cobertura' => true,
            'capturado_por_id' => $vendedor->id,
        ]);

        app(RechazarPagosPedidoBmaService::class)->ejecutar(
            $pedido,
            [$pago->id],
            'Comprobante ilegible',
            $auxiliar->id
        );

        $pago->refresh();
        $this->assertFalse((bool) $pago->activo_para_cobertura);
        $this->assertSame(PedidoBmaPago::REVISION_RECHAZADO, $pago->estado_revision);
        Storage::disk('public')->assertExists($ruta);

        $resumen = app(CoberturaPagoPedidoBmaService::class)->calcular($pedido->fresh());
        $this->assertSame('0.00', $resumen['pagos_validos']);
        $this->assertFalse($resumen['cubierto']);
    }

    public function test_pago_revisado_no_se_elimina(): void
    {
        Storage::fake('public');
        $estatus = $this->estatus(CatalogoEstatusPedido::FASE_BORRADOR, 'GRIS');
        $vendedor = User::factory()->create();
        $pedido = $this->crearPedido($vendedor, $estatus);
        $ruta = 'pedidos_bma/pagos/x.jpg';
        Storage::disk('public')->put($ruta, 'x');

        $pago = PedidoBmaPago::query()->create([
            'pedido_bma_id' => $pedido->id,
            'numero_exhibicion' => 1,
            'monto' => 100,
            'forma_pago' => 'efectivo',
            'ruta_archivo' => $ruta,
            'estado_revision' => PedidoBmaPago::REVISION_VERIFICADO,
            'activo_para_cobertura' => true,
        ]);

        $this->expectException(\RuntimeException::class);
        app(EliminarPagoPedidoBmaService::class)->handle($pago, $vendedor->id);
    }

    public function test_banco_fuera_de_departamento_rechazado(): void
    {
        $depto = Departamento::query()->create([
            'nombre' => 'Depto Test',
            'codigo' => 'TST',
            'activo' => true,
        ]);
        $bancoOk = CatalogoBanco::query()->create(['nombre' => 'Banco OK', 'activo' => true]);
        $bancoNo = CatalogoBanco::query()->create(['nombre' => 'Banco NO', 'activo' => true]);
        CatalogoBancoDepartamento::query()->create([
            'catalogo_banco_id' => $bancoOk->id,
            'departamento_id' => $depto->id,
            'activo' => true,
            'orden' => 1,
        ]);

        $vendedor = User::factory()->create(['departamento_id' => $depto->id]);
        $estatus = $this->estatus(CatalogoEstatusPedido::FASE_PENDIENTE_AUXILIAR, 'AZUL');
        $pedido = $this->crearPedido($vendedor, $estatus);
        $pedido->setRelation('vendedor', $vendedor);

        $this->expectException(\InvalidArgumentException::class);
        CoberturaPagoPedidoBmaService::assertBancoPermitido($pedido, $bancoNo->id);
    }

    public function test_validar_idempotente_con_lock(): void
    {
        Storage::fake('public');
        $estatus = $this->estatus(CatalogoEstatusPedido::FASE_PENDIENTE_AUXILIAR, 'AZUL');
        $vendedor = User::factory()->create();
        $auxiliar = User::factory()->create();
        $pedido = $this->crearPedido($vendedor, $estatus);

        $ruta = 'pedidos_bma/pagos/y.jpg';
        Storage::disk('public')->put($ruta, 'x');

        PedidoBmaPago::query()->create([
            'pedido_bma_id' => $pedido->id,
            'numero_exhibicion' => 1,
            'monto' => 1000,
            'forma_pago' => 'efectivo',
            'ruta_archivo' => $ruta,
            'estado_revision' => PedidoBmaPago::REVISION_PENDIENTE,
            'activo_para_cobertura' => true,
        ]);

        $svc = app(ValidarPagoPedidoBmaService::class);
        $a = $svc->ejecutar($pedido, $auxiliar->id);
        $b = $svc->ejecutar($pedido->fresh(), $auxiliar->id);

        $this->assertNotNull($a['pedido']->pago_validado_at);
        $this->assertEquals(
            $a['pedido']->pago_validado_at?->toDateTimeString(),
            $b['pedido']->pago_validado_at?->toDateTimeString()
        );
        $this->assertSame(PagosPedidoBmaConfig::DEFAULT_TOLERANCIA, $a['resumen']['tolerancia_aplicada']);
    }
}
