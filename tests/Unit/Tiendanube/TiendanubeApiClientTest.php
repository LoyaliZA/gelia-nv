<?php

namespace Tests\Unit\Tiendanube;

use App\Exceptions\Tiendanube\TiendanubeApiException;
use App\Exceptions\Tiendanube\TiendanubeApiNotFoundException;
use App\Exceptions\Tiendanube\TiendanubeApiRouteException;
use App\Models\Tiendanube\TiendanubeConfiguracion;
use App\Models\User;
use App\Services\Tiendanube\TiendanubeApiClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Spatie\Permission\Models\Permission;
use Tests\Support\FakesTiendanubeApi;
use Tests\Support\RefreshDatabaseSafe;
use Tests\TestCase;

class TiendanubeApiClientTest extends TestCase
{
    use FakesTiendanubeApi;
    use RefreshDatabaseSafe;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureTiendanubeHttp();
        $this->seedTiendanubeCredentials();
    }

    public function test_resuelve_url_host_version_tienda(): void
    {
        $this->configureTiendanubeHttp([
            'tiendanube.api_version' => '2025-03',
        ]);

        $this->assertSame(
            'https://api.tiendanube.com/2025-03/8004291',
            app(TiendanubeApiClient::class)->resolveBaseUrl()
        );
    }

    public function test_api_base_legado_tiene_precedencia(): void
    {
        $this->configureTiendanubeHttp([
            'tiendanube.api_base' => 'https://api.tiendanube.com/v1',
            'tiendanube.api_version' => '2025-03',
        ]);

        $api = app(TiendanubeApiClient::class);
        $this->assertSame('https://api.tiendanube.com/v1/8004291', $api->resolveBaseUrl());
        $this->assertSame('v1', $api->configuredVersion());
    }

    public function test_rechaza_version_duplicada_en_api_base(): void
    {
        $this->configureTiendanubeHttp([
            'tiendanube.api_base' => 'https://api.tiendanube.com/v1/v1',
        ]);

        $this->expectException(InvalidArgumentException::class);
        app(TiendanubeApiClient::class)->resolveBaseUrl();
    }

    public function test_rechaza_version_desconocida(): void
    {
        $this->configureTiendanubeHttp([
            'tiendanube.api_version' => '2019-01',
        ]);

        $this->expectException(InvalidArgumentException::class);
        app(TiendanubeApiClient::class)->resolveBaseUrl();
    }

    public function test_envia_authorization_y_user_agent_con_contacto(): void
    {
        Http::fake([
            $this->tiendanubeUrl('/store') => Http::response(['id' => 8004291, 'name' => ['es' => 'Demo']], 200),
        ]);

        app(TiendanubeApiClient::class)->getStore();

        Http::assertSent(function ($request) {
            $auth = $request->header('Authorization')[0] ?? '';
            $ua = $request->header('User-Agent')[0] ?? '';
            $this->assertSame('Bearer token-test', $auth);
            $this->assertSame('Gelianv (integraciones@example.com)', $ua);
            $this->assertSame($this->tiendanubeUrl('/store'), $request->url());
            $this->assertStringNotContainsString('token-test', $ua);

            return true;
        });
    }

    public function test_404_de_producto_no_es_error_de_ruta(): void
    {
        Http::fake([
            $this->tiendanubeUrl('/products/99') => Http::response(
                ['code' => 404, 'message' => 'Not Found'],
                404,
                ['Content-Type' => 'application/json']
            ),
        ]);

        try {
            app(TiendanubeApiClient::class)->getProduct(99);
            $this->fail('Debió lanzar not found');
        } catch (TiendanubeApiNotFoundException $e) {
            $this->assertSame(404, $e->statusCode);
            $this->assertSame('/products/99', $e->resource);
            $this->assertStringNotContainsString('token-test', $e->getMessage());
            $this->assertStringNotContainsString('Bearer', $e->getMessage());
        }
    }

    public function test_404_html_de_store_es_error_de_ruta(): void
    {
        Http::fake([
            $this->tiendanubeUrl('/store') => Http::response('<html>not found</html>', 404, [
                'Content-Type' => 'text/html',
            ]),
        ]);

        $this->expectException(TiendanubeApiRouteException::class);
        app(TiendanubeApiClient::class)->getStore();
    }

    public function test_get_503_reintenta_y_post_timeout_no(): void
    {
        Http::fake([
            $this->tiendanubeUrl('/store') => Http::sequence()
                ->push(['error' => 'down'], 503)
                ->push(['id' => 8004291, 'name' => ['es' => 'Ok']], 200),
        ]);

        $store = app(TiendanubeApiClient::class)->getStore();
        $this->assertSame(8004291, $store['id']);
        Http::assertSentCount(2);

        $posts = 0;
        Http::fake(function ($request) use (&$posts) {
            if ($request->method() === 'POST') {
                $posts++;
                throw new ConnectionException('cURL error 28: timeout');
            }

            return Http::response([], 200);
        });

        try {
            app(TiendanubeApiClient::class)->createProduct(['name' => 'x']);
            $this->fail('Debió fallar el POST');
        } catch (TiendanubeApiException $e) {
            $this->assertSame('connection_failed', $e->summary);
        }
        $this->assertSame(1, $posts);
    }

    public function test_paginacion_sigue_link_valido(): void
    {
        $next = $this->tiendanubeUrl('/categories?page=2&per_page=50');
        $n = 0;
        Http::fake(function () use (&$n, $next) {
            $n++;
            if ($n > 1) {
                return Http::response([], 200);
            }

            return Http::response(
                [['id' => 1, 'name' => ['es' => 'A']]],
                200,
                ['Link' => '<'.$next.'>; rel="next"']
            );
        });

        $pages = iterator_to_array(app(TiendanubeApiClient::class)->paginatePath('/categories'));
        $this->assertSame(2, $n);
        $this->assertCount(2, $pages);
        $this->assertSame(1, $pages[0][0]['id']);
        $this->assertSame([], $pages[1]);
    }

    public function test_paginacion_rechaza_link_de_host_ajeno(): void
    {
        Http::fake(function () {
            return Http::response(
                [['id' => 1]],
                200,
                ['Link' => '<https://evil.example/steal?page=2>; rel="next"']
            );
        });

        $this->expectException(TiendanubeApiRouteException::class);
        iterator_to_array(app(TiendanubeApiClient::class)->paginatePath('/products'));
    }

    public function test_pagina_vacia_y_respuesta_invalida_terminan(): void
    {
        Http::fake([
            $this->tiendanubeUrl('/categories*') => Http::response([], 200),
        ]);
        $empty = iterator_to_array(app(TiendanubeApiClient::class)->paginatePath('/categories'));
        $this->assertSame([[]], $empty);

        Http::fake([
            $this->tiendanubeUrl('/products*') => Http::response(['not' => 'a list'], 200),
        ]);
        $this->expectException(\App\Exceptions\Tiendanube\TiendanubeApiException::class);
        iterator_to_array(app(TiendanubeApiClient::class)->paginatePath('/products'));
    }

    public function test_probar_conexion_expone_version_sin_secretos(): void
    {
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class);
        Permission::findOrCreate('tiendanube.ver', 'web');
        Permission::findOrCreate('tiendanube.configurar', 'web');
        $user = User::factory()->create();
        $user->givePermissionTo(['tiendanube.ver', 'tiendanube.configurar']);

        Http::fake(function ($request) {
            $url = $request->url();
            if (str_contains($url, '/store')) {
                return Http::response([
                    'id' => 8004291,
                    'name' => ['es' => 'Tienda Demo'],
                    'original_domain' => 'demo.mitiendanube.com',
                ], 200);
            }
            if (str_contains($url, '/categories')) {
                return Http::response([], 200);
            }
            if (str_contains($url, '/locations')) {
                return Http::response(['code' => 403, 'message' => 'Forbidden'], 403);
            }

            return Http::response(['error' => 'unexpected'], 500);
        });

        $this->configureTiendanubeHttp(['tiendanube.api_version' => '2025-03']);

        $res = $this->actingAs($user)->postJson(route('tiendanube.configuracion.probar_conexion'));
        $res->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('api_version', '2025-03')
            ->assertJsonPath('api_host', 'api.tiendanube.com')
            ->assertJsonPath('checks.store', 'ok')
            ->assertJsonPath('checks.categories', 'ok')
            ->assertJsonPath('checks.locations', 'forbidden');

        $this->assertSame('forbidden', TiendanubeConfiguracion::obtener()->locations_probe);

        $json = $res->json();
        $this->assertStringNotContainsString('token-test', json_encode($json));
        $this->assertArrayNotHasKey('access_token', $json);
    }
}
