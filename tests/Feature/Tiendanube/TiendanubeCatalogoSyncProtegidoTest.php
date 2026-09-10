<?php

namespace Tests\Feature\Tiendanube;

use App\Jobs\Tiendanube\SyncTiendanubeCatalogoJob;
use App\Models\Tiendanube\TiendanubeCategoria;
use App\Models\Tiendanube\TiendanubeConfiguracion;
use App\Models\Tiendanube\TiendanubeProducto;
use App\Models\Tiendanube\TiendanubeSyncLog;
use App\Models\User;
use App\Services\Tiendanube\TiendanubeCatalogoSyncService;
use App\Services\Tiendanube\TiendanubeOperacionTiendaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class TiendanubeCatalogoSyncProtegidoTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'tiendanube.api_base' => 'https://api.tiendanube.com/v1',
            'tiendanube.per_page' => 50,
            'tiendanube.user_agent' => 'Gelianv',
            'tiendanube.app_secret' => 'test-secret',
            'tiendanube.sync_prune_enabled' => true,
            'tiendanube.sync_prune_confirm_threshold' => 10,
            'tiendanube.sync_lease_seconds' => 120,
        ]);

        foreach (['tiendanube.ver', 'tiendanube.configurar', 'tiendanube.sincronizar'] as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }

        $this->user = User::factory()->create();
        $this->user->givePermissionTo(['tiendanube.ver', 'tiendanube.configurar', 'tiendanube.sincronizar']);

        TiendanubeConfiguracion::obtener()->fill([
            'store_id' => 8004291,
            'app_id' => '37163',
            'access_token' => Crypt::encryptString('token-test'),
        ])->save();
    }

    public function test_fallo_pagina_intermedia_conserva_espejo(): void
    {
        config(['tiendanube.per_page' => 1]);

        TiendanubeProducto::query()->create(['id' => 777, 'name' => ['es' => 'No descargado'], 'published' => false]);
        TiendanubeCategoria::query()->create(['id' => 50, 'name' => ['es' => 'Extra']]);

        Http::fake(function (\Illuminate\Http\Client\Request $request) {
            $url = $request->url();
            $page = (int) ($request['page'] ?? 0);

            if (str_contains($url, '/categories')) {
                if ($page === 1) {
                    return Http::response([['id' => 10, 'name' => ['es' => 'Aromas']]], 200);
                }

                return Http::response([], 200);
            }
            if (str_contains($url, '/products')) {
                if ($page === 1) {
                    return Http::response([[
                        'id' => 100,
                        'name' => ['es' => 'P1'],
                        'published' => true,
                        'images' => [],
                        'variants' => [],
                        'categories' => [],
                    ]], 200);
                }

                return Http::response(['message' => 'fail'], 500);
            }

            return Http::response(['error' => 'unexpected'], 500);
        });

        $log = TiendanubeSyncLog::create(['tipo' => 'completo', 'estado' => 'pendiente']);

        try {
            app(TiendanubeCatalogoSyncService::class)->sincronizar($log);
            $this->fail('Se esperaba fallo de página intermedia.');
        } catch (\Throwable $e) {
            $this->assertNotSame('', $e->getMessage());
        }

        $this->assertSame('error', $log->fresh()->estado);
        $this->assertDatabaseHas('tiendanube_productos', ['id' => 777]);
        $this->assertDatabaseHas('tiendanube_productos', ['id' => 100]);
        $this->assertDatabaseHas('tiendanube_categorias', ['id' => 50]);
        $this->assertSame(0, (int) $log->fresh()->eliminados_productos);
    }

    public function test_catalogo_vacio_requiere_confirmacion(): void
    {
        config(['tiendanube.sync_prune_confirm_threshold' => 1]);
        TiendanubeProducto::query()->create(['id' => 1, 'name' => ['es' => 'A'], 'published' => false]);
        TiendanubeProducto::query()->create(['id' => 2, 'name' => ['es' => 'B'], 'published' => false]);
        TiendanubeCategoria::query()->create(['id' => 9, 'name' => ['es' => 'C']]);

        Http::fake(function (\Illuminate\Http\Client\Request $request) {
            if (preg_match('#/(products|categories)/\d+#', $request->url())) {
                return Http::response(['code' => 404], 404);
            }

            return Http::response([], 200);
        });

        $log = TiendanubeSyncLog::create([
            'tipo' => 'completo',
            'estado' => 'pendiente',
            'confirmar_depuracion_masiva' => false,
        ]);

        app(TiendanubeCatalogoSyncService::class)->sincronizar($log);

        $log->refresh();
        $this->assertSame('completado', $log->estado);
        $this->assertGreaterThan(1, (int) $log->candidatos_productos + (int) $log->candidatos_categorias);
        $this->assertDatabaseHas('tiendanube_productos', ['id' => 1]);
        $this->assertDatabaseHas('tiendanube_categorias', ['id' => 9]);
        $this->assertSame(0, (int) $log->eliminados_productos);
    }

    public function test_catalogo_vacio_con_confirmacion_depura(): void
    {
        config(['tiendanube.sync_prune_confirm_threshold' => 1]);

        TiendanubeProducto::query()->create(['id' => 1, 'name' => ['es' => 'A'], 'published' => false]);
        TiendanubeCategoria::query()->create(['id' => 9, 'name' => ['es' => 'C']]);

        Http::fake(function (\Illuminate\Http\Client\Request $request) {
            if (preg_match('#/(products|categories)/\d+#', $request->url())) {
                return Http::response(['code' => 404], 404);
            }

            return Http::response([], 200);
        });

        $log = TiendanubeSyncLog::create([
            'tipo' => 'completo',
            'estado' => 'pendiente',
            'confirmar_depuracion_masiva' => true,
        ]);

        app(TiendanubeCatalogoSyncService::class)->sincronizar($log);

        $this->assertSame('completado', $log->fresh()->estado);
        $this->assertDatabaseMissing('tiendanube_productos', ['id' => 1]);
        $this->assertDatabaseMissing('tiendanube_categorias', ['id' => 9]);
    }

    public function test_candidato_existe_remoto_se_conserva(): void
    {
        TiendanubeProducto::query()->create(['id' => 200, 'name' => ['es' => 'Durante paginacion'], 'published' => false]);

        Http::fake(function (\Illuminate\Http\Client\Request $request) {
            $url = $request->url();
            if (preg_match('#/products/200#', $url)) {
                return Http::response([
                    'id' => 200,
                    'name' => ['es' => 'Creado en medio'],
                    'published' => true,
                    'images' => [],
                    'variants' => [],
                    'categories' => [],
                ], 200);
            }
            if (str_contains($url, '/categories')) {
                return Http::response([], 200);
            }
            if (str_contains($url, '/products')) {
                return Http::response([], 200);
            }

            return Http::response(['error' => 'unexpected'], 500);
        });

        $log = TiendanubeSyncLog::create([
            'tipo' => 'completo',
            'estado' => 'pendiente',
            'confirmar_depuracion_masiva' => true,
        ]);

        app(TiendanubeCatalogoSyncService::class)->sincronizar($log);

        $prod = TiendanubeProducto::find(200);
        $this->assertNotNull($prod);
        $this->assertTrue($prod->published);
        $this->assertSame(0, (int) $log->fresh()->eliminados_productos);
    }

    public function test_timeout_en_confirmacion_conserva(): void
    {
        TiendanubeProducto::query()->create(['id' => 300, 'name' => ['es' => 'Pendiente'], 'published' => false]);

        Http::fake(function (\Illuminate\Http\Client\Request $request) {
            if (preg_match('#/products/300#', $request->url())) {
                return Http::response(['message' => 'timeout'], 503);
            }
            if (str_contains($request->url(), '/categories') || str_contains($request->url(), '/products')) {
                return Http::response([], 200);
            }

            return Http::response(['error' => 'unexpected'], 500);
        });

        $log = TiendanubeSyncLog::create([
            'tipo' => 'completo',
            'estado' => 'pendiente',
            'confirmar_depuracion_masiva' => true,
        ]);

        app(TiendanubeCatalogoSyncService::class)->sincronizar($log);

        $this->assertDatabaseHas('tiendanube_productos', ['id' => 300]);
        $this->assertSame(1, (int) $log->fresh()->pendientes_confirmacion);
        $this->assertSame(0, (int) $log->fresh()->eliminados_productos);
    }

    public function test_segundo_sync_recibe_409(): void
    {
        Queue::fake();
        Http::fake();

        $this->actingAs($this->user)
            ->postJson(route('tiendanube.sincronizar'))
            ->assertOk();

        $this->actingAs($this->user)
            ->postJson(route('tiendanube.sincronizar'))
            ->assertStatus(409)
            ->assertJsonPath('success', false);
    }

    public function test_lease_expirado_permite_nuevo_sync(): void
    {
        Queue::fake();
        Http::fake();

        $ops = app(TiendanubeOperacionTiendaService::class);
        $ops->asegurarFila(8004291);
        $ops->adquirirExclusiva(8004291, TiendanubeOperacionTiendaService::TIPO_CATALOGO_SYNC, 99, 1);

        \App\Models\Tiendanube\TiendanubeOperacionTienda::query()
            ->where('store_id', 8004291)
            ->update(['lease_expires_at' => now()->subMinute()]);

        $this->actingAs($this->user)
            ->postJson(route('tiendanube.sincronizar'))
            ->assertOk();
    }

    public function test_job_aborta_si_generacion_cambio(): void
    {
        $config = TiendanubeConfiguracion::obtener();
        $log = TiendanubeSyncLog::create([
            'tipo' => 'completo',
            'estado' => 'pendiente',
            'store_id' => 8004291,
            'config_generation' => 1,
        ]);

        $config->increment('config_generation');

        Http::fake();

        (new SyncTiendanubeCatalogoJob($log->id))->handle(
            app(TiendanubeCatalogoSyncService::class),
            app(TiendanubeOperacionTiendaService::class),
            app(\App\Services\Tiendanube\TiendanubeWebhookInboxService::class)
        );

        $this->assertSame('error', $log->fresh()->estado);
        $this->assertStringContainsString('generación', $log->fresh()->mensaje_error);
        Http::assertNothingSent();
    }
}
