<?php

namespace Tests\Feature\Tiendanube;

use App\Jobs\Tiendanube\ProcessTiendanubeWebhook;
use App\Models\AuditoriaConfiguracion;
use App\Models\Tiendanube\TiendanubeCategoria;
use App\Models\Tiendanube\TiendanubeConfiguracion;
use App\Models\Tiendanube\TiendanubeProducto;
use App\Models\Tiendanube\TiendanubeWebhookDelivery;
use App\Models\User;
use App\Services\Tiendanube\TiendanubeApiClient;
use App\Services\Tiendanube\TiendanubeCatalogoSyncService;
use App\Services\Tiendanube\TiendanubeOperacionTiendaService;
use App\Services\Tiendanube\TiendanubePrivacyService;
use App\Services\Tiendanube\TiendanubeWebhookInboxService;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Support\RefreshDatabaseSafe;
use Tests\TestCase;

class TiendanubeWebhookTest extends TestCase
{
    use RefreshDatabaseSafe;

    private string $secret = 'test-app-secret';

    private string $webhookUrl = 'https://hooks.example.com/webhooks/tiendanube';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'tiendanube.api_base' => 'https://api.tiendanube.com/v1',
            'tiendanube.per_page' => 50,
            'tiendanube.user_agent' => 'Gelianv',
            'tiendanube.retry_sleep_ms' => 0,
            'tiendanube.app_secret' => $this->secret,
            'tiendanube.webhook_url' => $this->webhookUrl,
            'tiendanube.store_id' => null,
            'tiendanube.access_token' => null,
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
            app(TiendanubePrivacyService::class),
            app(TiendanubeWebhookInboxService::class)
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function productoApi(string $nombre): array
    {
        return [
            'id' => 1948209,
            'name' => ['es' => $nombre],
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
        ];
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
            'status' => 'queued',
        ]);

        Queue::assertPushed(ProcessTiendanubeWebhook::class);

        $delivery = TiendanubeWebhookDelivery::query()->firstOrFail();

        Http::fake([
            'api.tiendanube.com/v1/8004291/products/1948209' => Http::response($this->productoApi('Perfume Webhook')),
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

    public function test_product_deleted_elimina_producto_local(): void
    {
        Queue::fake();

        TiendanubeProducto::query()->create([
            'id' => 1948209,
            'name' => ['es' => 'A borrar'],
            'published' => false,
        ]);

        $this->postSignedWebhook([
            'store_id' => 8004291,
            'event' => 'product/deleted',
            'id' => 1948209,
        ])->assertOk();

        $delivery = TiendanubeWebhookDelivery::query()->firstOrFail();
        $this->runDeliveryJob($delivery);

        $this->assertSame('processed', $delivery->fresh()->status);
        $this->assertDatabaseMissing('tiendanube_productos', ['id' => 1948209]);
    }

    public function test_listar_entregas_webhook(): void
    {
        Permission::findOrCreate('tiendanube.ver', 'web');
        Permission::findOrCreate('tiendanube.configurar', 'web');

        $user = User::factory()->create();
        $user->givePermissionTo(['tiendanube.ver', 'tiendanube.configurar']);

        TiendanubeWebhookDelivery::query()->create([
            'store_id' => 8004291,
            'event' => 'product/deleted',
            'resource_id' => '999',
            'payload' => ['event' => 'product/deleted', 'id' => 999],
            'payload_hash' => hash('sha256', 'x'),
            'hmac_valid' => true,
            'status' => 'processed',
        ]);

        $this->actingAs($user)
            ->getJson(route('tiendanube.webhooks.entregas'))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('entregas.0.event', 'product/deleted')
            ->assertJsonPath('entregas.0.resource_id', '999')
            ->assertJsonPath('entregas.0.status', 'processed');
    }

    public function test_dos_cuerpos_identicos_se_persisten_y_el_espejo_refleja_el_estado_remoto_final(): void
    {
        Queue::fake();

        $payload = [
            'store_id' => 8004291,
            'event' => 'product/updated',
            'id' => 1948209,
        ];

        $this->postSignedWebhook($payload)->assertOk();
        $this->postSignedWebhook($payload)->assertOk();

        $this->assertSame(2, TiendanubeWebhookDelivery::query()->count());

        $entregas = TiendanubeWebhookDelivery::query()->orderBy('id')->get();
        Http::fake([
            'api.tiendanube.com/v1/8004291/products/1948209' => Http::sequence()
                ->push($this->productoApi('Primera version'))
                ->push($this->productoApi('Version final')),
        ]);

        $this->runDeliveryJob($entregas[0]);
        $this->runDeliveryJob($entregas[1]);

        $this->assertSame('processed', $entregas[0]->fresh()->status);
        $this->assertSame('processed', $entregas[1]->fresh()->status);
        $this->assertSame('Version final', TiendanubeProducto::query()->find(1948209)?->name['es'] ?? null);
    }

    public function test_fallo_de_broker_deja_received_y_el_recuperador_reencola(): void
    {
        $inbox = app(TiendanubeWebhookInboxService::class);
        $delivery = $inbox->persistReceived([
            'store_id' => 8004291,
            'event' => 'product/updated',
            'id' => 1948209,
        ], hash('sha256', 'cuerpo-identico'));

        $dispatcher = \Mockery::mock(\Illuminate\Contracts\Bus\Dispatcher::class);
        $dispatcher->shouldReceive('dispatch')->andThrow(new \RuntimeException('broker down'));
        $dispatcher->shouldReceive('hasCommandHandler')->andReturn(false);
        $this->app->instance(\Illuminate\Contracts\Bus\Dispatcher::class, $dispatcher);

        $this->assertFalse($inbox->tryDispatch($delivery));
        $this->assertSame('received', $delivery->fresh()->status);
    }

    public function test_recuperador_reencola_entregas_received(): void
    {
        Queue::fake();

        $delivery = app(TiendanubeWebhookInboxService::class)->persistReceived([
            'store_id' => 8004291,
            'event' => 'product/updated',
            'id' => 1948209,
        ], hash('sha256', 'recuperar-received'));

        $this->assertSame('received', $delivery->status);

        $this->artisan('tiendanube:recuperar-webhooks')->assertSuccessful();
        Queue::assertPushed(ProcessTiendanubeWebhook::class, fn (ProcessTiendanubeWebhook $job) => $job->deliveryId === $delivery->id);
    }

    public function test_solo_un_trabajador_adquiere_la_entrega(): void
    {
        $inbox = app(TiendanubeWebhookInboxService::class);
        $delivery = $inbox->persistReceived([
            'store_id' => 8004291,
            'event' => 'product/updated',
            'id' => 1948209,
        ], hash('sha256', 'lease-test'));

        $this->assertTrue($inbox->tryAcquire($delivery, (string) Str::uuid()));
        $this->assertFalse($inbox->tryAcquire($delivery->fresh(), (string) Str::uuid()));
        $this->assertSame('processing', $delivery->fresh()->status);

        Http::fake([
            'api.tiendanube.com/v1/8004291/products/1948209' => Http::response($this->productoApi('Unico')),
        ]);

        $this->runDeliveryJob($delivery);
        $this->assertSame('processing', $delivery->fresh()->status);
        $this->assertNull(TiendanubeProducto::query()->find(1948209));
    }

    public function test_lease_vencido_es_recuperable(): void
    {
        Queue::fake();

        $delivery = TiendanubeWebhookDelivery::query()->create([
            'store_id' => 8004291,
            'event' => 'product/updated',
            'resource_id' => '1948209',
            'payload' => ['store_id' => 8004291, 'event' => 'product/updated', 'id' => 1948209],
            'payload_hash' => hash('sha256', 'lease-vencido'),
            'hmac_valid' => true,
            'status' => 'processing',
            'attempts' => 1,
            'lease_token' => (string) Str::uuid(),
            'lease_expires_at' => now()->subMinutes(10),
        ]);

        $this->artisan('tiendanube:recuperar-webhooks')->assertSuccessful();
        Queue::assertPushed(ProcessTiendanubeWebhook::class, fn (ProcessTiendanubeWebhook $job) => $job->deliveryId === $delivery->id);
    }

    public function test_reintentos_agotados_marcan_failed(): void
    {
        config(['tiendanube.webhook_max_attempts' => 1]);

        $delivery = app(TiendanubeWebhookInboxService::class)->persistReceived([
            'store_id' => 8004291,
            'event' => 'product/updated',
            'id' => 1948209,
        ], hash('sha256', 'fail-max'));

        Http::fake([
            'api.tiendanube.com/v1/8004291/products/1948209' => Http::response(['message' => 'down'], 500),
        ]);

        $this->runDeliveryJob($delivery);

        $this->assertSame('failed', $delivery->fresh()->status);
        $this->assertNotNull($delivery->fresh()->error);
    }

    public function test_error_recuperable_queda_retry_pending(): void
    {
        config(['tiendanube.webhook_max_attempts' => 5]);

        $delivery = app(TiendanubeWebhookInboxService::class)->persistReceived([
            'store_id' => 8004291,
            'event' => 'product/updated',
            'id' => 1948209,
        ], hash('sha256', 'retry-pending'));

        Http::fake([
            'api.tiendanube.com/v1/8004291/products/1948209' => Http::response(['message' => 'down'], 500),
        ]);

        $this->runDeliveryJob($delivery);

        $fresh = $delivery->fresh();
        $this->assertSame('retry_pending', $fresh->status);
        $this->assertNotNull($fresh->next_attempt_at);
        $this->assertSame(1, $fresh->attempts);
    }

    public function test_trabajador_antiguo_no_finaliza_una_adquisicion_nueva(): void
    {
        $inbox = app(TiendanubeWebhookInboxService::class);
        $delivery = $inbox->persistReceived([
            'store_id' => 8004291,
            'event' => 'product/updated',
            'id' => 1948209,
        ], hash('sha256', 'token-viejo'));

        $tokenViejo = (string) Str::uuid();
        $this->assertTrue($inbox->tryAcquire($delivery, $tokenViejo));

        $delivery->forceFill([
            'lease_expires_at' => now()->subMinute(),
        ])->save();

        $tokenNuevo = (string) Str::uuid();
        $this->assertTrue($inbox->tryAcquire($delivery->fresh(), $tokenNuevo));
        $this->assertFalse($inbox->markProcessed($delivery->fresh(), $tokenViejo));
        $this->assertSame('processing', $delivery->fresh()->status);
        $this->assertSame($tokenNuevo, $delivery->fresh()->lease_token);
    }

    public function test_reintento_manual_reencola_sin_marcar_procesado(): void
    {
        Permission::findOrCreate('tiendanube.ver', 'web');
        Permission::findOrCreate('tiendanube.configurar', 'web');

        $user = User::factory()->create();
        $user->givePermissionTo(['tiendanube.ver', 'tiendanube.configurar']);

        $delivery = TiendanubeWebhookDelivery::query()->create([
            'store_id' => 8004291,
            'event' => 'product/updated',
            'resource_id' => '1948209',
            'payload' => ['store_id' => 8004291, 'event' => 'product/updated', 'id' => 1948209],
            'payload_hash' => hash('sha256', 'manual-retry'),
            'hmac_valid' => true,
            'status' => 'failed',
            'error' => 'fallo previo',
            'attempts' => 10,
        ]);

        Queue::fake();

        $this->actingAs($user)
            ->postJson(route('tiendanube.webhooks.entregas.reintentar', $delivery))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('entrega.status', 'queued');

        $fresh = $delivery->fresh();
        $this->assertSame('queued', $fresh->status);
        $this->assertSame(0, $fresh->attempts);
        $this->assertNull($fresh->error);
        $this->assertSame($user->id, $fresh->retried_by_user_id);
        Queue::assertPushed(ProcessTiendanubeWebhook::class);
        $this->assertNotSame('processed', $fresh->status);

        $this->assertDatabaseHas('auditorias_configuraciones', [
            'modulo' => 'Tiendanube',
            'accion' => 'Reintento entrega webhook',
        ]);
        $this->assertNotNull(AuditoriaConfiguracion::query()->where('accion', 'Reintento entrega webhook')->first());
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

    public function test_webhook_diferido_se_conserva_durante_sync_y_se_libera(): void
    {
        Queue::fake();

        app(TiendanubeOperacionTiendaService::class)->adquirirExclusiva(
            8004291,
            TiendanubeOperacionTiendaService::TIPO_CATALOGO_SYNC,
            1,
            1
        );

        $this->postSignedWebhook([
            'store_id' => 8004291,
            'event' => 'product/updated',
            'id' => 1948209,
        ])->assertOk();

        $delivery = TiendanubeWebhookDelivery::query()->firstOrFail();
        $this->assertSame(TiendanubeWebhookDelivery::STATUS_DEFERRED, $delivery->status);
        Queue::assertNothingPushed();

        app(TiendanubeOperacionTiendaService::class)->liberar(8004291, null, 1);
        app(TiendanubeWebhookInboxService::class)->liberarEntregasDiferidas(8004291);

        $this->assertSame(TiendanubeWebhookDelivery::STATUS_QUEUED, $delivery->fresh()->status);
        Queue::assertPushed(ProcessTiendanubeWebhook::class);
    }
}
