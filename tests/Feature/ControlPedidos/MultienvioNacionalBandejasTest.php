<?php

namespace Tests\Feature\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\Departamento;
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

    private User $auxiliar;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Storage::fake('public');
        Storage::disk('public')->put('pedidos_bma/soporte.pdf', 'PDF');
        $depto = Departamento::query()->create(['nombre' => 'Ventas MEV Aud', 'activo' => true]);
        $this->ventas = User::factory()->create(['departamento_id' => $depto->id]);
        $this->ventas->givePermissionTo(Permission::findOrCreate('control_pedidos.crear', 'web'));
        $this->ventas->givePermissionTo(Permission::findOrCreate('control_pedidos.ver_listado', 'web'));
        $this->auxiliar = User::factory()->create(['departamento_id' => $depto->id]);
        $this->auxiliar->givePermissionTo(Permission::findOrCreate('control_pedidos.auditar', 'web'));
        foreach ([
            CatalogoEstatusPedido::FASE_PESAJE_RESPONDIDO => ['MEV-PR', 'Pesaje respondido', 1],
            CatalogoEstatusPedido::FASE_PENDIENTE_AUXILIAR => ['MEV-PA', 'Pendiente auxiliar', 2],
            CatalogoEstatusPedido::FASE_EN_CEDIS => ['MEV-CE', 'En CEDIS', 3],
        ] as $fase => [$codigo, $nombre, $orden]) {
            CatalogoEstatusPedido::query()->firstOrCreate(
                ['fase_ciclo' => $fase],
                ['codigo_interno' => $codigo, 'nombre_visual' => $nombre, 'color_hex' => '#999999', 'orden' => $orden, 'activo' => true]
            );
        }
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

    public function test_detalle_de_auditoria_incluye_hermanos_del_multienvio(): void
    {
        $m = $this->grupoEnAuditoria();

        $detalle = $this->actingAs($this->auxiliar)
            ->getJson(route('control_pedidos.auditar.detalle', $m[1]))
            ->assertOk()
            ->json('pedido');

        $this->assertSame($m[1]->id, $detalle['id']);
        $this->assertSame(1, $detalle['multienvio']['orden']);
        $this->assertSame(3, $detalle['multienvio']['n_total']);
        $this->assertEqualsCanonicalizing(
            [$m[1]->id, $m[2]->id, $m[3]->id],
            array_column($detalle['multienvio']['hermanos'], 'id')
        );
    }

    public function test_detalle_de_un_envio_aprobado_sigue_disponible_y_actualiza_el_grupo(): void
    {
        $m = $this->grupoEnAuditoria();
        $cedis = CatalogoEstatusPedido::query()->where('fase_ciclo', CatalogoEstatusPedido::FASE_EN_CEDIS)->firstOrFail();
        $m[1]->forceFill(['catalogo_estatus_pedido_id' => $cedis->id])->save();

        $aprobado = $this->actingAs($this->auxiliar)
            ->getJson(route('control_pedidos.auditar.detalle', $m[1]))
            ->assertOk()
            ->json('pedido');
        $this->assertSame(CatalogoEstatusPedido::FASE_EN_CEDIS, $aprobado['estatus']['fase_ciclo']);

        $pendiente = $this->getJson(route('control_pedidos.auditar.detalle', $m[2]))
            ->assertOk()
            ->json('pedido');
        $this->assertSame(CatalogoEstatusPedido::FASE_PENDIENTE_AUXILIAR, $pendiente['estatus']['fase_ciclo']);
        $hermano1 = collect($pendiente['multienvio']['hermanos'])->firstWhere('id', $m[1]->id);
        $this->assertSame(CatalogoEstatusPedido::FASE_EN_CEDIS, $hermano1['estatus_fase']);
    }

    public function test_detalle_de_un_pedido_ordinario_no_adjunta_multienvio(): void
    {
        $suelto = $this->pedidoNacional(['folio_remision' => 'SUELTO-AUD']);
        $pendiente = CatalogoEstatusPedido::query()->where('fase_ciclo', CatalogoEstatusPedido::FASE_PENDIENTE_AUXILIAR)->firstOrFail();
        $suelto->forceFill(['catalogo_estatus_pedido_id' => $pendiente->id])->save();

        $detalle = $this->actingAs($this->auxiliar)
            ->getJson(route('control_pedidos.auditar.detalle', $suelto))
            ->assertOk()
            ->json('pedido');

        $this->assertSame($suelto->id, $detalle['id']);
        $this->assertArrayNotHasKey('multienvio', $detalle);
    }

    /** @return array<int, PedidoBma> miembros por orden, en pendiente de auxiliar */
    private function grupoEnAuditoria(): array
    {
        $m = $this->grupoConfirmado();
        $pendiente = CatalogoEstatusPedido::query()->where('fase_ciclo', CatalogoEstatusPedido::FASE_PENDIENTE_AUXILIAR)->firstOrFail();
        foreach ($m as $pedido) {
            $pedido->forceFill(['catalogo_estatus_pedido_id' => $pendiente->id])->save();
        }

        return $m;
    }
}
