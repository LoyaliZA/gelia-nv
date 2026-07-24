<?php

namespace Tests\Feature\Tiendanube;

use App\Models\Tiendanube\TiendanubeConfiguracion;
use App\Models\Tiendanube\TiendanubeProducto;
use App\Models\Tiendanube\TiendanubeProductoVariante;
use App\Models\User;
use App\Services\Tiendanube\TiendanubeProductoWriteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class TiendanubeProductoWriteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

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
    }

    public function test_crear_producto_simple_upsert_espejo(): void
    {
        Http::fake([
            'api.tiendanube.com/v1/8004291/products' => Http::response([
                'id' => 200,
                'name' => ['es' => 'Nuevo Perfume'],
                'description' => ['es' => '<p>Desc</p>'],
                'handle' => ['es' => 'nuevo-perfume'],
                'brand' => 'Gelia',
                'published' => true,
                'free_shipping' => false,
                'requires_shipping' => true,
                'seo_title' => 'SEO Nuevo',
                'seo_description' => 'SEO desc',
                'tags' => 'nuevo',
                'attributes' => [],
                'categories' => [],
                'images' => [
                    ['id' => 501, 'src' => 'https://cdn.example.com/nuevo.jpg', 'position' => 1, 'alt' => null],
                ],
                'variants' => [
                    [
                        'id' => 901,
                        'sku' => 'SKU-NEW',
                        'price' => '150.00',
                        'promotional_price' => null,
                        'cost' => '50.00',
                        'stock' => 3,
                        'stock_management' => true,
                        'values' => [],
                    ],
                ],
            ], 201),
        ]);

        $producto = app(TiendanubeProductoWriteService::class)->crear([
            'name' => 'Nuevo Perfume',
            'description' => '<p>Desc</p>',
            'brand' => 'Gelia',
            'published' => true,
            'sku' => 'SKU-NEW',
            'price' => 150,
            'cost' => 50,
            'stock' => 3,
            'image_urls' => ['https://cdn.example.com/nuevo.jpg'],
        ]);

        $this->assertSame(200, $producto->id);
        $this->assertSame('Nuevo Perfume', $producto->nombreVisible());
        $this->assertDatabaseHas('tiendanube_producto_variantes', [
            'id' => 901,
            'producto_id' => 200,
            'sku' => 'SKU-NEW',
        ]);
        $this->assertDatabaseHas('tiendanube_producto_imagenes', [
            'id' => 501,
            'producto_id' => 200,
        ]);
    }

    public function test_actualizar_producto_y_variante(): void
    {
        TiendanubeProducto::create([
            'id' => 100,
            'name' => ['es' => 'Viejo'],
            'published' => true,
            'seo_title' => 'Viejo SEO',
        ]);
        TiendanubeProductoVariante::create([
            'id' => 900,
            'producto_id' => 100,
            'sku' => 'OLD-SKU',
            'price' => 100,
            'stock' => 1,
            'stock_management' => true,
        ]);

        Http::fake(function (\Illuminate\Http\Client\Request $request) {
            $url = $request->url();
            $method = $request->method();

            if ($method === 'PUT' && str_contains($url, '/products/100/variants/900')) {
                return Http::response(['id' => 900, 'sku' => 'NEW-SKU', 'price' => '250.00'], 200);
            }
            if ($method === 'PUT' && str_ends_with(parse_url($url, PHP_URL_PATH) ?? '', '/products/100')) {
                return Http::response(['id' => 100], 200);
            }
            if ($method === 'GET' && str_contains($url, '/products/100')) {
                return Http::response([
                    'id' => 100,
                    'name' => ['es' => 'Actualizado'],
                    'description' => ['es' => ''],
                    'handle' => ['es' => 'actualizado'],
                    'brand' => 'Gelia',
                    'published' => false,
                    'seo_title' => 'SEO Nuevo',
                    'seo_description' => 'Desc',
                    'tags' => null,
                    'attributes' => [],
                    'categories' => [],
                    'images' => [],
                    'variants' => [
                        [
                            'id' => 900,
                            'sku' => 'NEW-SKU',
                            'price' => '250.00',
                            'promotional_price' => null,
                            'cost' => null,
                            'stock' => 5,
                            'stock_management' => true,
                            'values' => [],
                        ],
                    ],
                ], 200);
            }

            return Http::response(['error' => $method.' '.$url], 500);
        });

        $producto = app(TiendanubeProductoWriteService::class)->actualizar(100, [
            'name' => 'Actualizado',
            'published' => false,
            'seo_title' => 'SEO Nuevo',
            'sku' => 'NEW-SKU',
            'price' => 250,
            'stock' => 5,
        ]);

        $this->assertSame('Actualizado', $producto->nombreVisible());
        $this->assertSame('SEO Nuevo', $producto->seo_title);
        $this->assertFalse($producto->published);
        $this->assertSame('NEW-SKU', $producto->fresh()->variantes()->first()->sku);
        $this->assertSame(250.0, (float) $producto->fresh()->variantes()->first()->price);
    }

    public function test_agregar_imagen_por_url(): void
    {
        TiendanubeProducto::create([
            'id' => 100,
            'name' => ['es' => 'Prod'],
            'published' => true,
        ]);

        Http::fake([
            'api.tiendanube.com/v1/8004291/products/100/images' => Http::response([
                'id' => 777,
                'src' => 'https://cdn.tiendanube.com/final.jpg',
                'position' => 1,
                'product_id' => 100,
                'alt' => null,
            ], 201),
        ]);

        $imagen = app(TiendanubeProductoWriteService::class)->agregarImagen(
            100,
            'https://cdn.example.com/origen.jpg'
        );

        $this->assertSame(777, $imagen->id);
        $this->assertSame('https://cdn.tiendanube.com/final.jpg', $imagen->src);
        $this->assertDatabaseHas('tiendanube_producto_imagenes', [
            'id' => 777,
            'producto_id' => 100,
        ]);
    }

    public function test_endpoint_crear_requiere_permiso(): void
    {
        Permission::findOrCreate('tiendanube.ver', 'web');
        Permission::findOrCreate('tiendanube.productos.editar', 'web');
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('tiendanube.productos.store'), ['name' => 'X'])
            ->assertForbidden();

        $user->givePermissionTo(['tiendanube.ver', 'tiendanube.productos.editar']);

        Http::fake([
            'api.tiendanube.com/v1/8004291/products' => Http::response([
                'id' => 301,
                'name' => ['es' => 'X'],
                'published' => true,
                'attributes' => [],
                'categories' => [],
                'images' => [],
                'variants' => [
                    ['id' => 1, 'sku' => null, 'price' => null, 'stock' => null, 'stock_management' => false, 'values' => []],
                ],
            ], 201),
        ]);

        $this->actingAs($user)
            ->postJson(route('tiendanube.productos.store'), ['name' => 'X'])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('producto_id', 301);
    }
}
