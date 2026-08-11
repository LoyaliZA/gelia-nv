<?php

namespace Tests\Feature\Tiendanube;

use App\Models\Tiendanube\TiendanubeConfiguracion;
use App\Models\Tiendanube\TiendanubeProducto;
use App\Models\Tiendanube\TiendanubeProductoImagen;
use App\Models\Tiendanube\TiendanubeProductoVariante;
use App\Models\User;
use App\Services\Tiendanube\TiendanubeProductoWriteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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

        config(['queue.default' => 'sync']);
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

    public function test_agregar_imagen_resuelve_src_temporal_via_get_product(): void
    {
        TiendanubeProducto::create([
            'id' => 100,
            'name' => ['es' => 'Prod'],
            'published' => true,
        ]);

        Http::fake([
            'api.tiendanube.com/v1/8004291/products/100/images' => Http::response([
                'id' => 779,
                'src' => 'https://dcdn-us.mitiendanube.com/tmp/stores/008/004/291/products/x.webp',
                'position' => 1,
                'product_id' => 100,
                'alt' => null,
            ], 201),
            'api.tiendanube.com/v1/8004291/products/100' => Http::response([
                'id' => 100,
                'name' => ['es' => 'Prod'],
                'images' => [[
                    'id' => 779,
                    'src' => 'https://dcdn-us.mitiendanube.com/stores/008/004/291/products/x-1024-1024.webp',
                    'position' => 1,
                ]],
                'variants' => [],
            ], 200),
        ]);

        $imagen = app(TiendanubeProductoWriteService::class)->agregarImagen(
            100,
            'https://cdn.example.com/origen.jpg'
        );

        $this->assertSame(779, $imagen->id);
        $this->assertSame(
            'https://dcdn-us.mitiendanube.com/stores/008/004/291/products/x-1024-1024.webp',
            $imagen->src
        );
        $this->assertStringNotContainsString('/tmp/', (string) $imagen->src);
        Http::assertSent(fn ($r) => $r->method() === 'GET' && str_ends_with(rtrim(parse_url($r->url(), PHP_URL_PATH) ?: '', '/'), '/products/100'));
    }

    public function test_agregar_imagen_tmp_sin_api_permanente_usa_heuristica(): void
    {
        TiendanubeProducto::create([
            'id' => 100,
            'name' => ['es' => 'Prod'],
            'published' => true,
        ]);

        Http::fake([
            'api.tiendanube.com/v1/8004291/products/100/images' => Http::response([
                'id' => 780,
                'src' => 'https://dcdn-us.mitiendanube.com/tmp/stores/008/004/291/products/abc123.webp',
                'position' => 1,
                'product_id' => 100,
                'alt' => null,
            ], 201),
            'api.tiendanube.com/v1/8004291/products/100' => Http::response([
                'id' => 100,
                'name' => ['es' => 'Prod'],
                'images' => [[
                    'id' => 780,
                    'src' => 'https://dcdn-us.mitiendanube.com/tmp/stores/008/004/291/products/abc123.webp',
                    'position' => 1,
                ]],
                'variants' => [],
            ], 200),
        ]);

        $imagen = app(TiendanubeProductoWriteService::class)->agregarImagen(
            100,
            'https://cdn.example.com/origen.jpg'
        );

        $this->assertSame(
            'https://dcdn-us.mitiendanube.com/stores/008/004/291/products/abc123-1024-1024.webp',
            $imagen->src
        );
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

    public function test_agregar_imagen_por_archivo_guarda_alertas(): void
    {
        TiendanubeProducto::create([
            'id' => 100,
            'name' => ['es' => 'Prod'],
            'published' => true,
        ]);

        Http::fake([
            'api.tiendanube.com/v1/8004291/products/100/images' => Http::response([
                'id' => 778,
                'src' => 'https://cdn.tiendanube.com/tiny.webp',
                'position' => 1,
                'product_id' => 100,
                'alt' => null,
            ], 201),
        ]);

        $bin = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
            true
        );
        $path = sys_get_temp_dir().'/tn_upload_'.uniqid('', true).'.png';
        file_put_contents($path, $bin);
        $file = new \Illuminate\Http\UploadedFile($path, 'tiny.png', 'image/png', null, true);

        $imagen = app(TiendanubeProductoWriteService::class)->agregarImagen(100, null, $file);

        $this->assertSame(778, $imagen->id);
        $this->assertTrue($imagen->requiere_revision);
        $this->assertTrue($imagen->alerta_pequena);
        $this->assertFalse($imagen->alerta_no_cuadrada);
        $this->assertSame(1, $imagen->width);
        $this->assertSame(1, $imagen->height);

        Http::assertSent(function ($request) {
            $data = $request->data();

            return isset($data['attachment']) && isset($data['filename']);
        });

        @unlink($path);
    }

    public function test_index_filtra_productos_con_alerta_imagenes(): void
    {
        Permission::findOrCreate('tiendanube.ver', 'web');
        $user = User::factory()->create();
        $user->givePermissionTo('tiendanube.ver');

        $ok = TiendanubeProducto::create(['id' => 10, 'name' => ['es' => 'OK'], 'published' => true]);
        $alerta = TiendanubeProducto::create(['id' => 20, 'name' => ['es' => 'Alerta'], 'published' => true]);

        \App\Models\Tiendanube\TiendanubeProductoImagen::create([
            'id' => 1,
            'producto_id' => $ok->id,
            'src' => 'https://cdn.example.com/ok.webp',
            'position' => 1,
            'width' => 1280,
            'height' => 1280,
            'requiere_revision' => false,
            'alerta_pequena' => false,
            'alerta_no_cuadrada' => false,
        ]);
        \App\Models\Tiendanube\TiendanubeProductoImagen::create([
            'id' => 2,
            'producto_id' => $alerta->id,
            'src' => 'https://cdn.example.com/bad.webp',
            'position' => 1,
            'width' => 900,
            'height' => 1600,
            'requiere_revision' => true,
            'alerta_pequena' => false,
            'alerta_no_cuadrada' => true,
        ]);

        $this->actingAs($user)
            ->get(route('tiendanube.index', ['imagenes_alerta' => 1]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Tiendanube/Index', false)
                ->where('totales.productos_alerta_imagenes', 1)
                ->where('filters.imagenes_alerta', true)
                ->has('productos.data', 1)
                ->where('productos.data.0.id', 20)
                ->where('productos.data.0.tiene_alerta_imagenes', true)
            );
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

    public function test_agregar_imagen_reemplazar_borra_anteriores(): void
    {
        TiendanubeProducto::create(['id' => 100, 'name' => ['es' => 'Prod'], 'published' => true]);
        TiendanubeProductoImagen::create([
            'id' => 10,
            'producto_id' => 100,
            'src' => 'https://cdn.example.com/old.jpg',
            'position' => 1,
        ]);
        TiendanubeProductoImagen::create([
            'id' => 11,
            'producto_id' => 100,
            'src' => 'https://cdn.example.com/old2.jpg',
            'position' => 2,
        ]);

        Http::fake([
            'api.tiendanube.com/v1/8004291/products/100/images/10' => Http::response([], 200),
            'api.tiendanube.com/v1/8004291/products/100/images/11' => Http::response([], 200),
            'api.tiendanube.com/v1/8004291/products/100/images' => Http::response([
                'id' => 99,
                'src' => 'https://cdn.tiendanube.com/nueva.webp',
                'position' => 1,
                'product_id' => 100,
                'alt' => null,
            ], 201),
        ]);

        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
            true
        );
        $path = sys_get_temp_dir().'/tn_rep_'.uniqid('', true).'.png';
        file_put_contents($path, $png);
        $file = new UploadedFile($path, 'SKU.png', 'image/png', null, true);

        $imagen = app(TiendanubeProductoWriteService::class)->agregarImagen(100, null, $file, null, true);

        $this->assertSame(99, $imagen->id);
        $this->assertSame(1, TiendanubeProductoImagen::where('producto_id', 100)->count());
        $this->assertDatabaseMissing('tiendanube_producto_imagenes', ['id' => 10]);
        $this->assertDatabaseMissing('tiendanube_producto_imagenes', ['id' => 11]);

        Http::assertSent(fn ($r) => $r->method() === 'DELETE' && str_contains($r->url(), '/images/10'));
        Http::assertSent(fn ($r) => $r->method() === 'DELETE' && str_contains($r->url(), '/images/11'));
        Http::assertSent(fn ($r) => $r->method() === 'POST' && str_ends_with(parse_url($r->url(), PHP_URL_PATH) ?: '', '/images'));

        @unlink($path);
    }

    public function test_resolver_sku_encontrado_y_no(): void
    {
        Permission::findOrCreate('tiendanube.ver', 'web');
        Permission::findOrCreate('tiendanube.productos.editar', 'web');
        $user = User::factory()->create();
        $user->givePermissionTo(['tiendanube.ver', 'tiendanube.productos.editar']);

        TiendanubeProducto::create(['id' => 50, 'name' => ['es' => 'Aroma'], 'published' => true]);
        TiendanubeProductoVariante::create([
            'id' => 5,
            'producto_id' => 50,
            'sku' => 'SKU-OK',
            'price' => 10,
        ]);
        TiendanubeProductoImagen::create([
            'id' => 7,
            'producto_id' => 50,
            'src' => 'https://cdn.example.com/a.webp',
            'position' => 1,
        ]);

        $this->actingAs($user)
            ->getJson(route('tiendanube.skus.resolver', ['sku' => 'SKU-OK']))
            ->assertOk()
            ->assertJsonPath('encontrado', true)
            ->assertJsonPath('producto_id', 50)
            ->assertJsonPath('nombre', 'Aroma')
            ->assertJsonPath('imagen_actual', 'https://cdn.example.com/a.webp');

        $this->actingAs($user)
            ->getJson(route('tiendanube.skus.resolver', ['sku' => 'NOPE']))
            ->assertOk()
            ->assertJsonPath('encontrado', false)
            ->assertJsonPath('producto_id', null);
    }

    public function test_store_imagen_reemplazar_via_endpoint(): void
    {
        Permission::findOrCreate('tiendanube.ver', 'web');
        Permission::findOrCreate('tiendanube.productos.editar', 'web');
        $user = User::factory()->create();
        $user->givePermissionTo(['tiendanube.ver', 'tiendanube.productos.editar']);

        TiendanubeProducto::create(['id' => 100, 'name' => ['es' => 'P'], 'published' => true]);
        TiendanubeProductoImagen::create([
            'id' => 1,
            'producto_id' => 100,
            'src' => 'https://cdn.example.com/old.jpg',
            'position' => 1,
        ]);

        Http::fake([
            'api.tiendanube.com/v1/8004291/products/100/images/1' => Http::response([], 200),
            'api.tiendanube.com/v1/8004291/products/100/images' => Http::response([
                'id' => 2,
                'src' => 'https://cdn.tiendanube.com/new.webp',
                'position' => 1,
                'product_id' => 100,
            ], 201),
        ]);

        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
            true
        );
        $file = UploadedFile::fake()->createWithContent('SKU.webp', $png);

        $this->actingAs($user)
            ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class)
            ->post(route('tiendanube.productos.imagenes.store', 100), [
                'file' => $file,
                'reemplazar' => '1',
            ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('success', true);

        $this->assertSame(1, TiendanubeProductoImagen::where('producto_id', 100)->count());
        $this->assertDatabaseHas('tiendanube_producto_imagenes', ['id' => 2, 'producto_id' => 100]);
        $this->assertDatabaseMissing('tiendanube_producto_imagenes', ['id' => 1]);
    }
}
