<?php

namespace Tests\Feature\WooCommerce;

use App\Models\User;
use App\Models\Woocommerce\WoocommerceConfiguracion;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\RefreshDatabaseSafe;
use Tests\TestCase;

class ProbarConexionWooCommerceTest extends TestCase
{
    use RefreshDatabaseSafe;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (['woocommerce.ver', 'woocommerce.configurar'] as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }

        $this->user = User::factory()->create();
        $this->user->givePermissionTo(['woocommerce.ver', 'woocommerce.configurar']);
        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    public function test_envia_basic_auth_y_token_sin_credenciales_en_la_url(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://tienda.example/*' => Http::response([['id' => 1, 'sku' => 'A']], 200),
        ]);

        $this->actingAs($this->user)
            ->postJson(route('woocommerce.configuracion.probar_conexion'), [
                'store_url' => 'https://tienda.example',
                'consumer_key' => 'ck_test_key',
                'consumer_secret' => 'cs_test_secret',
                'integration_token' => 'tok_identificacion',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        Http::assertSent(function ($request) {
            $auth = $request->header('Authorization')[0] ?? '';
            $token = $request->header('X-Gelia-Integration-Token')[0] ?? '';
            $url = $request->url();

            $this->assertSame('Basic '.base64_encode('ck_test_key:cs_test_secret'), $auth);
            $this->assertSame('tok_identificacion', $token);
            $this->assertSame('Gelia/1.0 (sincronizacion-precios)', $request->header('User-Agent')[0] ?? '');
            $this->assertStringNotContainsString('consumer_key', $url);
            $this->assertStringNotContainsString('consumer_secret', $url);
            $this->assertStringNotContainsString('ck_test_key', $url);
            $this->assertStringNotContainsString('cs_test_secret', $url);

            return true;
        });
    }

    public function test_url_http_no_dispara_peticion(): void
    {
        Http::preventStrayRequests();
        Http::fake();

        $this->actingAs($this->user)
            ->postJson(route('woocommerce.configuracion.probar_conexion'), [
                'store_url' => 'http://tienda.example',
                'consumer_key' => 'ck_test_key',
                'consumer_secret' => 'cs_test_secret',
            ])
            ->assertStatus(422);

        Http::assertNothingSent();
    }

    public function test_url_http_guardada_no_dispara_peticion(): void
    {
        Http::preventStrayRequests();
        Http::fake();

        WoocommerceConfiguracion::obtener()->update([
            'store_url' => 'http://tienda.example',
        ]);

        $this->actingAs($this->user)
            ->postJson(route('woocommerce.configuracion.probar_conexion'), [
                'consumer_key' => 'ck_test_key',
                'consumer_secret' => 'cs_test_secret',
            ])
            ->assertStatus(422);

        Http::assertNothingSent();
    }
}
