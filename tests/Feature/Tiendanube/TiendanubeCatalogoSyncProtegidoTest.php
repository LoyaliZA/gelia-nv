<?php

namespace Tests\Feature\Tiendanube;

use App\Models\Tiendanube\TiendanubeCategoria;
use App\Models\Tiendanube\TiendanubeProducto;
use App\Models\Tiendanube\TiendanubeSyncLog;
use App\Services\Tiendanube\TiendanubeCatalogoSyncService;
use Illuminate\Support\Facades\Http;
use Tests\Support\FakesTiendanubeApi;
use Tests\Support\RefreshDatabaseSafe;
use Tests\TestCase;

class TiendanubeCatalogoSyncProtegidoTest extends TestCase
{
    use FakesTiendanubeApi;
    use RefreshDatabaseSafe;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureTiendanubeHttp([
            'tiendanube.api_version' => '2025-03',
            'tiendanube.per_page' => 1,
            'tiendanube.sync_prune_enabled' => true,
        ]);
        $this->seedTiendanubeCredentials();
    }

    public function test_fallo_pagina_2_no_ejecuta_prune(): void
    {
        TiendanubeProducto::query()->create(['id' => 999, 'name' => ['es' => 'Previo'], 'published' => false]);
        TiendanubeCategoria::query()->create(['id' => 888, 'name' => ['es' => 'Previa']]);

        $n = 0;
        Http::fake(function (\Illuminate\Http\Client\Request $request) use (&$n) {
            $url = $request->url();
            if (str_contains($url, '/categories')) {
                return Http::response([], 200);
            }
            if (str_contains($url, '/products')) {
                $n++;
                if ($n === 1) {
                    return Http::response([
                        [
                            'id' => 100,
                            'name' => ['es' => 'Pagina 1'],
                            'published' => true,
                            'images' => [],
                            'variants' => [['id' => 1, 'sku' => 'A', 'price' => '1.00']],
                        ],
                    ], 200, [
                        'Link' => '<'.$this->tiendanubeUrl('/products?page=2&per_page=1', '2025-03').'>; rel="next"',
                    ]);
                }

                return Http::response(['error' => 'fail'], 503);
            }

            return Http::response(['error' => 'unexpected'], 500);
        });

        $log = $this->runSync();

        $this->assertSame('parcial', $log->estado);
        $this->assertSame(0, (int) $log->eliminados_productos);
        $this->assertDatabaseHas('tiendanube_productos', ['id' => 999]);
        $this->assertDatabaseHas('tiendanube_productos', ['id' => 100]);
        $this->assertDatabaseHas('tiendanube_categorias', ['id' => 888]);
    }

    public function test_prune_deshabilitado_conserva_huerfanos_en_sync_completo(): void
    {
        config(['tiendanube.sync_prune_enabled' => false]);

        TiendanubeProducto::query()->create(['id' => 999, 'name' => ['es' => 'Huérfano'], 'published' => false]);
        TiendanubeCategoria::query()->create(['id' => 888, 'name' => ['es' => 'Huérfana']]);

        Http::fake(function (\Illuminate\Http\Client\Request $request) {
            $url = $request->url();
            $query = [];
            parse_str((string) (parse_url($url, PHP_URL_QUERY) ?: ''), $query);
            $page1 = (int) ($query['page'] ?? 1) <= 1;
            if (str_contains($url, '/categories')) {
                return Http::response($page1 ? [['id' => 10, 'name' => ['es' => 'Aromas']]] : [], 200);
            }
            if (str_contains($url, '/products')) {
                return Http::response($page1 ? [[
                    'id' => 100,
                    'name' => ['es' => 'Ok'],
                    'published' => true,
                    'images' => [],
                    'variants' => [['id' => 1, 'sku' => 'A', 'price' => '1.00']],
                ]] : [], 200);
            }

            return Http::response(['error' => 'unexpected'], 500);
        });

        $log = $this->runSync();

        $this->assertSame('completado', $log->estado);
        $this->assertSame(0, (int) $log->eliminados_productos);
        $this->assertSame(0, (int) $log->eliminados_categorias);
        $this->assertDatabaseHas('tiendanube_productos', ['id' => 999]);
        $this->assertDatabaseHas('tiendanube_categorias', ['id' => 888]);
        $this->assertDatabaseHas('tiendanube_productos', ['id' => 100]);
    }

    public function test_primera_pagina_vacia_con_prune_deshabilitado_no_borra_catalogo(): void
    {
        config(['tiendanube.sync_prune_enabled' => false]);

        TiendanubeProducto::query()->create(['id' => 1, 'name' => ['es' => 'Local'], 'published' => true]);
        TiendanubeCategoria::query()->create(['id' => 1, 'name' => ['es' => 'Local']]);

        Http::fake([
            $this->tiendanubeUrl('/categories*', '2025-03') => Http::response([], 200),
            $this->tiendanubeUrl('/products*', '2025-03') => Http::response([], 200),
        ]);

        $log = $this->runSync();

        $this->assertSame('completado', $log->estado);
        $this->assertDatabaseHas('tiendanube_productos', ['id' => 1]);
        $this->assertDatabaseHas('tiendanube_categorias', ['id' => 1]);
    }

    public function test_pagina_vacia_con_prune_habilitado_no_borra_si_ids_vistos_vacios(): void
    {
        TiendanubeProducto::query()->create(['id' => 1, 'name' => ['es' => 'Local'], 'published' => true]);

        Http::fake([
            $this->tiendanubeUrl('/categories*', '2025-03') => Http::response([], 200),
            $this->tiendanubeUrl('/products*', '2025-03') => Http::response([], 200),
        ]);

        $log = $this->runSync();

        $this->assertSame('completado', $log->estado);
        $this->assertSame(0, (int) $log->eliminados_productos);
        $this->assertDatabaseHas('tiendanube_productos', ['id' => 1]);
    }

    private function runSync(): TiendanubeSyncLog
    {
        $log = TiendanubeSyncLog::create([
            'tipo' => 'completo',
            'estado' => 'pendiente',
        ]);
        app(TiendanubeCatalogoSyncService::class)->sincronizar($log);

        return $log->refresh();
    }
}
