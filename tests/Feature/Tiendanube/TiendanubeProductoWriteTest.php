<?php

namespace Tests\Feature\Tiendanube;

use App\Exceptions\Tiendanube\TiendanubeStockEscrituraBloqueadaException;
use App\Models\Tiendanube\TiendanubeConfiguracion;
use App\Models\Tiendanube\TiendanubeProducto;
use App\Models\Tiendanube\TiendanubeProductoImagen;
use App\Models\Tiendanube\TiendanubeProductoVariante;
use App\Models\Tiendanube\TiendanubeUbicacion;
use App\Models\Tiendanube\TiendanubeVarianteNivel;
use App\Models\User;
use App\Services\Tiendanube\TiendanubeProductoWriteService;
use Tests\Support\RefreshDatabaseSafe;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class TiendanubeProductoWriteTest extends TestCase
{
    use RefreshDatabaseSafe;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'tiendanube.api_base' => 'https://api.tiendanube.com/v1',
            'tiendanube.per_page' => 50,
            'tiendanube.user_agent' => 'Gelianv',
            'tiendanube.retry_sleep_ms' => 0,
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
        $remote = [
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
        ];

        Http::fake(function (\Illuminate\Http\Client\Request $request) use ($remote) {
            if ($request->method() === 'POST' && str_ends_with(rtrim(parse_url($request->url(), PHP_URL_PATH) ?: '', '/'), '/products')) {
                return Http::response($remote, 201);
            }
            if ($request->method() === 'GET' && str_contains($request->url(), '/products/200')) {
                return Http::response($remote, 200);
            }

            return Http::response(['error' => $request->method().' '.$request->url()], 500);
        });

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

    public function test_actualizar_stock_plano_bloqueado_si_multi_inventario(): void
    {
        TiendanubeConfiguracion::obtener()->fill([
            'multi_inventario_activo' => true,
        ])->save();

        TiendanubeProducto::create([
            'id' => 100,
            'name' => ['es' => 'Viejo'],
            'published' => true,
        ]);
        TiendanubeProductoVariante::create([
            'id' => 900,
            'producto_id' => 100,
            'sku' => 'OLD-SKU',
            'price' => 100,
            'stock' => 1,
            'stock_management' => true,
        ]);

        $this->expectException(TiendanubeStockEscrituraBloqueadaException::class);
        app(TiendanubeProductoWriteService::class)->actualizar(100, [
            'sku' => 'NEW-SKU',
            'stock' => 5,
        ]);
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
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class);
        Permission::findOrCreate('tiendanube.ver', 'web');
        Permission::findOrCreate('tiendanube.productos.editar', 'web');
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('tiendanube.productos.store'), ['name' => 'X'])
            ->assertForbidden();

        $user->givePermissionTo(['tiendanube.ver', 'tiendanube.productos.editar']);

        Http::fake(function (\Illuminate\Http\Client\Request $request) {
            $remote = [
                'id' => 301,
                'name' => ['es' => 'X'],
                'published' => true,
                'attributes' => [],
                'categories' => [],
                'images' => [],
                'variants' => [
                    ['id' => 1, 'sku' => null, 'price' => null, 'stock' => null, 'stock_management' => false, 'values' => []],
                ],
            ];
            if ($request->method() === 'POST' && str_ends_with(rtrim(parse_url($request->url(), PHP_URL_PATH) ?: '', '/'), '/products')) {
                return Http::response($remote, 201);
            }
            if ($request->method() === 'GET' && str_contains($request->url(), '/products/301')) {
                return Http::response($remote, 200);
            }

            return Http::response(['error' => $request->method().' '.$request->url()], 500);
        });

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

        Http::assertSent(fn ($r) => $r->method() === 'POST' && str_ends_with(parse_url($r->url(), PHP_URL_PATH) ?: '', '/images'));
        Http::assertSent(fn ($r) => $r->method() === 'DELETE' && str_contains($r->url(), '/images/10'));
        Http::assertSent(fn ($r) => $r->method() === 'DELETE' && str_contains($r->url(), '/images/11'));

        $orden = [];
        foreach (Http::recorded() as $pair) {
            $orden[] = $pair[0]->method();
        }
        $this->assertLessThan(
            array_search('DELETE', $orden, true),
            array_search('POST', $orden, true)
        );

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

    public function test_actualizar_precio_no_envia_stock(): void
    {
        TiendanubeProducto::create(['id' => 100, 'name' => ['es' => 'P'], 'published' => true]);
        TiendanubeProductoVariante::create([
            'id' => 900,
            'producto_id' => 100,
            'sku' => 'SKU',
            'price' => 10,
            'stock' => 4,
            'stock_management' => true,
        ]);

        Http::fake(function (\Illuminate\Http\Client\Request $request) {
            if ($request->method() === 'PUT' && str_contains($request->url(), '/variants/900')) {
                $this->assertArrayNotHasKey('stock', $request->data());
                $this->assertArrayNotHasKey('inventory_levels', $request->data());
                $this->assertSame('99', (string) ($request->data()['price'] ?? ''));

                return Http::response(['id' => 900], 200);
            }
            if ($request->method() === 'GET') {
                return Http::response([
                    'id' => 100,
                    'name' => ['es' => 'P'],
                    'published' => true,
                    'attributes' => [],
                    'categories' => [],
                    'images' => [],
                    'variants' => [[
                        'id' => 900,
                        'sku' => 'SKU',
                        'price' => '99.00',
                        'stock' => 4,
                        'stock_management' => true,
                        'values' => [],
                    ]],
                ], 200);
            }

            return Http::response(['error' => $request->url()], 500);
        });

        app(TiendanubeProductoWriteService::class)->actualizar(100, ['price' => 99]);
    }

    public function test_quitar_promocion_envia_null(): void
    {
        TiendanubeProducto::create(['id' => 100, 'name' => ['es' => 'P'], 'published' => true]);
        TiendanubeProductoVariante::create([
            'id' => 900,
            'producto_id' => 100,
            'sku' => 'SKU',
            'price' => 10,
            'promotional_price' => 8,
        ]);

        Http::fake(function (\Illuminate\Http\Client\Request $request) {
            if ($request->method() === 'PUT' && str_contains($request->url(), '/variants/900')) {
                $this->assertArrayHasKey('promotional_price', $request->data());
                $this->assertNull($request->data()['promotional_price']);

                return Http::response(['id' => 900], 200);
            }
            if ($request->method() === 'GET') {
                return Http::response([
                    'id' => 100,
                    'name' => ['es' => 'P'],
                    'published' => true,
                    'images' => [],
                    'categories' => [],
                    'variants' => [[
                        'id' => 900, 'sku' => 'SKU', 'price' => '10.00',
                        'promotional_price' => null, 'values' => [],
                    ]],
                ], 200);
            }

            return Http::response(['error' => $request->url()], 500);
        });

        app(TiendanubeProductoWriteService::class)->actualizar(100, ['promotional_price' => null]);
    }

    public function test_replace_categories_vacio_envia_array_vacio(): void
    {
        TiendanubeProducto::create(['id' => 100, 'name' => ['es' => 'P'], 'published' => true]);
        TiendanubeProductoVariante::create(['id' => 900, 'producto_id' => 100, 'sku' => 'S']);

        Http::fake(function (\Illuminate\Http\Client\Request $request) {
            if ($request->method() === 'PUT' && str_ends_with(rtrim(parse_url($request->url(), PHP_URL_PATH) ?: '', '/'), '/products/100')) {
                $this->assertSame([], $request->data()['categories']);

                return Http::response(['id' => 100], 200);
            }
            if ($request->method() === 'GET') {
                return Http::response([
                    'id' => 100, 'name' => ['es' => 'P'], 'published' => true,
                    'categories' => [], 'images' => [],
                    'variants' => [['id' => 900, 'sku' => 'S', 'values' => []]],
                ], 200);
            }

            return Http::response(['error' => $request->url()], 500);
        });

        app(TiendanubeProductoWriteService::class)->actualizar(100, [
            'categories' => [],
            'replace_categories' => true,
        ]);
    }

    public function test_stock_por_ubicacion_a_no_incluye_b(): void
    {
        $locA = '01GQ2ZHK064BQRHGDB7CCV0Y6N';
        $locB = '01GQ2ZHK064BQRHGDB7CCV0Y6B';

        TiendanubeConfiguracion::obtener()->fill([
            'locations_probe' => 'ok',
            'multi_inventario_activo' => true,
        ])->save();

        TiendanubeProducto::create(['id' => 100, 'name' => ['es' => 'P'], 'published' => true]);
        TiendanubeProductoVariante::create([
            'id' => 900, 'producto_id' => 100, 'sku' => 'S', 'stock' => 10, 'stock_management' => true,
        ]);
        TiendanubeUbicacion::create([
            'id' => $locA, 'store_id' => 8004291, 'name' => ['es' => 'A'],
            'activa' => true, 'synced_at' => now(),
        ]);
        TiendanubeUbicacion::create([
            'id' => $locB, 'store_id' => 8004291, 'name' => ['es' => 'B'],
            'activa' => true, 'synced_at' => now(),
        ]);
        TiendanubeVarianteNivel::create([
            'variante_id' => 900, 'ubicacion_id' => $locA, 'stock' => 3, 'synced_at' => now(),
        ]);
        TiendanubeVarianteNivel::create([
            'variante_id' => 900, 'ubicacion_id' => $locB, 'stock' => 7, 'synced_at' => now(),
        ]);

        Http::fake(function (\Illuminate\Http\Client\Request $request) use ($locA) {
            if ($request->method() === 'PUT' && str_contains($request->url(), '/variants/900')) {
                $levels = $request->data()['inventory_levels'] ?? null;
                $this->assertIsArray($levels);
                $this->assertCount(1, $levels);
                $this->assertSame($locA, $levels[0]['location_id']);
                $this->assertSame(5, $levels[0]['stock']);
                $this->assertArrayNotHasKey('stock', $request->data());
                $this->assertArrayNotHasKey('price', $request->data());

                return Http::response(['id' => 900], 200);
            }
            if ($request->method() === 'GET') {
                return Http::response([
                    'id' => 100, 'name' => ['es' => 'P'], 'published' => true,
                    'images' => [], 'categories' => [],
                    'variants' => [[
                        'id' => 900, 'sku' => 'S', 'values' => [],
                        'inventory_levels' => [
                            ['location_id' => $locA, 'stock' => 5],
                            ['location_id' => '01GQ2ZHK064BQRHGDB7CCV0Y6B', 'stock' => 7],
                        ],
                    ]],
                ], 200);
            }

            return Http::response(['error' => $request->url()], 500);
        });

        app(TiendanubeProductoWriteService::class)->actualizar(100, [
            'location_id' => $locA,
            'stock' => 5,
        ]);
    }

    public function test_visibility_2025_03_no_mezcla_published(): void
    {
        config([
            'tiendanube.api_base' => '',
            'tiendanube.api_host' => 'https://api.tiendanube.com',
            'tiendanube.api_version' => '2025-03',
        ]);

        TiendanubeProducto::create(['id' => 100, 'name' => ['es' => 'P'], 'published' => true]);
        TiendanubeProductoVariante::create(['id' => 900, 'producto_id' => 100, 'sku' => 'S']);

        Http::fake(function (\Illuminate\Http\Client\Request $request) {
            if ($request->method() === 'PUT' && str_contains($request->url(), '/2025-03/') && str_ends_with(rtrim(parse_url($request->url(), PHP_URL_PATH) ?: '', '/'), '/products/100')) {
                $data = $request->data();
                $this->assertSame('hidden', $data['visibility']);
                $this->assertArrayNotHasKey('published', $data);

                return Http::response(['id' => 100], 200);
            }
            if ($request->method() === 'GET') {
                return Http::response([
                    'id' => 100, 'name' => ['es' => 'P'], 'visibility' => 'hidden',
                    'images' => [], 'categories' => [],
                    'variants' => [['id' => 900, 'sku' => 'S', 'values' => []]],
                ], 200);
            }

            return Http::response(['error' => $request->url()], 500);
        });

        app(TiendanubeProductoWriteService::class)->actualizar(100, ['published' => false]);
    }

    public function test_agregar_imagen_fallo_conserva_fotos(): void
    {
        TiendanubeProducto::create(['id' => 100, 'name' => ['es' => 'Prod'], 'published' => true]);
        TiendanubeProductoImagen::create([
            'id' => 10,
            'producto_id' => 100,
            'src' => 'https://cdn.example.com/old.jpg',
            'position' => 1,
        ]);

        Http::fake([
            'api.tiendanube.com/v1/8004291/products/100/images' => Http::response(['error' => 'fail'], 500),
        ]);

        try {
            app(TiendanubeProductoWriteService::class)->agregarImagen(
                100,
                'https://cdn.example.com/nueva.jpg',
                null,
                null,
                true
            );
            $this->fail('Debió fallar la carga');
        } catch (RuntimeException) {
            // esperado
        }

        $this->assertDatabaseHas('tiendanube_producto_imagenes', ['id' => 10, 'producto_id' => 100]);
        Http::assertNotSent(fn ($r) => $r->method() === 'DELETE');
    }

    public function test_crear_timeout_reconcilia_por_sku_sin_repetir_post(): void
    {
        $remote = [
            'id' => 440,
            'name' => ['es' => 'Incerto'],
            'published' => true,
            'images' => [],
            'categories' => [],
            'variants' => [[
                'id' => 441, 'sku' => 'SKU-INC', 'price' => '1.00', 'values' => [],
            ]],
        ];
        $posts = 0;

        Http::fake(function (\Illuminate\Http\Client\Request $request) use ($remote, &$posts) {
            $path = rtrim(parse_url($request->url(), PHP_URL_PATH) ?: '', '/');
            if ($request->method() === 'POST' && str_ends_with($path, '/products')) {
                $posts++;
                throw new ConnectionException('timeout');
            }
            if ($request->method() === 'GET' && str_contains($request->url(), 'sku=')) {
                return Http::response([$remote], 200);
            }
            if ($request->method() === 'GET' && str_contains($request->url(), '/products/440')) {
                return Http::response($remote, 200);
            }

            return Http::response(['error' => $request->url()], 500);
        });

        $producto = app(TiendanubeProductoWriteService::class)->crear([
            'name' => 'Incerto',
            'sku' => 'SKU-INC',
        ]);

        $this->assertSame(440, $producto->id);
        $this->assertSame(1, $posts);
    }
}
