<?php

namespace Tests\Feature\Tiendanube;

use App\Jobs\Tiendanube\SimularPrecioLoteJob;
use App\Models\Tiendanube\TiendanubeConfiguracion;
use App\Models\Tiendanube\TiendanubePrecioLote;
use App\Models\Tiendanube\TiendanubePrecioLoteEvento;
use App\Models\Tiendanube\TiendanubeProducto;
use App\Models\Tiendanube\TiendanubeProductoVariante;
use App\Models\User;
use App\Services\Tiendanube\Precios\Lotes\TiendanubePrecioLoteAprobacionService;
use App\Services\Tiendanube\Precios\Lotes\TiendanubePrecioLoteService;
use App\Services\Tiendanube\Precios\Lotes\TiendanubePrecioLoteSimulacionService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\RefreshDatabaseSafe;
use Tests\Support\TiendanubePrecioMotorFixtures;
use Tests\TestCase;

class TiendanubePrecioLotesTest extends TestCase
{
    use RefreshDatabaseSafe;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        TiendanubeConfiguracion::obtener()->fill([
            'store_id' => 8004291,
            'app_id' => '37163',
            'access_token' => Crypt::encryptString('token-test'),
            'config_generation' => 1,
        ])->save();

        foreach ([
            'tiendanube.ver',
            'tiendanube.precios.ver',
            'tiendanube.precios.editar',
            'tiendanube.precios.reglas.ver',
            'tiendanube.precios.reglas.administrar',
            'tiendanube.precios.aprobar',
        ] as $perm) {
            Permission::findOrCreate($perm, 'web');
        }

        $this->user = User::factory()->create();
        $this->user->givePermissionTo([
            'tiendanube.ver',
            'tiendanube.precios.ver',
            'tiendanube.precios.editar',
            'tiendanube.precios.reglas.ver',
            'tiendanube.precios.reglas.administrar',
            'tiendanube.precios.aprobar',
        ]);

        $this->withoutMiddleware(PreventRequestForgery::class);
        config(['tiendanube.precio_lote_sync_max' => 200]);
    }

    public function test_seleccion_multipagina_congela_ids_una_sola_vez(): void
    {
        config(['tiendanube.precio_lote_resolver_per_page' => 2]);
        foreach ([1001, 1002, 1003, 1004, 1005] as $i => $id) {
            $this->crearVariante($id, 200 + $i, 'SKU-'.$id, '100.00', null, '40.00');
        }

        $selection = $this->actingAs($this->user)
            ->postJson(route('tiendanube.precios.selecciones.store'), [
                'modo' => 'pagina',
                'variante_ids' => [1001, 1002, 1003, 1004, 1005],
            ])
            ->assertCreated();

        $lote = $this->actingAs($this->user)
            ->postJson(route('tiendanube.precios.lotes.store'), [
                'selection_id' => $selection->json('selection_id'),
                'selection_version' => $selection->json('version'),
                'definicion' => $this->definicionAumento(),
            ])
            ->assertCreated();

        $items = $this->actingAs($this->user)
            ->getJson(route('tiendanube.precios.lotes.items', [$lote->json('lote_id'), 'per_page' => 50]))
            ->assertOk();

        $ids = collect($items->json('data'))->pluck('variante_id')->sort()->values()->all();
        $this->assertSame([1001, 1002, 1003, 1004, 1005], $ids);
        $this->assertCount(5, array_unique($ids));
    }

    public function test_recalcular_no_acumula_aumentos(): void
    {
        $this->crearVariante(1000, 100, 'SKU-A', '100.00', null, '40.00');
        $lote = $this->crearLoteSimulado([1000]);

        $items = $this->actingAs($this->user)
            ->getJson(route('tiendanube.precios.lotes.items', $lote['lote_id']))
            ->assertOk();
        $propuesto = $items->json('data.0.campos.normal.propuesto');

        $this->actingAs($this->user)
            ->postJson(route('tiendanube.precios.lotes.simular', $lote['lote_id']), [])
            ->assertOk();

        $otra = $this->actingAs($this->user)
            ->getJson(route('tiendanube.precios.lotes.items', $lote['lote_id']))
            ->assertOk();

        $this->assertSame($propuesto, $otra->json('data.0.campos.normal.propuesto'));
        $this->assertSame(1, $otra->json('revision_numero') ?? $lote['revision']['numero']);
        $variante = TiendanubeProductoVariante::find(1000);
        $this->assertEquals(100.0, (float) $variante?->getRawOriginal('price'));
    }

    public function test_cambio_de_regla_exige_nueva_revision(): void
    {
        $this->crearVariante(1000, 100, 'SKU-A', '100.00', null, '40.00');
        $lote = $this->crearLoteSimulado([1000]);

        $next = $this->actingAs($this->user)
            ->postJson(route('tiendanube.precios.lotes.simular', $lote['lote_id']), [
                'definicion' => TiendanubePrecioMotorFixtures::regla(
                    'temp-2',
                    'precio_normal_actual',
                    'aumentar_porcentaje',
                    'normal',
                    '20'
                ),
            ])
            ->assertOk();

        $this->assertSame(2, $next->json('revision.numero'));
        $this->assertSame('simulado', $next->json('estado'));
    }

    public function test_aprobar_revision_desactualizada_devuelve_conflicto(): void
    {
        $this->crearVariante(1000, 100, 'SKU-A', '100.00', null, '40.00');
        $lote = $this->crearLoteSimulado([1000]);

        $this->actingAs($this->user)
            ->postJson(route('tiendanube.precios.lotes.aprobar', $lote['lote_id']), [
                'checksum' => str_repeat('a', 64),
            ])
            ->assertStatus(409)
            ->assertJsonPath('codigo', 'conflicto_checksum');
    }

    public function test_exclusion_conserva_evidencia_y_no_es_publicable_en_contrato(): void
    {
        $this->crearVariante(1000, 100, 'SKU-A', '100.00', null, '40.00');
        $this->crearVariante(1001, 101, 'SKU-B', '80.00', null, '30.00');
        $lote = $this->crearLoteSimulado([1000, 1001]);

        $items = $this->actingAs($this->user)
            ->getJson(route('tiendanube.precios.lotes.items', $lote['lote_id']))
            ->assertOk();
        $itemId = $items->json('data.0.id');
        $varianteExcluida = $items->json('data.0.variante_id');

        $this->actingAs($this->user)
            ->patchJson(route('tiendanube.precios.lotes.items.update', [$lote['lote_id'], $itemId]), [
                'accion' => 'excluir',
                'motivo' => 'Fuera de alcance',
            ])
            ->assertOk()
            ->assertJsonPath('item.excluido', true);

        $this->assertDatabaseHas('tiendanube_precio_lote_items', [
            'id' => $itemId,
            'excluido' => 1,
        ]);

        $fresh = $this->actingAs($this->user)
            ->getJson(route('tiendanube.precios.lotes.show', $lote['lote_id']))
            ->assertOk();

        $this->actingAs($this->user)
            ->postJson(route('tiendanube.precios.lotes.aprobar', $lote['lote_id']), [
                'checksum' => $fresh->json('revision.checksum'),
            ])
            ->assertOk();

        $contrato = $this->actingAs($this->user)
            ->getJson(route('tiendanube.precios.lotes.revision_aprobada', $lote['lote_id']))
            ->assertOk();

        $fila = collect($contrato->json('items'))->firstWhere('variante_id', $varianteExcluida);
        $this->assertTrue($fila['excluido']);
        $this->assertFalse($fila['publicable']);
        $this->assertSame('Fuera de alcance', $fila['exclusion_motivo']);
    }

    public function test_fuente_faltante_no_produce_precio_publicable(): void
    {
        $this->crearVariante(1000, 100, 'SKU-A', '100.00', null, null);

        $selection = $this->actingAs($this->user)
            ->postJson(route('tiendanube.precios.selecciones.store'), [
                'modo' => 'pagina',
                'variante_ids' => [1000],
            ])
            ->assertCreated();

        $lote = $this->actingAs($this->user)
            ->postJson(route('tiendanube.precios.lotes.store'), [
                'selection_id' => $selection->json('selection_id'),
                'selection_version' => $selection->json('version'),
                'definicion' => TiendanubePrecioMotorFixtures::regla(
                    'temp-costo',
                    'costo_remoto_actual',
                    'aumentar_porcentaje',
                    'normal',
                    '10'
                ),
            ])
            ->assertCreated();

        $items = $this->actingAs($this->user)
            ->getJson(route('tiendanube.precios.lotes.items', $lote->json('lote_id')))
            ->assertOk();

        $this->assertFalse($items->json('data.0.publicable'));
        $this->assertNotSame('con_cambio', $items->json('data.0.estado_fila'));
        $this->assertSame('simulado', $lote->json('estado'));
        $this->assertFalse($lote->json('revision.resumen.puede_aprobar'));
    }

    public function test_snapshot_aprobado_se_lee_sin_http_remoto_y_stock_no_cambia_huella(): void
    {
        Http::fake();
        $this->crearVariante(1000, 100, 'SKU-A', '100.00', null, '40.00');
        $lote = $this->crearLoteSimulado([1000]);
        $huella = $lote['revision']['checksum'];

        $this->actingAs($this->user)
            ->postJson(route('tiendanube.precios.lotes.aprobar', $lote['lote_id']), [
                'checksum' => $lote['revision']['checksum'],
            ])
            ->assertOk();

        TiendanubeProductoVariante::query()->where('id', 1000)->update(['stock' => 99]);

        $modelo = TiendanubePrecioLote::query()->find($lote['lote_id']);
        $revision = $modelo->revisionActual();
        $this->assertSame($huella, $revision->checksum);

        $contrato = app(TiendanubePrecioLoteAprobacionService::class)
            ->obtenerRevisionAprobada($lote['lote_id'], 8004291, (int) $this->user->id);

        $this->assertSame($lote['lote_id'], $contrato['lote_id']);
        $this->assertNotEmpty($contrato['items']);
        Http::assertNothingSent();
        $this->assertSame(1, TiendanubePrecioLoteEvento::query()->where('tipo', TiendanubePrecioLoteEvento::TIPO_REVISION_APROBADA)->count());

        $this->actingAs($this->user)
            ->postJson(route('tiendanube.precios.lotes.aprobar', $lote['lote_id']), [
                'checksum' => $lote['revision']['checksum'],
            ])
            ->assertOk();
        $this->assertSame(1, TiendanubePrecioLoteEvento::query()->where('tipo', TiendanubePrecioLoteEvento::TIPO_REVISION_APROBADA)->count());
    }

    public function test_job_de_simulacion_persiste_progreso(): void
    {
        Queue::fake();
        config(['tiendanube.precio_lote_sync_max' => 0]);
        $this->crearVariante(1000, 100, 'SKU-A', '100.00', null, '40.00');

        $selection = $this->actingAs($this->user)
            ->postJson(route('tiendanube.precios.selecciones.store'), [
                'modo' => 'pagina',
                'variante_ids' => [1000],
            ])
            ->assertCreated();

        $lote = $this->actingAs($this->user)
            ->postJson(route('tiendanube.precios.lotes.store'), [
                'selection_id' => $selection->json('selection_id'),
                'selection_version' => $selection->json('version'),
                'definicion' => $this->definicionAumento(),
            ])
            ->assertCreated();

        $this->assertSame('borrador', $lote->json('estado'));
        Queue::assertPushed(SimularPrecioLoteJob::class);

        $job = new SimularPrecioLoteJob($lote->json('lote_id'), (int) $lote->json('revision.id'), (int) $this->user->id);
        $job->handle(app(TiendanubePrecioLoteSimulacionService::class), app(TiendanubePrecioLoteService::class));

        $progreso = $this->actingAs($this->user)
            ->getJson(route('tiendanube.precios.lotes.progreso', $lote->json('lote_id')))
            ->assertOk();
        $this->assertSame('completada', $progreso->json('estado'));
        $this->assertSame(100, $progreso->json('porcentaje'));
    }

    public function test_ninguna_accion_escribe_remotamente(): void
    {
        Queue::fake();
        Http::fake();
        $this->crearVariante(1000, 100, 'SKU-A', '100.00', '90.00', '40.00');
        $lote = $this->crearLoteSimulado([1000]);
        $this->actingAs($this->user)
            ->postJson(route('tiendanube.precios.lotes.aprobar', $lote['lote_id']), [
                'checksum' => $lote['revision']['checksum'],
            ])
            ->assertOk();

        $variante = TiendanubeProductoVariante::find(1000);
        $this->assertEquals(100.0, (float) $variante?->getRawOriginal('price'));
        $this->assertEquals(90.0, (float) $variante?->getRawOriginal('promotional_price'));
        Http::assertNothingSent();
        Queue::assertNotPushed(SimularPrecioLoteJob::class);
    }

    public function test_ajuste_manual_crea_revision_nueva(): void
    {
        $this->crearVariante(1000, 100, 'SKU-A', '100.00', null, '40.00');
        $lote = $this->crearLoteSimulado([1000]);
        $items = $this->actingAs($this->user)
            ->getJson(route('tiendanube.precios.lotes.items', $lote['lote_id']))
            ->assertOk();

        $updated = $this->actingAs($this->user)
            ->patchJson(route('tiendanube.precios.lotes.items.update', [$lote['lote_id'], $items->json('data.0.id')]), [
                'accion' => 'ajustar',
                'destino' => 'normal',
                'valor' => '125.00',
                'motivo' => 'Corrección comercial',
            ])
            ->assertOk();

        $this->assertSame(2, $updated->json('lote.revision.numero'));
        $this->assertSame('125.00', $updated->json('item.campos.normal.propuesto'));
    }

    /**
     * @param  list<int>  $varianteIds
     * @return array<string, mixed>
     */
    private function crearLoteSimulado(array $varianteIds): array
    {
        $selection = $this->actingAs($this->user)
            ->postJson(route('tiendanube.precios.selecciones.store'), [
                'modo' => 'pagina',
                'variante_ids' => $varianteIds,
            ])
            ->assertCreated();

        return $this->actingAs($this->user)
            ->postJson(route('tiendanube.precios.lotes.store'), [
                'selection_id' => $selection->json('selection_id'),
                'selection_version' => $selection->json('version'),
                'definicion' => $this->definicionAumento(),
            ])
            ->assertCreated()
            ->json();
    }

    /**
     * @return array<string, mixed>
     */
    private function definicionAumento(): array
    {
        return TiendanubePrecioMotorFixtures::regla(
            'temp',
            'precio_normal_actual',
            'aumentar_porcentaje',
            'normal',
            '10'
        );
    }

    private function crearVariante(
        int $id,
        int $productoId,
        string $sku,
        string $precio,
        ?string $promo,
        ?string $costo,
    ): void {
        TiendanubeProducto::query()->create([
            'id' => $productoId,
            'name' => ['es' => 'Producto '.$productoId],
            'published' => true,
            'synced_at' => now(),
        ]);

        TiendanubeProductoVariante::query()->create([
            'id' => $id,
            'producto_id' => $productoId,
            'sku' => $sku,
            'price' => $precio,
            'promotional_price' => $promo,
            'cost' => $costo,
            'values' => ['Talla M'],
            'stock' => 5,
        ]);
    }
}
