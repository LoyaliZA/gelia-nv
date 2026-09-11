<?php

namespace Tests\Feature\Tiendanube;

use App\Models\Tiendanube\TiendanubeCategoria;
use App\Models\Tiendanube\TiendanubeConfiguracion;
use App\Models\Tiendanube\TiendanubeProducto;
use App\Models\Tiendanube\TiendanubeProductoImagen;
use App\Models\Tiendanube\TiendanubeProductoImagenOperacion;
use App\Models\Tiendanube\TiendanubeProductoVariante;
use App\Models\User;
use App\Services\Tiendanube\OptimizarImagenTiendanubeService;
use App\Services\Tiendanube\TiendanubeProductoWriteService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Tests\Support\RefreshDatabaseSafe;
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
        ]);

        $this->withoutMiddleware([
            PreventRequestForgery::class,
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
            \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
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

        Http::fake(function (Request $request) {
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

    public function test_actualizar_variante_explicita_no_modifica_otra(): void
    {
        $this->seedProductoConDosVariantes();
        $puts = [];

        Http::fake(function (Request $request) use (&$puts) {
            $url = $request->url();
            $method = $request->method();
            if ($method === 'PUT' && str_contains($url, '/variants/')) {
                $puts[] = $url;
                $this->assertStringContainsString('/variants/901', $url);
                $this->assertStringNotContainsString('/variants/900', $url);

                return Http::response(['id' => 901, 'sku' => 'SKU-B-NEW', 'price' => '80.00'], 200);
            }
            if ($method === 'PUT' && str_ends_with(parse_url($url, PHP_URL_PATH) ?? '', '/products/100')) {
                $this->fail('No debía actualizar el producto');
            }
            if ($method === 'GET' && str_contains($url, '/products/100')) {
                return Http::response($this->productoRemotoConVariantes([
                    ['id' => 900, 'sku' => 'SKU-A', 'price' => '10.00', 'stock' => 2, 'stock_management' => true],
                    ['id' => 901, 'sku' => 'SKU-B-NEW', 'price' => '80.00', 'stock' => 4, 'stock_management' => true],
                ]), 200);
            }

            return Http::response(['error' => $method.' '.$url], 500);
        });

        $producto = app(TiendanubeProductoWriteService::class)->actualizar(100, [
            'variant_id' => 901,
            'sku' => 'SKU-B-NEW',
            'price' => 80,
        ]);

        $this->assertCount(1, $puts);
        $this->assertSame('SKU-A', $producto->fresh()->variantes()->find(900)->sku);
        $this->assertSame('SKU-B-NEW', $producto->fresh()->variantes()->find(901)->sku);
    }

    public function test_variant_id_ajeno_rechazado_sin_api(): void
    {
        $this->seedProductoConDosVariantes();
        TiendanubeProducto::create(['id' => 200, 'name' => ['es' => 'Otro'], 'published' => true]);
        TiendanubeProductoVariante::create([
            'id' => 999,
            'producto_id' => 200,
            'sku' => 'AJENO',
            'price' => 1,
            'stock_management' => true,
        ]);

        Http::fake();

        $this->actingAs($this->usuarioEditor())
            ->putJson(route('tiendanube.productos.update', 100), [
                'variant_id' => 999,
                'sku' => 'HACK',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['variant_id']);

        Http::assertNothingSent();
    }

    public function test_multivariante_sin_variant_id_rechazado(): void
    {
        $this->seedProductoConDosVariantes();
        Http::fake();

        $this->actingAs($this->usuarioEditor())
            ->putJson(route('tiendanube.productos.update', 100), [
                'sku' => 'SIN-ID',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['variant_id']);

        Http::assertNothingSent();
    }

    public function test_producto_simple_sin_variant_id_ok(): void
    {
        TiendanubeProducto::create(['id' => 100, 'name' => ['es' => 'Simple'], 'published' => true]);
        TiendanubeProductoVariante::create([
            'id' => 900,
            'producto_id' => 100,
            'sku' => 'OLD',
            'price' => 10,
            'stock_management' => true,
        ]);

        Http::fake(function (Request $request) {
            $url = $request->url();
            $method = $request->method();
            if ($method === 'PUT' && str_contains($url, '/variants/900')) {
                return Http::response(['id' => 900, 'sku' => 'NEW'], 200);
            }
            if ($method === 'GET' && str_contains($url, '/products/100')) {
                return Http::response($this->productoRemotoConVariantes([
                    ['id' => 900, 'sku' => 'NEW', 'price' => '10.00', 'stock' => 1, 'stock_management' => true],
                ]), 200);
            }

            return Http::response(['error' => $method.' '.$url], 500);
        });

        $producto = app(TiendanubeProductoWriteService::class)->actualizar(100, [
            'sku' => 'NEW',
        ]);

        $this->assertSame('NEW', $producto->fresh()->variantes()->first()->sku);
        Http::assertSent(fn ($r) => $r->method() === 'PUT' && str_contains($r->url(), '/variants/900'));
        Http::assertNotSent(fn ($r) => $r->method() === 'PUT' && str_ends_with(rtrim(parse_url($r->url(), PHP_URL_PATH) ?: '', '/'), '/products/100'));
    }

    public function test_stock_omitido_no_envia_stock(): void
    {
        TiendanubeProducto::create(['id' => 100, 'name' => ['es' => 'Viejo'], 'published' => true]);
        TiendanubeProductoVariante::create([
            'id' => 900,
            'producto_id' => 100,
            'sku' => 'OLD',
            'price' => 10,
            'stock' => 7,
            'stock_management' => true,
        ]);

        Http::fake(function (Request $request) {
            $url = $request->url();
            $method = $request->method();
            if ($method === 'PUT' && str_ends_with(parse_url($url, PHP_URL_PATH) ?? '', '/products/100')) {
                return Http::response(['id' => 100], 200);
            }
            if ($method === 'GET' && str_contains($url, '/products/100')) {
                return Http::response($this->productoRemotoConVariantes([
                    ['id' => 900, 'sku' => 'OLD', 'price' => '10.00', 'stock' => 7, 'stock_management' => true],
                ]), 200);
            }

            return Http::response(['error' => $method.' '.$url], 500);
        });

        app(TiendanubeProductoWriteService::class)->actualizar(100, ['name' => 'Nuevo nombre']);

        Http::assertNotSent(fn ($r) => $r->method() === 'PUT' && str_contains($r->url(), '/variants/'));
    }

    public function test_stock_vacio_no_activa_ilimitado(): void
    {
        TiendanubeProducto::create(['id' => 100, 'name' => ['es' => 'P'], 'published' => true]);
        TiendanubeProductoVariante::create([
            'id' => 900,
            'producto_id' => 100,
            'sku' => 'OLD',
            'price' => 10,
            'stock' => 7,
            'stock_management' => true,
        ]);

        Http::fake(function (Request $request) {
            $url = $request->url();
            $method = $request->method();
            if ($method === 'PUT' && str_contains($url, '/variants/900')) {
                return Http::response(['id' => 900, 'sku' => 'NEW'], 200);
            }
            if ($method === 'GET' && str_contains($url, '/products/100')) {
                return Http::response($this->productoRemotoConVariantes([
                    ['id' => 900, 'sku' => 'NEW', 'price' => '10.00', 'stock' => 7, 'stock_management' => true],
                ]), 200);
            }

            return Http::response(['error' => $method.' '.$url], 500);
        });

        app(TiendanubeProductoWriteService::class)->actualizar(100, [
            'sku' => 'NEW',
            'stock' => null,
        ]);

        Http::assertSent(function ($request) {
            if ($request->method() !== 'PUT' || ! str_contains($request->url(), '/variants/900')) {
                return false;
            }
            $data = $request->data();

            return ($data['sku'] ?? null) === 'NEW'
                && ! array_key_exists('stock', $data);
        });
    }

    public function test_actualizacion_parcial_producto_ok_variante_falla(): void
    {
        $this->seedProductoConDosVariantes();

        Http::fake(function (Request $request) {
            $url = $request->url();
            $path = parse_url($url, PHP_URL_PATH) ?? '';
            $method = $request->method();
            if ($method === 'PUT' && str_contains($path, '/variants/901')) {
                return Http::response(['message' => 'busy'], 500);
            }
            if ($method === 'PUT' && str_ends_with($path, '/products/100')) {
                return Http::response(['id' => 100], 200);
            }
            if ($method === 'GET' && str_contains($path, '/products/100')) {
                return Http::response($this->productoRemotoConVariantes([
                    ['id' => 900, 'sku' => 'SKU-A', 'price' => '10.00', 'stock' => 2, 'stock_management' => true],
                    ['id' => 901, 'sku' => 'SKU-B', 'price' => '20.00', 'stock' => 4, 'stock_management' => true],
                ], 'Parcial'), 200);
            }

            return Http::response(['error' => $method.' '.$url], 500);
        });

        $this->actingAs($this->usuarioEditor())
            ->putJson(route('tiendanube.productos.update', 100), [
                'name' => 'Parcial',
                'variant_id' => 901,
                'sku' => 'SKU-B-FAIL',
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('parcial', true)
            ->assertJsonPath('producto_actualizado', true)
            ->assertJsonPath('producto_id', 100);

        $this->assertSame('Parcial', TiendanubeProducto::find(100)?->nombreVisible());
        Http::assertSent(fn ($r) => $r->method() === 'PUT' && str_contains($r->url(), '/variants/901'));
        Http::assertNotSent(fn ($r) => $r->method() === 'PUT' && str_contains($r->url(), '/variants/900'));
    }

    public function test_variante_eliminada_remotamente(): void
    {
        $this->seedProductoConDosVariantes();

        Http::fake(function (Request $request) {
            $url = $request->url();
            $path = parse_url($url, PHP_URL_PATH) ?? '';
            $method = $request->method();
            if ($method === 'PUT' && str_contains($path, '/variants/901')) {
                return Http::response(['message' => 'Not Found'], 404);
            }
            if ($method === 'GET' && str_contains($path, '/products/100')) {
                return Http::response($this->productoRemotoConVariantes([
                    ['id' => 900, 'sku' => 'SKU-A', 'price' => '10.00', 'stock' => 2, 'stock_management' => true],
                ]), 200);
            }

            return Http::response(['error' => $method.' '.$url], 500);
        });

        try {
            app(TiendanubeProductoWriteService::class)->actualizar(100, [
                'variant_id' => 901,
                'sku' => 'GONE',
            ]);
            $this->fail('Debió informar la variante eliminada');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('ya no existe', mb_strtolower($e->getMessage()));
        }

        $this->assertDatabaseMissing('tiendanube_producto_variantes', ['id' => 901]);
        $this->assertDatabaseHas('tiendanube_producto_variantes', ['id' => 900, 'producto_id' => 100]);
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
        )->imagen;

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
        )->imagen;

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
            'api.tiendanube.com/v1/8004291/products/100' => Http::response([
                'id' => 100,
                'name' => ['es' => 'Prod'],
                'images' => [],
                'variants' => [],
            ], 200),
        ]);

        $imagen = app(TiendanubeProductoWriteService::class)->agregarImagen(
            100,
            'https://cdn.example.com/origen.jpg'
        )->imagen;

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
            'api.tiendanube.com/v1/8004291/products/100' => Http::response([
                'id' => 100,
                'name' => ['es' => 'Prod'],
                'images' => [],
                'variants' => [],
            ], 200),
        ]);

        $bin = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
            true
        );
        $path = sys_get_temp_dir().'/tn_upload_'.uniqid('', true).'.png';
        file_put_contents($path, $bin);
        $file = new UploadedFile($path, 'tiny.png', 'image/png', null, true);

        $imagen = app(TiendanubeProductoWriteService::class)->agregarImagen(100, null, $file)->imagen;

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

        TiendanubeProductoImagen::create([
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
        TiendanubeProductoImagen::create([
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

    public function test_index_busca_por_nombre_tags_y_no_confunde_seo(): void
    {
        $user = $this->usuarioEditor();

        $porNombre = TiendanubeProducto::create([
            'id' => 40,
            'name' => ['es' => 'Perfume Mandarina'],
            'seo_title' => 'SKU-XYZ',
            'published' => true,
        ]);
        $porTags = TiendanubeProducto::create([
            'id' => 41,
            'name' => ['es' => 'Otro'],
            'tags' => 'verano-2026',
            'published' => true,
        ]);
        TiendanubeProducto::create([
            'id' => 42,
            'name' => ['es' => 'Irrelevante'],
            'seo_title' => 'Titulo SEO distinto',
            'published' => true,
        ]);

        $this->actingAs($user)
            ->get(route('tiendanube.index', ['search' => 'Mandarina']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Tiendanube/Index', false)
                ->has('productos.data', 1)
                ->where('productos.data.0.id', $porNombre->id)
            );

        $this->actingAs($user)
            ->get(route('tiendanube.index', ['search' => 'verano-2026']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('productos.data', 1)
                ->where('productos.data.0.id', $porTags->id)
            );

        $this->actingAs($user)
            ->get(route('tiendanube.index', ['search' => 'SKU-XYZ']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('productos.data', 1)
                ->where('productos.data.0.id', $porNombre->id)
            );
    }

    public function test_actualizar_sin_categories_no_envia_clave_a_la_api(): void
    {
        $cat = $this->seedCategoria(1);
        $producto = TiendanubeProducto::create(['id' => 100, 'name' => ['es' => 'Prod'], 'published' => true]);
        $producto->categorias()->sync([$cat->id]);

        Http::fake(function (Request $request) use ($cat) {
            $path = parse_url($request->url(), PHP_URL_PATH) ?? '';
            $method = $request->method();

            if ($method === 'PUT' && str_ends_with($path, '/products/100')) {
                $this->assertArrayNotHasKey('categories', $request->data());

                return Http::response(['id' => 100], 200);
            }
            if ($method === 'GET' && str_contains($path, '/products/100')) {
                return Http::response($this->productoRemotoConCategorias([1], 'Prod'), 200);
            }

            return Http::response(['error' => $method.' '.$request->url()], 500);
        });

        $this->actingAs($this->usuarioEditor())
            ->putJson(route('tiendanube.productos.update', 100), [
                'name' => 'Prod',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertEqualsCanonicalizing([$cat->id], $producto->fresh()->categorias->pluck('id')->all());
    }

    public function test_vaciar_categorias_envia_replace_y_lista_vacia(): void
    {
        $cat = $this->seedCategoria(1);
        $producto = TiendanubeProducto::create(['id' => 100, 'name' => ['es' => 'Prod'], 'published' => true]);
        $producto->categorias()->sync([$cat->id]);

        Http::fake(function (Request $request) {
            $path = parse_url($request->url(), PHP_URL_PATH) ?? '';
            $method = $request->method();

            if ($method === 'PUT' && str_ends_with($path, '/products/100')) {
                $this->assertSame([], $request->data()['categories'] ?? null);

                return Http::response(['id' => 100], 200);
            }
            if ($method === 'GET' && str_contains($path, '/products/100')) {
                return Http::response($this->productoRemotoConCategorias([], 'Prod'), 200);
            }

            return Http::response(['error' => $method.' '.$request->url()], 500);
        });

        $this->actingAs($this->usuarioEditor())
            ->putJson(route('tiendanube.productos.update', 100), [
                'categories' => [],
                'replace_categories' => true,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame([], $producto->fresh()->categorias->pluck('id')->all());
    }

    public function test_reasignar_categorias_envia_ids_seleccionados(): void
    {
        $catA = $this->seedCategoria(1);
        $catB = $this->seedCategoria(2);
        $producto = TiendanubeProducto::create(['id' => 100, 'name' => ['es' => 'Prod'], 'published' => true]);
        $producto->categorias()->sync([$catA->id]);

        Http::fake(function (Request $request) {
            $path = parse_url($request->url(), PHP_URL_PATH) ?? '';
            $method = $request->method();

            if ($method === 'PUT' && str_ends_with($path, '/products/100')) {
                $this->assertSame([2], $request->data()['categories'] ?? null);

                return Http::response(['id' => 100], 200);
            }
            if ($method === 'GET' && str_contains($path, '/products/100')) {
                return Http::response($this->productoRemotoConCategorias([2], 'Prod'), 200);
            }

            return Http::response(['error' => $method.' '.$request->url()], 500);
        });

        $this->actingAs($this->usuarioEditor())
            ->putJson(route('tiendanube.productos.update', 100), [
                'categories' => [$catB->id],
                'replace_categories' => true,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertEqualsCanonicalizing([$catB->id], $producto->fresh()->categorias->pluck('id')->all());
    }

    public function test_fallo_remoto_al_vaciar_categorias_no_simula_exito(): void
    {
        $cat = $this->seedCategoria(1);
        $producto = TiendanubeProducto::create(['id' => 100, 'name' => ['es' => 'Prod'], 'published' => true]);
        $producto->categorias()->sync([$cat->id]);

        Http::fake(function (Request $request) {
            $path = parse_url($request->url(), PHP_URL_PATH) ?? '';
            $method = $request->method();

            if ($method === 'PUT' && str_ends_with($path, '/products/100')) {
                return Http::response(['error' => 'fail'], 500);
            }
            if ($method === 'GET' && str_contains($path, '/products/100')) {
                return Http::response($this->productoRemotoConCategorias([1], 'Prod'), 200);
            }

            return Http::response(['error' => $method.' '.$request->url()], 500);
        });

        $this->actingAs($this->usuarioEditor())
            ->putJson(route('tiendanube.productos.update', 100), [
                'categories' => [],
                'replace_categories' => true,
            ])
            ->assertStatus(400)
            ->assertJsonPath('success', false);

        $this->assertEqualsCanonicalizing([$cat->id], $producto->fresh()->categorias->pluck('id')->all());
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

        Http::fake(function ($request) {
            $path = parse_url($request->url(), PHP_URL_PATH) ?: '';
            $method = $request->method();

            if ($method === 'GET' && str_ends_with(rtrim($path, '/'), '/products/100')) {
                return Http::response([
                    'id' => 100,
                    'name' => ['es' => 'Prod'],
                    'published' => true,
                    'attributes' => [],
                    'categories' => [],
                    'images' => [
                        ['id' => 10, 'src' => 'https://cdn.example.com/old.jpg', 'position' => 1],
                        ['id' => 11, 'src' => 'https://cdn.example.com/old2.jpg', 'position' => 2],
                    ],
                    'variants' => [],
                ], 200);
            }
            if ($method === 'POST' && str_ends_with($path, '/products/100/images')) {
                return Http::response([
                    'id' => 99,
                    'src' => 'https://cdn.tiendanube.com/nueva.webp',
                    'position' => 1,
                    'product_id' => 100,
                    'alt' => null,
                ], 201);
            }
            if ($method === 'DELETE' && str_contains($path, '/products/100/images/')) {
                return Http::response([], 200);
            }

            return Http::response(['error' => $method.' '.$path], 500);
        });

        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
            true
        );
        $path = sys_get_temp_dir().'/tn_rep_'.uniqid('', true).'.png';
        file_put_contents($path, $png);
        $file = new UploadedFile($path, 'SKU.png', 'image/png', null, true);

        $imagen = app(TiendanubeProductoWriteService::class)->agregarImagen(100, null, $file, null, true)->imagen;

        $this->assertSame(99, $imagen->id);
        $this->assertSame(1, TiendanubeProductoImagen::where('producto_id', 100)->count());
        $this->assertDatabaseMissing('tiendanube_producto_imagenes', ['id' => 10]);
        $this->assertDatabaseMissing('tiendanube_producto_imagenes', ['id' => 11]);

        $recorded = Http::recorded();
        $postIdx = $recorded->search(fn ($pair) => $pair[0]->method() === 'POST' && str_ends_with(parse_url($pair[0]->url(), PHP_URL_PATH) ?: '', '/images'));
        $delIdx = $recorded->search(fn ($pair) => $pair[0]->method() === 'DELETE');
        $this->assertNotFalse($postIdx);
        $this->assertNotFalse($delIdx);
        $this->assertTrue($postIdx < $delIdx);

        Http::assertSent(fn ($r) => $r->method() === 'DELETE' && str_contains($r->url(), '/images/10'));
        Http::assertSent(fn ($r) => $r->method() === 'DELETE' && str_contains($r->url(), '/images/11'));
        Http::assertNotSent(fn ($r) => $r->method() === 'DELETE' && str_contains($r->url(), '/images/99'));
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

        Http::fake(function ($request) {
            $path = parse_url($request->url(), PHP_URL_PATH) ?: '';
            $method = $request->method();

            if ($method === 'GET' && str_ends_with(rtrim($path, '/'), '/products/100')) {
                return Http::response([
                    'id' => 100,
                    'name' => ['es' => 'P'],
                    'published' => true,
                    'attributes' => [],
                    'categories' => [],
                    'images' => [
                        ['id' => 1, 'src' => 'https://cdn.example.com/old.jpg', 'position' => 1],
                    ],
                    'variants' => [],
                ], 200);
            }
            if ($method === 'POST' && str_ends_with($path, '/products/100/images')) {
                return Http::response([
                    'id' => 2,
                    'src' => 'https://cdn.tiendanube.com/new.webp',
                    'position' => 1,
                    'product_id' => 100,
                ], 201);
            }
            if ($method === 'DELETE' && str_contains($path, '/products/100/images/1')) {
                return Http::response([], 200);
            }

            return Http::response(['error' => $method.' '.$path], 500);
        });

        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
            true
        );
        $file = UploadedFile::fake()->createWithContent('SKU.webp', $png);

        $this->actingAs($user)
            ->withoutMiddleware(PreventRequestForgery::class)
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

    public function test_optimizacion_fallida_no_borra_anteriores(): void
    {
        $this->seedProductoConDosImagenes();

        $this->mock(OptimizarImagenTiendanubeService::class, function ($mock) {
            $mock->shouldReceive('ejecutar')->once()->andThrow(new \RuntimeException('optimización fallida'));
        });

        Http::fake(function ($request) {
            if ($request->method() === 'GET') {
                return Http::response($this->productoRemotoCon([10, 11]), 200);
            }

            return Http::response(['error' => 'no-debe-escribir'], 500);
        });

        try {
            app(TiendanubeProductoWriteService::class)->agregarImagen(100, null, $this->pngFile(), null, true);
            $this->fail('Debió fallar la preparación');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('optimización fallida', $e->getMessage());
        }

        $this->assertDatabaseHas('tiendanube_producto_imagenes', ['id' => 10]);
        $this->assertDatabaseHas('tiendanube_producto_imagenes', ['id' => 11]);
        Http::assertNotSent(fn ($r) => $r->method() === 'POST');
        Http::assertNotSent(fn ($r) => $r->method() === 'DELETE');
    }

    public function test_post_fallido_en_reemplazo_conserva_anteriores(): void
    {
        $this->seedProductoConDosImagenes();

        Http::fake(function ($request) {
            $path = parse_url($request->url(), PHP_URL_PATH) ?: '';
            if ($request->method() === 'GET' && str_ends_with(rtrim($path, '/'), '/products/100')) {
                return Http::response($this->productoRemotoCon([10, 11]), 200);
            }
            if ($request->method() === 'POST') {
                return Http::response(['message' => 'upload failed'], 422);
            }

            return Http::response(['error' => 'no-delete'], 500);
        });

        try {
            app(TiendanubeProductoWriteService::class)->agregarImagen(100, null, $this->pngFile(), null, true);
            $this->fail('Debió fallar la carga');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('422', $e->getMessage());
        }

        $this->assertDatabaseHas('tiendanube_producto_imagenes', ['id' => 10]);
        $this->assertDatabaseHas('tiendanube_producto_imagenes', ['id' => 11]);
        Http::assertNotSent(fn ($r) => $r->method() === 'DELETE');
    }

    public function test_delete_parcial_queda_pendiente_reconciliacion(): void
    {
        $this->seedProductoConDosImagenes();

        Http::fake(function ($request) {
            $path = parse_url($request->url(), PHP_URL_PATH) ?: '';
            $method = $request->method();
            if ($method === 'GET' && str_ends_with(rtrim($path, '/'), '/products/100')) {
                return Http::response($this->productoRemotoCon([10, 11]), 200);
            }
            if ($method === 'POST' && str_ends_with($path, '/images')) {
                return Http::response([
                    'id' => 99,
                    'src' => 'https://cdn.tiendanube.com/nueva.webp',
                    'position' => 1,
                    'product_id' => 100,
                ], 201);
            }
            if ($method === 'DELETE' && str_contains($path, '/images/10')) {
                return Http::response([], 200);
            }
            if ($method === 'DELETE' && str_contains($path, '/images/11')) {
                return Http::response(['message' => 'rate limit'], 500);
            }

            return Http::response(['error' => $method.' '.$path], 500);
        });

        $carga = app(TiendanubeProductoWriteService::class)->agregarImagen(100, null, $this->pngFile(), null, true);

        $this->assertSame(99, $carga->imagen->id);
        $this->assertSame(TiendanubeProductoImagenOperacion::ESTADO_PENDIENTE_RECONCILIACION, $carga->operacion->estado);
        $this->assertTrue($carga->operacion->esParcial());
        $this->assertDatabaseMissing('tiendanube_producto_imagenes', ['id' => 10]);
        $this->assertDatabaseHas('tiendanube_producto_imagenes', ['id' => 11]);
        Http::assertNotSent(fn ($r) => $r->method() === 'DELETE' && str_contains($r->url(), '/images/99'));
    }

    public function test_delete_404_se_considera_resuelto(): void
    {
        $this->seedProductoConDosImagenes();

        Http::fake(function ($request) {
            $path = parse_url($request->url(), PHP_URL_PATH) ?: '';
            $method = $request->method();
            if ($method === 'GET' && str_ends_with(rtrim($path, '/'), '/products/100')) {
                return Http::response($this->productoRemotoCon([10, 11]), 200);
            }
            if ($method === 'POST' && str_ends_with($path, '/images')) {
                return Http::response([
                    'id' => 99,
                    'src' => 'https://cdn.tiendanube.com/nueva.webp',
                    'position' => 1,
                    'product_id' => 100,
                ], 201);
            }
            if ($method === 'DELETE') {
                return Http::response(['message' => 'Not Found'], 404);
            }

            return Http::response(['error' => $method.' '.$path], 500);
        });

        $carga = app(TiendanubeProductoWriteService::class)->agregarImagen(100, null, $this->pngFile(), null, true);

        $this->assertSame(TiendanubeProductoImagenOperacion::ESTADO_COMPLETADA, $carga->operacion->estado);
        $this->assertFalse($carga->operacion->esParcial());
        $this->assertDatabaseMissing('tiendanube_producto_imagenes', ['id' => 10]);
        $this->assertDatabaseMissing('tiendanube_producto_imagenes', ['id' => 11]);
    }

    public function test_reanudar_misma_solicitud_clave_no_repite_post(): void
    {
        $this->seedProductoConDosImagenes();
        $posts = 0;
        $delete11Fails = 1;

        Http::fake(function ($request) use (&$posts, &$delete11Fails) {
            $path = parse_url($request->url(), PHP_URL_PATH) ?: '';
            $method = $request->method();
            if ($method === 'GET' && str_ends_with(rtrim($path, '/'), '/products/100')) {
                return Http::response($this->productoRemotoCon([10, 11]), 200);
            }
            if ($method === 'POST' && str_ends_with($path, '/images')) {
                $posts++;

                return Http::response([
                    'id' => 99,
                    'src' => 'https://cdn.tiendanube.com/nueva.webp',
                    'position' => 1,
                    'product_id' => 100,
                ], 201);
            }
            if ($method === 'DELETE' && str_contains($path, '/images/10')) {
                return Http::response([], 200);
            }
            if ($method === 'DELETE' && str_contains($path, '/images/11')) {
                if ($delete11Fails > 0) {
                    $delete11Fails--;

                    return Http::response(['message' => 'busy'], 500);
                }

                return Http::response([], 200);
            }

            return Http::response(['error' => $method.' '.$path], 500);
        });

        $clave = 'req-reanudar-1';
        $write = app(TiendanubeProductoWriteService::class);
        $primera = $write->agregarImagen(100, null, $this->pngFile(), null, true, [], $clave);
        $this->assertSame(TiendanubeProductoImagenOperacion::ESTADO_PENDIENTE_RECONCILIACION, $primera->operacion->estado);

        $segunda = $write->agregarImagen(100, null, $this->pngFile(), null, true, [], $clave);
        $this->assertSame(TiendanubeProductoImagenOperacion::ESTADO_COMPLETADA, $segunda->operacion->estado);
        $this->assertSame(1, $posts);
        $this->assertSame($primera->operacion->id, $segunda->operacion->id);
    }

    public function test_post_timeout_sin_identificar_no_borra_anteriores(): void
    {
        $this->seedProductoConDosImagenes();

        Http::fake(function ($request) {
            $path = parse_url($request->url(), PHP_URL_PATH) ?: '';
            if ($request->method() === 'GET' && str_ends_with(rtrim($path, '/'), '/products/100')) {
                return Http::response($this->productoRemotoCon([10, 11]), 200);
            }
            if ($request->method() === 'POST') {
                throw new ConnectionException('cURL error 28: timeout');
            }

            return Http::response(['error' => 'no-delete'], 500);
        });

        try {
            app(TiendanubeProductoWriteService::class)->agregarImagen(100, null, $this->pngFile(), null, true);
            $this->fail('Debió marcar resultado incierto');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('no se repetirá el post', mb_strtolower($e->getMessage()));
        }

        $this->assertDatabaseHas('tiendanube_producto_imagenes', ['id' => 10]);
        $this->assertDatabaseHas('tiendanube_producto_imagenes', ['id' => 11]);
        Http::assertNotSent(fn ($r) => $r->method() === 'DELETE');
        $this->assertDatabaseHas('tiendanube_producto_imagen_operaciones', [
            'estado' => TiendanubeProductoImagenOperacion::ESTADO_RESULTADO_INCIERTO,
        ]);
    }

    public function test_reemplazo_concurrente_se_rechaza_con_lock(): void
    {
        $this->seedProductoConDosImagenes();
        Http::fake();

        $lock = Cache::lock('tiendanube:producto-imagen:8004291:100', 120);
        $this->assertTrue($lock->get());

        try {
            app(TiendanubeProductoWriteService::class)->agregarImagen(100, null, $this->pngFile(), null, true);
            $this->fail('Debió rechazar por exclusión');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Ya hay un cambio de imágenes en curso', $e->getMessage());
        } finally {
            $lock->release();
        }
    }

    public function test_agregar_con_limite_de_nueve_no_toca_remotas(): void
    {
        TiendanubeProducto::create(['id' => 100, 'name' => ['es' => 'Prod'], 'published' => true]);
        $ids = [];
        for ($i = 1; $i <= 9; $i++) {
            $ids[] = $i;
            TiendanubeProductoImagen::create([
                'id' => $i,
                'producto_id' => 100,
                'src' => 'https://cdn.example.com/'.$i.'.jpg',
                'position' => $i,
            ]);
        }

        Http::fake(function ($request) use ($ids) {
            if ($request->method() === 'GET') {
                return Http::response($this->productoRemotoCon($ids), 200);
            }

            return Http::response(['error' => 'no-write'], 500);
        });

        try {
            app(TiendanubeProductoWriteService::class)->agregarImagen(100, null, $this->pngFile(), null, false);
            $this->fail('Debió rechazar el límite');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('9 imágenes', $e->getMessage());
        }

        Http::assertNotSent(fn ($r) => $r->method() === 'POST');
        Http::assertNotSent(fn ($r) => $r->method() === 'DELETE');
        $this->assertSame(9, TiendanubeProductoImagen::where('producto_id', 100)->count());
    }

    public function test_reconciliar_completa_eliminaciones_pendientes(): void
    {
        $this->seedProductoConDosImagenes();
        Permission::findOrCreate('tiendanube.ver', 'web');
        Permission::findOrCreate('tiendanube.productos.editar', 'web');
        $user = User::factory()->create();
        $user->givePermissionTo(['tiendanube.ver', 'tiendanube.productos.editar']);

        $op = TiendanubeProductoImagenOperacion::create([
            'tienda_id' => '8004291',
            'producto_id' => 100,
            'solicitud_clave' => 'rec-1',
            'modo' => TiendanubeProductoImagenOperacion::MODO_REEMPLAZAR_TODAS,
            'ids_originales' => [10, 11],
            'imagen_nueva_id' => 99,
            'estado' => TiendanubeProductoImagenOperacion::ESTADO_PENDIENTE_RECONCILIACION,
            'eliminaciones' => [
                '10' => ['resultado' => 'ok'],
                '11' => ['resultado' => 'error', 'mensaje' => 'busy'],
            ],
        ]);
        TiendanubeProductoImagen::create([
            'id' => 99,
            'producto_id' => 100,
            'src' => 'https://cdn.tiendanube.com/nueva.webp',
            'position' => 1,
        ]);

        Http::fake(function ($request) {
            $path = parse_url($request->url(), PHP_URL_PATH) ?: '';
            if ($request->method() === 'GET') {
                return Http::response($this->productoRemotoCon([99]), 200);
            }
            if ($request->method() === 'DELETE' && str_contains($path, '/images/11')) {
                return Http::response([], 200);
            }

            return Http::response(['error' => $request->method().' '.$path], 500);
        });

        $this->actingAs($user)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->post(route('tiendanube.imagen_operaciones.reconciliar', $op->id), [], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('parcial', false);

        $this->assertDatabaseMissing('tiendanube_producto_imagenes', ['id' => 11]);
        Http::assertNotSent(fn ($r) => $r->method() === 'POST');
    }

    /**
     * @param  list<int>  $imageIds
     * @return array<string, mixed>
     */
    private function productoRemotoCon(array $imageIds): array
    {
        $images = [];
        foreach ($imageIds as $i => $id) {
            $images[] = [
                'id' => $id,
                'src' => 'https://cdn.example.com/'.$id.'.jpg',
                'position' => $i + 1,
            ];
        }

        return [
            'id' => 100,
            'name' => ['es' => 'Prod'],
            'published' => true,
            'attributes' => [],
            'categories' => [],
            'images' => $images,
            'variants' => [],
        ];
    }

    /**
     * @param  list<int>  $categoriaIds
     * @return array<string, mixed>
     */
    private function productoRemotoConCategorias(array $categoriaIds, string $nombre = 'Prod'): array
    {
        $payload = $this->productoRemotoCon([]);
        $payload['name'] = ['es' => $nombre];
        $payload['categories'] = $categoriaIds;

        return $payload;
    }

    private function seedCategoria(int $id): TiendanubeCategoria
    {
        return TiendanubeCategoria::create([
            'id' => $id,
            'name' => ['es' => 'Cat '.$id],
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $variants
     * @return array<string, mixed>
     */
    private function productoRemotoConVariantes(array $variants, string $nombre = 'Prod'): array
    {
        $normalizadas = [];
        foreach ($variants as $v) {
            $normalizadas[] = [
                'id' => $v['id'],
                'sku' => $v['sku'] ?? null,
                'price' => $v['price'] ?? null,
                'promotional_price' => $v['promotional_price'] ?? null,
                'cost' => $v['cost'] ?? null,
                'stock' => $v['stock'] ?? null,
                'stock_management' => $v['stock_management'] ?? true,
                'values' => $v['values'] ?? [],
            ];
        }

        return [
            'id' => 100,
            'name' => ['es' => $nombre],
            'description' => ['es' => ''],
            'handle' => ['es' => 'prod'],
            'brand' => null,
            'published' => true,
            'seo_title' => null,
            'seo_description' => null,
            'tags' => null,
            'attributes' => [],
            'categories' => [],
            'images' => [],
            'variants' => $normalizadas,
        ];
    }

    private function seedProductoConDosVariantes(): void
    {
        TiendanubeProducto::create(['id' => 100, 'name' => ['es' => 'Multi'], 'published' => true]);
        TiendanubeProductoVariante::create([
            'id' => 900,
            'producto_id' => 100,
            'sku' => 'SKU-A',
            'price' => 10,
            'stock' => 2,
            'stock_management' => true,
            'values' => [['es' => 'Rojo']],
        ]);
        TiendanubeProductoVariante::create([
            'id' => 901,
            'producto_id' => 100,
            'sku' => 'SKU-B',
            'price' => 20,
            'stock' => 4,
            'stock_management' => true,
            'values' => [['es' => 'Azul']],
        ]);
    }

    private function usuarioEditor(): User
    {
        Permission::findOrCreate('tiendanube.ver', 'web');
        Permission::findOrCreate('tiendanube.productos.editar', 'web');
        $user = User::factory()->create();
        $user->givePermissionTo(['tiendanube.ver', 'tiendanube.productos.editar']);

        return $user;
    }

    private function seedProductoConDosImagenes(): void
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
    }

    private function pngFile(string $name = 'SKU.png'): UploadedFile
    {
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
            true
        );

        return UploadedFile::fake()->createWithContent($name, $png);
    }
}
