<?php

namespace Tests\Feature\Tiendanube;

use App\Jobs\Tiendanube\ProcessTiendanubeWebhook;
use App\Models\Tiendanube\TiendanubeCategoria;
use App\Models\Tiendanube\TiendanubeConfiguracion;
use App\Models\Tiendanube\TiendanubeProducto;
use App\Models\Tiendanube\TiendanubeWebhookDelivery;
use App\Models\User;
use App\Services\Tiendanube\TiendanubeApiClient;
use App\Services\Tiendanube\TiendanubeCatalogoSyncService;
use App\Services\Tiendanube\TiendanubePrivacyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class TiendanubeWebhookTest extends TestCase
{
    use RefreshDatabase;

    private string $secret = 'test-app-secret';

    private string $webhookUrl = 'https://hooks.example.com/webhooks/tiendanube';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'tiendanube.api_base' => 'https://api.tiendanube.com/v1',
            'tiendanube.per_page' => 50,
            'tiendanube.user_agent' => 'Gelianv',
            'tiendanube.app_secret' => $this->secret,
            'tiendanube.webhook_url' => $this->webhookUrl,
            'tiendanube.webhook_events' => [
                'product/updated',
                'product/created',
                'app/uninstalled',
            ],
        ]);

        TiendanubeConfiguracion::obtener()->fill([
            'store_id' => 8004291,
            'app_id' => '37163',
            'access_token' => Crypt::encryptString('token-test'),
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function postSignedWebhook(array $payload): \Illuminate\Testing\TestResponse
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $body, $this->secret);

        return $this->call(
            'POST',
            route('webhooks.tiendanube'),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_LINKEDSTORE_HMAC_SHA256' => $signature,
            ],
            $body
        );
    }

    private function runDeliveryJob(TiendanubeWebhookDelivery $delivery): void
    {
        (new ProcessTiendanubeWebhook($delivery->id))->handle(
            app(TiendanubeApiClient::class),
            app(TiendanubeCatalogoSyncService::class),
            app(TiendanubePrivacyService::class)
        );
    }

    public function test_receptor_rechaza_sin_firma(): void
    {
        $this->postJson(route('webhooks.tiendanube'), [
            'store_id' => 8004291,
            'event' => 'product/updated',
            'id' => 100,
        ])->assertStatus(401);
    }

    public function test_receptor_rechaza_firma_invalida(): void
    {
        $body = json_encode([
            'store_id' => 8004291,
            'event' => 'product/updated',
            'id' => 100,
        ], JSON_THROW_ON_ERROR);

        $this->call(
            'POST',
            route('webhooks.tiendanube'),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_LINKEDSTORE_HMAC_SHA256' => 'firma-invalida',
            ],
            $body
        )->assertStatus(401);
    }

    public function test_receptor_acepta_firma_valida_y_procesa_product_updated(): void
    {
        Queue::fake();

        $payload = [
            'store_id' => 8004291,
            'event' => 'product/updated',
            'id' => 1948209,
        ];

        $this->postSignedWebhook($payload)->assertOk()->assertJson(['ok' => true]);

        $this->assertDatabaseHas('tiendanube_webhook_deliveries', [
            'event' => 'product/updated',
            'resource_id' => '1948209',
            'status' => 'received',
        ]);

        Queue::assertPushed(ProcessTiendanubeWebhook::class);

        $delivery = TiendanubeWebhookDelivery::query()->firstOrFail();

        Http::fake([
            'api.tiendanube.com/v1/8004291/products/1948209' => Http::response([
                'id' => 1948209,
                'name' => ['es' => 'Perfume Webhook'],
                'description' => ['es' => '<p>Desc</p>'],
                'handle' => ['es' => 'perfume-webhook'],
                'brand' => 'Gelia',
                'published' => true,
                'seo_title' => 'SEO WH',
                'seo_description' => 'SEO desc',
                'images' => [],
                'variants' => [
                    [
                        'id' => 1,
                        'sku' => 'SKU-WH-1',
                        'price' => '100.00',
                        'stock' => 5,
                    ],
                ],
                'categories' => [],
            ]),
        ]);

        $this->runDeliveryJob($delivery);

        $this->assertDatabaseHas('tiendanube_productos', [
            'id' => 1948209,
        ]);
        $this->assertSame('processed', $delivery->fresh()->status);
        $this->assertNotNull(TiendanubeProducto::find(1948209));
    }

    public function test_store_redact_borra_catalogo_y_limpia_config(): void
    {
        Queue::fake();

        TiendanubeProducto::query()->create([
            'id' => 1948209,
            'name' => ['es' => 'A borrar'],
            'published' => false,
        ]);
        TiendanubeCategoria::query()->create([
            'id' => 55,
            'name' => ['es' => 'Cat'],
        ]);

        $this->postSignedWebhook([
            'store_id' => 8004291,
            'event' => 'store/redact',
        ])->assertOk();

        $delivery = TiendanubeWebhookDelivery::query()->firstOrFail();
        $this->runDeliveryJob($delivery);

        $this->assertSame('processed', $delivery->fresh()->status);
        $this->assertDatabaseMissing('tiendanube_productos', ['id' => 1948209]);
        $this->assertDatabaseMissing('tiendanube_categorias', ['id' => 55]);
        $this->assertDatabaseHas('tiendanube_webhook_deliveries', [
            'id' => $delivery->id,
            'event' => 'store/redact',
        ]);

        $config = TiendanubeConfiguracion::obtener();
        $this->assertNull($config->store_id);
        $this->assertNull($config->access_token);
        $this->assertNull($config->store_name);
        $this->assertNull($config->store_url);
        $this->assertNull($config->scopes);
    }

    public function test_customers_redact_marca_procesado(): void
    {
        Queue::fake();

        $this->postSignedWebhook([
            'store_id' => 8004291,
            'event' => 'customers/redact',
            'customer' => ['id' => 1, 'email' => 'a@b.com'],
        ])->assertOk();

        $delivery = TiendanubeWebhookDelivery::query()->firstOrFail();
        $this->runDeliveryJob($delivery);

        $this->assertSame('processed', $delivery->fresh()->status);
        $this->assertNotNull(TiendanubeConfiguracion::obtener()->access_token);
    }

    public function test_customers_data_request_marca_procesado(): void
    {
        Queue::fake();

        $this->postSignedWebhook([
            'store_id' => 8004291,
            'event' => 'customers/data_request',
            'customer' => ['id' => 1, 'email' => 'a@b.com'],
            'data_request' => ['id' => 99],
        ])->assertOk();

        $delivery = TiendanubeWebhookDelivery::query()->firstOrFail();
        $this->runDeliveryJob($delivery);

        $this->assertSame('processed', $delivery->fresh()->status);
    }

    public function test_store_redact_con_store_id_distinto_no_borra(): void
    {
        Queue::fake();

        TiendanubeProducto::query()->create([
            'id' => 1948209,
            'name' => ['es' => 'Se queda'],
            'published' => false,
        ]);

        $this->postSignedWebhook([
            'store_id' => 9999999,
            'event' => 'store/redact',
        ])->assertOk();

        $delivery = TiendanubeWebhookDelivery::query()->firstOrFail();
        $this->runDeliveryJob($delivery);

        $this->assertSame('ignored', $delivery->fresh()->status);
        $this->assertDatabaseHas('tiendanube_productos', ['id' => 1948209]);
        $this->assertNotNull(TiendanubeConfiguracion::obtener()->access_token);
    }

    public function test_aplicar_recomendados_crea_solo_faltantes(): void
    {
        $this->withoutMiddleware([
            \Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class,
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
            \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
        ]);

        Permission::findOrCreate('tiendanube.ver', 'web');
        Permission::findOrCreate('tiendanube.configurar', 'web');

        $user = User::factory()->create();
        $user->givePermissionTo(['tiendanube.ver', 'tiendanube.configurar']);

        $created = [];

        Http::fake(function (\Illuminate\Http\Client\Request $request) use (&$created) {
            $url = $request->url();

            if ($request->method() === 'GET' && str_contains($url, '/webhooks')) {
                return Http::response([
                    [
                        'id' => 10,
                        'event' => 'product/updated',
                        'url' => $this->webhookUrl,
                    ],
                ]);
            }

            if ($request->method() === 'POST' && str_ends_with(parse_url($url, PHP_URL_PATH) ?: '', '/webhooks')) {
                $data = $request->data();
                $created[] = $data['event'] ?? null;

                return Http::response([
                    'id' => 100 + count($created),
                    'event' => $data['event'],
                    'url' => $data['url'],
                    'created_at' => now()->toIso8601String(),
                    'updated_at' => now()->toIso8601String(),
                ], 201);
            }

            return Http::response(['message' => 'unexpected '.$request->method().' '.$url], 500);
        });

        $this->actingAs($user)
            ->postJson(route('tiendanube.webhooks.aplicar_recomendados'), [
                'url' => $this->webhookUrl,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('resultado.ya_existentes', ['product/updated']);

        $this->assertEqualsCanonicalizing(
            ['product/created', 'app/uninstalled'],
            $created
        );
    }
}
