<?php

namespace Tests\Feature\Reportes;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Models\Reportes\PedidoBmaCierrePago;
use App\Models\SaldosAFavor\PedidoBmaPago;
use App\Models\User;
use App\Services\ControlPedidos\GestionarRemisionPedidoBmaService;
use App\Services\ControlPedidos\ValidarPagoPedidoBmaService;
use App\Services\Reportes\PagosPedidos\RegistrarCierrePagoPedidoService;
use App\Services\SaldosAFavor\RechazarPagosPedidoBmaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CierresPagoPedidoBmaTest extends TestCase
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

    private function crearPedido(User $vendedor, CatalogoEstatusPedido $estatus): PedidoBma
    {
        $this->seedMinimo();

        return PedidoBma::query()->create([
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
        ]);
    }

    public function test_validar_crea_cierre_v1(): void
    {
        Storage::fake('public');
        Permission::findOrCreate('control_pedidos.auditar', 'web');

        $estatusPend = $this->estatus(CatalogoEstatusPedido::FASE_PENDIENTE_AUXILIAR, 'AZUL');
        $vendedor = User::factory()->create();
        $auxiliar = User::factory()->create();
        $auxiliar->givePermissionTo('control_pedidos.auditar');

        $pedido = $this->crearPedido($vendedor, $estatusPend);
        PedidoBmaPago::query()->create([
            'pedido_bma_id' => $pedido->id,
            'numero_exhibicion' => 1,
            'monto' => 1000,
            'forma_pago' => 'efectivo',
            'estado_revision' => PedidoBmaPago::REVISION_PENDIENTE,
            'activo_para_cobertura' => true,
            'capturado_por_id' => $vendedor->id,
        ]);

        app(ValidarPagoPedidoBmaService::class)->ejecutar($pedido, $auxiliar->id);

        $cierre = PedidoBmaCierrePago::query()->where('pedido_bma_id', $pedido->id)->first();
        $this->assertNotNull($cierre);
        $this->assertSame(1, $cierre->version);
        $this->assertSame(PedidoBmaCierrePago::ESTADO_VIGENTE, $cierre->estado);
        $this->assertSame('1000.00', (string) $cierre->pagos_validos);
    }

    public function test_rechazo_revoca_cierre_y_revalidacion_crea_v2(): void
    {
        Storage::fake('public');
        Permission::findOrCreate('control_pedidos.auditar', 'web');
        $this->estatus(CatalogoEstatusPedido::FASE_RECHAZADO_VENDEDORA, 'NARANJA');

        $estatusPend = $this->estatus(CatalogoEstatusPedido::FASE_PENDIENTE_AUXILIAR, 'AZUL');
        $vendedor = User::factory()->create();
        $auxiliar = User::factory()->create();
        $auxiliar->givePermissionTo('control_pedidos.auditar');

        $pedido = $this->crearPedido($vendedor, $estatusPend);
        $pago = PedidoBmaPago::query()->create([
            'pedido_bma_id' => $pedido->id,
            'numero_exhibicion' => 1,
            'monto' => 1000,
            'forma_pago' => 'efectivo',
            'estado_revision' => PedidoBmaPago::REVISION_PENDIENTE,
            'activo_para_cobertura' => true,
            'capturado_por_id' => $vendedor->id,
        ]);

        app(ValidarPagoPedidoBmaService::class)->ejecutar($pedido, $auxiliar->id);
        app(RechazarPagosPedidoBmaService::class)->ejecutar($pedido->fresh(), [$pago->id], 'Comprobante ilegible', $auxiliar->id);

        $v1 = PedidoBmaCierrePago::query()->where('pedido_bma_id', $pedido->id)->where('version', 1)->first();
        $this->assertSame(PedidoBmaCierrePago::ESTADO_REVOCADO, $v1->estado);

        $pedido->update(['catalogo_estatus_pedido_id' => $estatusPend->id]);
        PedidoBmaPago::query()->create([
            'pedido_bma_id' => $pedido->id,
            'numero_exhibicion' => 2,
            'monto' => 1000,
            'forma_pago' => 'efectivo',
            'estado_revision' => PedidoBmaPago::REVISION_PENDIENTE,
            'activo_para_cobertura' => true,
            'capturado_por_id' => $vendedor->id,
        ]);

        app(ValidarPagoPedidoBmaService::class)->ejecutar($pedido->fresh(), $auxiliar->id);

        $this->assertSame(2, PedidoBmaCierrePago::query()->where('pedido_bma_id', $pedido->id)->count());
        $v2 = PedidoBmaCierrePago::query()->where('pedido_bma_id', $pedido->id)->where('version', 2)->first();
        $this->assertSame(PedidoBmaCierrePago::ESTADO_VIGENTE, $v2->estado);
    }

    public function test_remision_sustituida_se_conserva(): void
    {
        Storage::fake('public');
        $estatus = $this->estatus(CatalogoEstatusPedido::FASE_PENDIENTE_AUXILIAR, 'AZUL');
        $vendedor = User::factory()->create();
        $auxiliar = User::factory()->create();
        $pedido = $this->crearPedido($vendedor, $estatus);

        $pdf1 = UploadedFile::fake()->create('rem1.pdf', 100, 'application/pdf');
        $pdf2 = UploadedFile::fake()->create('rem2.pdf', 100, 'application/pdf');

        app(GestionarRemisionPedidoBmaService::class)->subir($pedido, $pdf1, $auxiliar->id);
        app(GestionarRemisionPedidoBmaService::class)->subir($pedido->fresh(), $pdf2, $auxiliar->id);

        $this->assertSame(2, PedidoBmaDocumento::query()->where('pedido_bma_id', $pedido->id)->where('tipo', PedidoBmaDocumento::TIPO_REMISION)->count());
        $this->assertSame(1, PedidoBmaDocumento::query()->where('pedido_bma_id', $pedido->id)->vigente()->count());
        $this->assertSame(1, PedidoBmaDocumento::query()->where('pedido_bma_id', $pedido->id)->historico()->count());
    }

    public function test_backfill_idempotente(): void
    {
        $estatus = $this->estatus(CatalogoEstatusPedido::FASE_PENDIENTE_AUXILIAR, 'AZUL');
        $vendedor = User::factory()->create();
        $auxiliar = User::factory()->create();
        $pedido = $this->crearPedido($vendedor, $estatus);
        $pedido->update([
            'pago_validado_at' => now(),
            'pago_validado_por_id' => $auxiliar->id,
        ]);
        PedidoBmaPago::query()->create([
            'pedido_bma_id' => $pedido->id,
            'numero_exhibicion' => 1,
            'monto' => 1000,
            'forma_pago' => 'efectivo',
            'estado_revision' => PedidoBmaPago::REVISION_VERIFICADO,
            'activo_para_cobertura' => true,
        ]);

        $this->artisan('reportes:backfill-cierres-pago')->assertSuccessful();
        $this->artisan('reportes:backfill-cierres-pago')->assertSuccessful();

        $this->assertSame(1, PedidoBmaCierrePago::query()->where('pedido_bma_id', $pedido->id)->count());
    }
}
