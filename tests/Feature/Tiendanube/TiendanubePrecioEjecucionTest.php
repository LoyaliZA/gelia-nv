<?php

namespace Tests\Feature\Tiendanube;

use App\Jobs\Tiendanube\Precios\ProcesarPrecioEjecucionJob;
use App\Models\Tiendanube\TiendanubeConfiguracion;
use App\Models\Tiendanube\TiendanubePrecioEjecucion;
use App\Models\Tiendanube\TiendanubePrecioEjecucionItem;
use App\Models\Tiendanube\TiendanubeProducto;
use App\Models\Tiendanube\TiendanubeProductoVariante;
use App\Models\User;
use App\Services\Tiendanube\Precios\Aplicacion\TiendanubePrecioEjecucionService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\RefreshDatabaseSafe;
use Tests\Support\TiendanubePrecioMotorFixtures;
use Tests\TestCase;

class TiendanubePrecioEjecucionTest extends TestCase
{
    use RefreshDatabaseSafe;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        config([
            'tiendanube.api_base' => null,
            'tiendanube.api_host' => 'https://api.tiendanube.com',
            'tiendanube.api_version' => '2025-03',
            'tiendanube.retry_sleep_ms' => 0,
            'tiendanube.precios_aplicacion_habilitada' => true,
            'tiendanube.precio_lote_sync_max' => 200,
        ]);

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
            'tiendanube.precios.aplicar',
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
            'tiendanube.precios.aplicar',
        ]);

        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    public function test_aplicar_dos_veces_no_duplica_ejecucion(): void
    {
        Queue::fake();
        Http::fake();
        $this->crearVariante(1000, 100, '100.00');
        $lote = $this->aprobarLote([1000]);

        $primero = $this->actingAs($this->user)
            ->postJson(route('tiendanube.precios.lotes.aplicar', $lote['lote_id']), [
                'checksum' => $lote['revision']['checksum'],
            ])
            ->assertCreated();

        $segundo = $this->actingAs($this->user)
            ->postJson(route('tiendanube.precios.lotes.aplicar', $lote['lote_id']), [
                'checksum' => $lote['revision']['checksum'],
            ])
            ->assertSuccessful();

        $this->assertSame($primero->json('id'), $segundo->json('id'));
        $this->assertSame(1, TiendanubePrecioEjecucion::query()->count());
        Queue::assertPushed(ProcesarPrecioEjecucionJob::class, 1);
        Http::assertNothingSent();
    }

    public function test_generacion_cambiada_no_escribe(): void
    {
        Queue::fake();
        Http::fake();
        $this->crearVariante(1000, 100, '100.00');
        $lote = $this->aprobarLote([1000]);
        TiendanubeConfiguracion::obtener()->increment('config_generation');

        $this->actingAs($this->user)
            ->postJson(route('tiendanube.precios.lotes.aplicar', $lote['lote_id']), [
                'checksum' => $lote['revision']['checksum'],
            ])
            ->assertStatus(409);

        Http::assertNothingSent();
        $this->assertSame(0, TiendanubePrecioEjecucion::query()->count());
    }

    public function test_variante_ajena_cero_escrituras(): void
    {
        Queue::fake();
        $this->crearVariante(1000, 100, '100.00');
        $lote = $this->aprobarLote([1000]);
        TiendanubeProductoVariante::query()->where('id', 1000)->delete();

        $puts = 0;
        Http::fake(function (Request $request) use (&$puts) {
            if ($request->method() === 'PUT') {
                $puts++;
            }

            return Http::response($this->productoRemoto(100, 1000, '100.00'), 200);
        });

        $this->actingAs($this->user)
            ->postJson(route('tiendanube.precios.lotes.aplicar', $lote['lote_id']), [
                'checksum' => $lote['revision']['checksum'],
            ])
            ->assertCreated();

        $job = new ProcesarPrecioEjecucionJob(TiendanubePrecioEjecucion::query()->value('id'));
        $job->handle(app(TiendanubePrecioEjecucionService::class));

        $this->assertSame(0, $puts);
        $item = TiendanubePrecioEjecucionItem::query()->first();
        $this->assertSame(TiendanubePrecioEjecucionItem::ESTADO_FALLIDO, $item->estado);
    }

    public function test_cambio_remoto_genera_conflicto_sin_put(): void
    {
        Queue::fake();
        $this->crearVariante(1000, 100, '100.00');
        $lote = $this->aprobarLote([1000]);
        $puts = [];
        Http::fake(function (Request $request) use (&$puts) {
            if ($request->method() === 'PUT') {
                $puts[] = $request->data();
            }

            return Http::response($this->productoRemoto(100, 1000, '250.00'), 200);
        });

        $this->aplicarYProcesar($lote);
        $item = TiendanubePrecioEjecucionItem::query()->first();
        $this->assertSame(TiendanubePrecioEjecucionItem::ESTADO_CONFLICTO, $item->estado);
        $this->assertSame([], $puts);
        $ejecucion = TiendanubePrecioEjecucion::query()->first();
        $this->assertSame(TiendanubePrecioEjecucion::ESTADO_PARCIAL, $ejecucion->estado);
        $this->assertFalse((bool) $this->actingAs($this->user)
            ->getJson(route('tiendanube.precios.lotes.ejecucion', $lote['lote_id']))
            ->json('exito_global'));
    }

    public function test_timeout_con_resultado_aplicado_se_confirma_por_relectura(): void
    {
        Queue::fake();
        $this->crearVariante(1000, 100, '100.00');
        $lote = $this->aprobarLote([1000]);
        $puts = 0;
        $gets = 0;
        Http::fake(function (Request $request) use (&$puts, &$gets) {
            if ($request->method() === 'PUT') {
                $puts++;

                return Http::response(['error' => 'gateway'], 504);
            }

            $gets++;

            return Http::response($this->productoRemoto(100, 1000, $gets === 1 ? '100.00' : '110.00'), 200);
        });

        $this->aplicarYProcesar($lote);
        $item = TiendanubePrecioEjecucionItem::query()->first();
        $this->assertSame(1, $puts);
        $this->assertSame(TiendanubePrecioEjecucionItem::ESTADO_CONFIRMADO, $item->estado);
        $this->assertSame('110.00', $item->valor_confirmado['normal']);
    }

    public function test_cancelacion_conserva_confirmados_y_detiene_pendientes(): void
    {
        Queue::fake();
        $this->crearVariante(1000, 100, '100.00');
        $this->crearVariante(1001, 101, '200.00');
        $lote = $this->aprobarLote([1000, 1001]);

        $res = $this->actingAs($this->user)
            ->postJson(route('tiendanube.precios.lotes.aplicar', $lote['lote_id']), [
                'checksum' => $lote['revision']['checksum'],
            ])
            ->assertCreated();

        $ejecucionId = $res->json('id');
        $primero = TiendanubePrecioEjecucionItem::query()->where('variante_id', 1000)->first();
        $primero->update(['estado' => TiendanubePrecioEjecucionItem::ESTADO_CONFIRMADO]);

        $this->actingAs($this->user)
            ->postJson(route('tiendanube.precios.ejecuciones.cancelar', $ejecucionId))
            ->assertOk();

        $this->assertSame(
            TiendanubePrecioEjecucionItem::ESTADO_CONFIRMADO,
            TiendanubePrecioEjecucionItem::query()->where('variante_id', 1000)->value('estado')
        );
        $this->assertSame(
            TiendanubePrecioEjecucionItem::ESTADO_CANCELADO,
            TiendanubePrecioEjecucionItem::query()->where('variante_id', 1001)->value('estado')
        );

        Http::fake();
        $job = new ProcesarPrecioEjecucionJob($ejecucionId);
        $job->handle(app(TiendanubePrecioEjecucionService::class));
        Http::assertNothingSent();
    }

    public function test_payload_publicado_coincide_con_snapshot_sin_stock(): void
    {
        Queue::fake();
        $this->crearVariante(1000, 100, '100.00');
        $lote = $this->aprobarLote([1000]);
        $puts = [];
        Http::fake(function (Request $request) use (&$puts) {
            if ($request->method() === 'PUT') {
                $puts[] = $request->data();

                return Http::response(['id' => 1000, 'price' => '110.00'], 200);
            }

            return Http::response($this->productoRemoto(100, 1000, $puts === [] ? '100.00' : '110.00'), 200);
        });

        $this->aplicarYProcesar($lote);
        $this->assertCount(1, $puts);
        $this->assertSame(['price' => '110.00'], $puts[0]);
        $this->assertArrayNotHasKey('stock', $puts[0]);
        $this->assertArrayNotHasKey('sku', $puts[0]);
        $this->assertArrayNotHasKey('name', $puts[0]);
        $this->assertArrayNotHasKey('categories', $puts[0]);
        $item = TiendanubePrecioEjecucionItem::query()->first();
        $this->assertSame(TiendanubePrecioEjecucionItem::ESTADO_CONFIRMADO, $item->estado);
        $this->assertSame('110.00', $item->valor_confirmado['normal']);
        $this->assertEquals(110.0, (float) TiendanubeProductoVariante::find(1000)?->getRawOriginal('price'));
        $this->assertEquals(5, (int) TiendanubeProductoVariante::find(1000)?->stock);
    }

    /**
     * @param  list<int>  $varianteIds
     * @return array<string, mixed>
     */
    private function aprobarLote(array $varianteIds): array
    {
        $selection = $this->actingAs($this->user)
            ->postJson(route('tiendanube.precios.selecciones.store'), [
                'modo' => 'pagina',
                'variante_ids' => $varianteIds,
            ])
            ->assertCreated();

        $lote = $this->actingAs($this->user)
            ->postJson(route('tiendanube.precios.lotes.store'), [
                'selection_id' => $selection->json('selection_id'),
                'selection_version' => $selection->json('version'),
                'definicion' => TiendanubePrecioMotorFixtures::regla(
                    'temp',
                    'precio_normal_actual',
                    'aumentar_porcentaje',
                    'normal',
                    '10'
                ),
            ])
            ->assertCreated()
            ->json();

        $this->actingAs($this->user)
            ->postJson(route('tiendanube.precios.lotes.aprobar', $lote['lote_id']), [
                'checksum' => $lote['revision']['checksum'],
            ])
            ->assertOk();

        return $lote;
    }

    /**
     * @param  array<string, mixed>  $lote
     */
    private function aplicarYProcesar(array $lote): void
    {
        $res = $this->actingAs($this->user)
            ->postJson(route('tiendanube.precios.lotes.aplicar', $lote['lote_id']), [
                'checksum' => $lote['revision']['checksum'],
            ])
            ->assertCreated();

        $job = new ProcesarPrecioEjecucionJob($res->json('id'));
        $job->handle(app(TiendanubePrecioEjecucionService::class));
    }

    /**
     * @return array<string, mixed>
     */
    private function productoRemoto(int $productoId, int $varianteId, string $precio): array
    {
        return [
            'id' => $productoId,
            'name' => ['es' => 'Producto '.$productoId],
            'published' => true,
            'variants' => [[
                'id' => $varianteId,
                'sku' => 'SKU-'.$varianteId,
                'price' => $precio,
                'promotional_price' => null,
                'cost' => '40.00',
                'stock' => 5,
                'stock_management' => true,
            ]],
        ];
    }

    private function crearVariante(int $id, int $productoId, string $precio): void
    {
        TiendanubeProducto::query()->create([
            'id' => $productoId,
            'name' => ['es' => 'Producto '.$productoId],
            'published' => true,
            'synced_at' => now(),
        ]);
        TiendanubeProductoVariante::query()->create([
            'id' => $id,
            'producto_id' => $productoId,
            'sku' => 'SKU-'.$id,
            'price' => $precio,
            'cost' => '40.00',
            'values' => ['Talla M'],
            'stock' => 5,
        ]);
    }
}
