<?php

namespace Tests\Feature\Tiendanube;

use App\Models\Tiendanube\TiendanubeCategoria;
use App\Models\Tiendanube\TiendanubeConfiguracion;
use App\Models\Tiendanube\TiendanubeProducto;
use App\Models\Tiendanube\TiendanubeSyncLog;
use App\Services\Tiendanube\TiendanubeCatalogoSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TiendanubeCatalogoSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_upsert_categoria_producto_imagen_desde_payload_api(): void
    {
        config([
            'tiendanube.api_base' => 'https://api.tiendanube.com/v1',
            'tiendanube.per_page' => 50,
            'tiendanube.user_agent' => 'Gelianv',
        ]);

        TiendanubeConfiguracion::obtener()->fill([
            'store_id' => 8004291,
            'app_id' => '37163',
            'access_token' => Crypt::encryptString('token-test'),
        ])->save();

        Http::fake(function (\Illuminate\Http\Client\Request $request) {
            $url = $request->url();

            if (str_contains($url, '/categories')) {
                if (str_contains($url, 'page=1')) {
                    return Http::response([
                        [
                            'id' => 10,
                            'name' => ['es' => 'Aromas'],
                            'handle' => ['es' => 'aromas'],
                            'description' => ['es' => 'Cat aromas'],
                            'parent' => null,
                            // Repro del TypeError: SEO como LocalizedString
                            'seo_title' => ['es' => 'SEO Aromas Localizado'],
                            'seo_description' => ['es' => 'Desc SEO Aromas Localizado'],
                        ],
                    ], 200);
                }

                return Http::response([], 200);
            }

            if (str_contains($url, '/products')) {
                if (str_contains($url, 'page=1')) {
                    return Http::response([
                        [
                            'id' => 100,
                            'name' => ['es' => 'Perfume Demo'],
                            'description' => ['es' => '<p>Demo</p>'],
                            'handle' => ['es' => 'perfume-demo'],
                            'brand' => 'Gelia',
                            'published' => true,
                            'free_shipping' => false,
                            'requires_shipping' => true,
                            'video_url' => null,
                            // Producto simple: sin atributos visibles; SEO puede venir localizado
                            'seo_title' => ['es' => 'Perfume Demo SEO'],
                            'seo_description' => ['es' => 'SEO desc'],
                            'tags' => 'demo,test',
                            'attributes' => [],
                            'canonical_url' => 'https://example.com/products/perfume-demo',
                            'categories' => [10],
                            'images' => [
                                [
                                    'id' => 500,
                                    'src' => 'https://cdn.example.com/demo.jpg',
                                    'position' => 1,
                                    'alt' => ['es' => 'Demo alt'],
                                ],
                            ],
                            // Variante virtual (producto sin opciones)
                            'variants' => [
                                [
                                    'id' => 900,
                                    'sku' => 'SKU-DEMO-1',
                                    'price' => '199.00',
                                    'promotional_price' => null,
                                    'cost' => '80.50',
                                    'stock' => '',
                                    'stock_management' => false,
                                    'values' => [],
                                    'barcode' => '7500000000000',
                                    'weight' => '0.200',
                                ],
                            ],
                        ],
                    ], 200);
                }

                return Http::response([], 200);
            }

            return Http::response(['error' => 'unexpected '.$url], 500);
        });

        $log = TiendanubeSyncLog::create([
            'tipo' => 'completo',
            'estado' => 'pendiente',
        ]);

        app(TiendanubeCatalogoSyncService::class)->sincronizar($log);

        $log->refresh();
        $this->assertSame('completado', $log->estado);
        $this->assertSame(1, $log->total_categorias);
        $this->assertSame(1, $log->total_productos);

        $cat = TiendanubeCategoria::find(10);
        $this->assertNotNull($cat);
        $this->assertSame('SEO Aromas Localizado', $cat->seo_title);
        $this->assertSame('Desc SEO Aromas Localizado', $cat->seo_description);
        $this->assertSame('Aromas', $cat->nombreVisible());

        $prod = TiendanubeProducto::with(['imagenes', 'variantes', 'categorias'])->find(100);
        $this->assertNotNull($prod);
        $this->assertSame('Perfume Demo', $prod->nombreVisible());
        $this->assertSame('Perfume Demo SEO', $prod->seo_title);
        $this->assertSame('SEO desc', $prod->seo_description);
        $this->assertTrue($prod->published);
        $this->assertTrue($prod->requires_shipping);
        $this->assertFalse($prod->free_shipping);
        $this->assertCount(1, $prod->imagenes);
        $this->assertSame('https://cdn.example.com/demo.jpg', $prod->imagenes->first()->src);
        $this->assertSame('Demo alt', $prod->imagenes->first()->alt);
        $this->assertCount(1, $prod->variantes);

        $variante = $prod->variantes->first();
        $this->assertSame('SKU-DEMO-1', $variante->sku);
        $this->assertSame(199.0, (float) $variante->price);
        $this->assertSame(80.5, (float) $variante->cost);
        $this->assertNull($variante->stock); // "" = ilimitado
        $this->assertTrue($prod->categorias->contains('id', 10));

        $this->assertDatabaseHas('tiendanube_producto_imagenes', [
            'id' => 500,
            'producto_id' => 100,
        ]);
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
}
