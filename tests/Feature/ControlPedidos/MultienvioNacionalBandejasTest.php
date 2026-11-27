<?php

namespace Tests\Feature\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\SaldosAFavor\PedidoBmaPago;
use App\Models\User;
use App\Services\ControlPedidos\ConsolidarPedidosEmpaqueService;
use App\Support\ControlPedidos\Nacional\BusquedaGrupoMultienvio;
use App\Support\ControlPedidos\Nacional\ResumenGrupoMultienvio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Feature\ControlPedidos\Concerns\EscenarioMultienvioNacional;
use Tests\TestCase;

/** Auditar, CEDIS y Delegado: búsqueda por hermano, comprobante compartido y no consolidar miembros. */
class MultienvioNacionalBandejasTest extends TestCase
{
    use EscenarioMultienvioNacional;
    use RefreshDatabase;

    private User $ventas;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Storage::fake('public');
        Storage::disk('public')->put('pedidos_bma/soporte.pdf', 'PDF');
        $this->ventas = User::factory()->create();
        $this->ventas->givePermissionTo(Permission::findOrCreate('control_pedidos.crear', 'web'));
        $this->ventas->givePermissionTo(Permission::findOrCreate('control_pedidos.ver_listado', 'web'));
        CatalogoEstatusPedido::query()->firstOrCreate(
            ['fase_ciclo' => CatalogoEstatusPedido::FASE_PESAJE_RESPONDIDO],
            ['codigo_interno' => 'MEV-PR', 'nombre_visual' => 'Pesaje respondido', 'color_hex' => '#999999', 'orden' => 1, 'activo' => true]
        );
    }

    /** @return array<int, PedidoBma> miembros por orden */
    private function grupoConfirmado(): array
    {
        [, $grupo, $cajas] = $this->escenario();
        $this->borrador($grupo, $cajas, [600, 500, 400], ['BND-A', 'BND-B', 'BND-C']);
        $this->confirmar($grupo->fresh());

        return PedidoBma::query()->where('multienvio_nacional_id', $grupo->id)->orderBy('multienvio_orden')->get()->keyBy('multienvio_orden')->all();
    }

    private function pago(PedidoBma $p, float $monto, string $referencia): void
    {
        PedidoBmaPago::create([
            'pedido_bma_id' => $p->id, 'numero_exhibicion' => 1, 'monto' => $monto, 'referencia' => $referencia,
            'ruta_archivo' => 'test/comprobante.jpg', 'nombre_original' => 'comprobante.jpg', 'mime_type' => 'image/jpeg',
            'tamano_bytes' => 100, 'estado_revision' => PedidoBmaPago::REVISION_PENDIENTE, 'capturado_por_id' => $this->ventas->id,
        ]);
    }

    public function test_buscar_por_folio_de_un_envio_encuentra_a_sus_hermanos(): void
    {
        $m = $this->grupoConfirmado();
        $otro = $this->pedidoNacional(['folio_remision' => 'AJENO-1']);

        $ids = PedidoBma::query()
            ->where(fn ($q) => BusquedaGrupoMultienvio::orHermano($q, 'BND-B'))
            ->pluck('id')->all();

        $this->assertEqualsCanonicalizing(array_map(fn ($x) => $x->id, array_values($m)), $ids);
        $this->assertNotContains($otro->id, $ids);
    }

    public function test_un_miembro_no_se_puede_consolidar(): void
    {
        $m = $this->grupoConfirmado();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('multienvío');
        app(ConsolidarPedidosEmpaqueService::class)->assertConsolidable($m[2]);
    }

    public function test_comprobante_compartido_se_avisa_sin_sumar_de_mas(): void
    {
        $m = $this->grupoConfirmado();
        $this->pago($m[1], 765.00, 'TRF-2040');
        $this->pago($m[2], 683.40, 'trf-2040');
        $this->pago($m[3], 591.60, 'OTRA-REF');

        $pagina = PedidoBma::query()->with(['estatus'])->whereIn('id', array_map(fn ($x) => $x->id, array_values($m)))->orderBy('multienvio_orden')->get();
        ResumenGrupoMultienvio::paraPagina($pagina, $this->ventas);

        $uno = $pagina[0]->getAttributes()['multienvio'];
        $this->assertCount(1, $uno['comprobantes_compartidos']);
        $this->assertSame([1, 2], $uno['comprobantes_compartidos'][0]['envios']);
        $this->assertSame(1448.40, $uno['comprobantes_compartidos'][0]['suma']);
        $this->assertSame([], $pagina[2]->getAttributes()['multienvio']['comprobantes_compartidos']);
    }
}
