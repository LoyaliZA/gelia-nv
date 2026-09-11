<?php

namespace Tests\Feature\Tiendanube;

use App\Models\Tiendanube\TiendanubeCategoria;
use App\Models\Tiendanube\TiendanubeConfiguracion;
use App\Models\Tiendanube\TiendanubeProducto;
use App\Models\Tiendanube\TiendanubeProductoImagen;
use App\Models\Tiendanube\TiendanubeProductoVariante;
use App\Models\Tiendanube\TiendanubeSyncLog;
use App\Models\Tiendanube\TiendanubeUbicacion;
use App\Models\Tiendanube\TiendanubeVarianteNivel;
use App\Services\Tiendanube\TiendanubeCatalogoSyncService;
use Illuminate\Support\Facades\Http;
use Tests\Support\FakesTiendanubeApi;
use Tests\Support\RefreshDatabaseSafe;
use Tests\Support\TiendanubeCatalogoFixtures;
use Tests\TestCase;

class TiendanubeCatalogoSyncTest extends TestCase
{
    use FakesTiendanubeApi;
    use RefreshDatabaseSafe;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureTiendanubeHttp([
            'tiendanube.api_version' => '2025-03',
            'tiendanube.sync_prune_enabled' => false,
        ]);
        $this->seedTiendanubeCredentials();
    }

    public function test_upsert_categoria_producto_imagen_desde_payload_api(): void
    {
        $cats = TiendanubeCatalogoFixtures::get('cat-arbol');
        $prod = TiendanubeCatalogoFixtures::get('prod-simple');

        $this->fakeCatalogo([$cats[0]], [$prod]);

        $log = $this->runSync();

        $this->assertSame('completado', $log->estado);
        $this->assertSame(1, $log->total_categorias);
        $this->assertSame(1, $log->total_productos);

        $cat = TiendanubeCategoria::find(10);
        $this->assertNotNull($cat);
        $this->assertSame('SEO Aromas', $cat->seo_title);
        $this->assertSame('Aromas', $cat->nombreVisible());

        $model = TiendanubeProducto::with(['imagenes', 'variantes', 'categorias'])->find(100);
        $this->assertNotNull($model);
        $this->assertSame('Perfume Demo', $model->nombreVisible());
        $this->assertSame('Perfume Demo SEO', $model->seo_title);
        $this->assertTrue($model->published);
        $this->assertTrue($model->requires_shipping);
        $this->assertFalse($model->free_shipping);
        $this->assertCount(1, $model->imagenes);
        $this->assertSame('https://cdn.example.test/demo.jpg', $model->imagenes->first()->src);
        $this->assertSame('Demo alt', $model->imagenes->first()->alt);
        $this->assertCount(1, $model->variantes);

        $variante = $model->variantes->first();
        $this->assertSame('SKU-DEMO-1', $variante->sku);
        $this->assertSame(199.0, (float) $variante->price);
        $this->assertSame(80.5, (float) $variante->cost);
        $this->assertNull($variante->stock);
        $this->assertTrue($model->categorias->contains('id', 10));
    }

    public function test_sync_elimina_productos_y_categorias_huerfanos_si_prune_habilitado(): void
    {
        config(['tiendanube.sync_prune_enabled' => true]);

        TiendanubeCategoria::query()->create(['id' => 888, 'name' => ['es' => 'Huérfana']]);
        TiendanubeProducto::query()->create(['id' => 999, 'name' => ['es' => 'Huérfano'], 'published' => false]);
        TiendanubeCategoria::query()->create(['id' => 10, 'name' => ['es' => 'Se queda']]);
        TiendanubeProducto::query()->create(['id' => 100, 'name' => ['es' => 'Se queda'], 'published' => true]);

        $this->fakeCatalogo(
            [['id' => 10, 'name' => ['es' => 'Aromas'], 'handle' => ['es' => 'aromas'], 'parent' => null]],
            [[
                'id' => 100,
                'name' => ['es' => 'Perfume Demo'],
                'handle' => ['es' => 'perfume-demo'],
                'published' => true,
                'images' => [],
                'variants' => [['id' => 900, 'sku' => 'SKU-1', 'price' => '10.00', 'stock' => 1]],
                'categories' => [10],
            ]]
        );

        $log = $this->runSync();

        $this->assertSame('completado', $log->estado);
        $this->assertSame(1, (int) $log->eliminados_productos);
        $this->assertSame(1, (int) $log->eliminados_categorias);
        $this->assertDatabaseMissing('tiendanube_productos', ['id' => 999]);
        $this->assertDatabaseMissing('tiendanube_categorias', ['id' => 888]);
        $this->assertDatabaseHas('tiendanube_productos', ['id' => 100]);
        $this->assertDatabaseHas('tiendanube_categorias', ['id' => 10]);
    }

    public function test_sync_fixtures_multi_i18n_nulls_y_arbol(): void
    {
        $cats = TiendanubeCatalogoFixtures::get('cat-arbol');
        $this->fakeCatalogo($cats, [
            TiendanubeCatalogoFixtures::get('prod-multi'),
            TiendanubeCatalogoFixtures::get('prod-i18n'),
            TiendanubeCatalogoFixtures::get('prod-nulls'),
        ]);

        $log = $this->runSync();
        $this->assertSame('completado', $log->estado);
        $this->assertSame(2, $log->total_categorias);
        $this->assertSame(3, $log->total_productos);

        $child = TiendanubeCategoria::find(20);
        $this->assertSame(10, $child->parent_id);

        $multi = TiendanubeProducto::with(['imagenes', 'variantes', 'categorias'])->find(200);
        $this->assertCount(3, $multi->imagenes);
        $this->assertCount(3, $multi->variantes);
        $this->assertTrue($multi->categorias->contains('id', 10));
        $this->assertTrue($multi->categorias->contains('id', 20));
        $this->assertSame(99.9, (float) $multi->variantes->firstWhere('id', 901)->promotional_price);

        $i18n = TiendanubeProducto::find(300);
        $this->assertSame('Aroma noche', $i18n->nombreVisible());
        $this->assertSame(['es' => 'Aroma noche', 'en' => 'Night scent'], $i18n->name);

        $nulls = TiendanubeProducto::with('variantes')->find(400);
        $this->assertFalse($nulls->published);
        $this->assertNull($nulls->seo_title);
        $this->assertNull($nulls->tags);
        $this->assertNull($nulls->video_url);
        $this->assertNull($nulls->canonical_url);
        $this->assertNull($nulls->variantes->first()->stock);
    }

    public function test_sin_clave_images_conserva_imagenes_locales(): void
    {
        TiendanubeProducto::query()->create(['id' => 300, 'name' => ['es' => 'Viejo'], 'published' => true]);
        TiendanubeProductoImagen::query()->create([
            'id' => 777,
            'producto_id' => 300,
            'src' => 'https://cdn.example.test/keep.jpg',
            'position' => 1,
        ]);

        $this->fakeCatalogo([], [TiendanubeCatalogoFixtures::get('prod-i18n')]);

        $this->runSync();

        $this->assertDatabaseHas('tiendanube_producto_imagenes', [
            'id' => 777,
            'producto_id' => 300,
        ]);
        $this->assertSame('Aroma noche', TiendanubeProducto::find(300)->nombreVisible());
    }

    public function test_visibility_hidden_marca_no_publicado(): void
    {
        $service = app(TiendanubeCatalogoSyncService::class);
        $this->assertFalse($service->resolvePublished(['visibility' => 'hidden', 'published' => true]));
        $this->assertTrue($service->resolvePublished(['visibility' => 'unlisted']));
        $this->assertTrue($service->resolvePublished(['published' => true]));
        $this->assertFalse($service->resolvePublished([]));
    }

    public function test_sync_niveles_a3_b7_total_10_conserva_id_opaco(): void
    {
        $this->fakeCatalogo(
            [TiendanubeCatalogoFixtures::get('cat-arbol')[0]],
            [TiendanubeCatalogoFixtures::get('prod-multi-inventory')],
            TiendanubeCatalogoFixtures::get('loc-dos')
        );

        $log = $this->runSync();
        $this->assertSame('completado', $log->estado);

        $this->assertDatabaseHas('tiendanube_ubicaciones', [
            'id' => '01GQ2ZHK064BQRHGDB7CCV0Y6N',
        ]);
        $this->assertSame(
            '01GQ2ZHK064BQRHGDB7CCV0Y6N',
            TiendanubeUbicacion::query()->find('01GQ2ZHK064BQRHGDB7CCV0Y6N')?->id
        );

        $variante = TiendanubeProductoVariante::with('nivelesInventario')->find(950);
        $this->assertNotNull($variante);
        $this->assertSame(10, $variante->stock);
        $this->assertSame(10, $variante->stockTotal());
        $this->assertCount(2, $variante->nivelesInventario);
        $this->assertSame(3, (int) $variante->nivelesInventario->firstWhere('ubicacion_id', '01GQ2ZHK064BQRHGDB7CCV0Y6N')->stock);
        $this->assertSame(7, (int) $variante->nivelesInventario->firstWhere('ubicacion_id', '01GQ2ZHK064BQRHGDB7DDCS4SA')->stock);
        $this->assertTrue((bool) TiendanubeConfiguracion::obtener()->multi_inventario_activo);
    }

    public function test_snapshot_sin_inventory_levels_no_borra_ni_pone_cero(): void
    {
        $this->fakeCatalogo(
            [TiendanubeCatalogoFixtures::get('cat-arbol')[0]],
            [TiendanubeCatalogoFixtures::get('prod-multi-inventory')],
            TiendanubeCatalogoFixtures::get('loc-dos')
        );
        $this->runSync();
        $this->assertSame(2, TiendanubeVarianteNivel::query()->where('variante_id', 950)->count());

        $parcial = TiendanubeCatalogoFixtures::get('prod-multi-inventory');
        unset($parcial['variants'][0]['inventory_levels']);
        $parcial['variants'][0]['stock'] = 10;

        app(TiendanubeCatalogoSyncService::class)->upsertProducto($parcial);

        $this->assertSame(2, TiendanubeVarianteNivel::query()->where('variante_id', 950)->count());
        $this->assertSame(3, (int) TiendanubeVarianteNivel::query()
            ->where('variante_id', 950)
            ->where('ubicacion_id', '01GQ2ZHK064BQRHGDB7CCV0Y6N')
            ->value('stock'));
        $this->assertSame(10, TiendanubeProductoVariante::find(950)->stockTotal());
    }

    public function test_localized_to_string_acepta_string_y_array(): void
    {
        $service = app(TiendanubeCatalogoSyncService::class);

        $this->assertSame('Hola', $service->localizedToString('Hola'));
        $this->assertSame('ES', $service->localizedToString(['es' => 'ES', 'en' => 'EN']));
        $this->assertNull($service->localizedToString(null));
        $this->assertNull($service->localizedToString(''));
        $this->assertSame('SEO…', $service->truncateSeo('SEO…extra', 4));
    }

    /**
     * @param  list<array<string, mixed>>  $categorias
     * @param  list<array<string, mixed>>  $productos
     * @param  list<array<string, mixed>>  $ubicaciones
     */
    private function fakeCatalogo(array $categorias, array $productos, array $ubicaciones = []): void
    {
        Http::fake(function (\Illuminate\Http\Client\Request $request) use ($categorias, $productos, $ubicaciones) {
            $url = $request->url();
            $query = [];
            parse_str((string) (parse_url($url, PHP_URL_QUERY) ?: ''), $query);
            $page1 = (int) ($query['page'] ?? 1) <= 1;

            if (str_contains($url, '/locations')) {
                return Http::response($page1 ? $ubicaciones : [], 200);
            }
            if (str_contains($url, '/categories')) {
                return Http::response($page1 ? $categorias : [], 200);
            }
            if (str_contains($url, '/products')) {
                return Http::response($page1 ? $productos : [], 200);
            }

            return Http::response(['error' => 'unexpected '.$url], 500);
        });
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
