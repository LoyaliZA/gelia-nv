<?php

namespace Tests\Feature\WooCommerce;

use App\Jobs\WooCommerce\UpdateWooCommercePricesJob;
use App\Models\Woocommerce\WoocommerceConfiguracion;
use App\Models\Woocommerce\WoocommerceMargin;
use App\Models\Woocommerce\WoocommerceProduct;
use App\Models\Woocommerce\WoocommerceSyncLog;
use App\Services\WooCommerce\WooCommercePreciosService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use ReflectionProperty;
use Tests\Support\RefreshDatabaseSafe;
use Tests\TestCase;

class UpdateWooCommercePricesJobTest extends TestCase
{
    use RefreshDatabaseSafe;

    protected function setUp(): void
    {
        parent::setUp();

        WoocommerceConfiguracion::obtener()->update([
            'store_url' => 'https://tienda.example',
            'iva' => 1.16,
            'consumer_key' => Crypt::encryptString('ck_test_key'),
            'consumer_secret' => Crypt::encryptString('cs_test_secret'),
            'integration_token' => Crypt::encryptString('tok_identificacion'),
        ]);
    }

    public function test_una_ejecucion_envia_un_lote_y_difiere_el_resto(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://tienda.example/*' => Http::response(['update' => []], 200),
        ]);
        Bus::fake();

        $servicio = app(WooCommercePreciosService::class);
        $margenes = WoocommerceMargin::orderBy('precio_min')->get();
        $normal = $servicio->calcular(50, 'normal', $margenes, 1.16);
        $rebaja = $servicio->calcular(50, 'rebaja', $margenes, 1.16);

        WoocommerceProduct::create([
            'id' => 1000,
            'sku' => 'SKU-IGUAL',
            'nombre' => 'Sin cambio',
            'precio_normal' => $normal,
            'precio_rebajado' => $rebaja,
            'tipo' => 'simple',
        ]);

        $payload = ['SKU-IGUAL' => 50];
        for ($i = 1; $i <= 12; $i++) {
            WoocommerceProduct::create([
                'id' => 1000 + $i,
                'sku' => 'SKU-'.$i,
                'nombre' => 'Producto '.$i,
                'precio_normal' => 1,
                'precio_rebajado' => 1,
                'tipo' => 'simple',
            ]);
            $payload['SKU-'.$i] = 50;
        }

        $log = WoocommerceSyncLog::create([
            'tipo' => 'upload_prices',
            'total_productos' => count($payload),
            'procesados' => 0,
            'estado' => 'pendiente',
            'payload' => $payload,
        ]);

        (new UpdateWooCommercePricesJob($log->id, 0))->handle();

        Http::assertSentCount(1);
        Http::assertSent(function ($request) {
            $update = $request->data()['update'] ?? [];
            $ids = array_column($update, 'id');
            $url = $request->url();

            $this->assertSame('POST', $request->method());
            $this->assertCount(10, $update);
            $this->assertNotContains(1000, $ids);
            $this->assertStringContainsString('/products/batch', $url);
            $this->assertStringNotContainsString('consumer_key', $url);
            $this->assertStringNotContainsString('consumer_secret', $url);
            $this->assertSame('Basic '.base64_encode('ck_test_key:cs_test_secret'), $request->header('Authorization')[0] ?? '');
            $this->assertSame('tok_identificacion', $request->header('X-Gelia-Integration-Token')[0] ?? '');

            return true;
        });

        Bus::assertDispatched(UpdateWooCommercePricesJob::class, function (UpdateWooCommercePricesJob $job) {
            $cuando = Carbon::parse($job->delay);
            $this->assertTrue($cuando->between(
                now()->subSeconds(2)->addSeconds(UpdateWooCommercePricesJob::PAUSA_MIN_SEGUNDOS),
                now()->addSeconds(UpdateWooCommercePricesJob::PAUSA_MAX_SEGUNDOS + 1)
            ));

            $offset = new ReflectionProperty($job, 'offset');
            $this->assertSame(11, $offset->getValue($job));

            return true;
        });
    }

    public function test_sku_sin_cambio_no_dispara_http(): void
    {
        Http::preventStrayRequests();
        Http::fake();
        Bus::fake();

        $servicio = app(WooCommercePreciosService::class);
        $margenes = WoocommerceMargin::orderBy('precio_min')->get();
        $normal = $servicio->calcular(50, 'normal', $margenes, 1.16);
        $rebaja = $servicio->calcular(50, 'rebaja', $margenes, 1.16);

        WoocommerceProduct::create([
            'id' => 2000,
            'sku' => 'SKU-IGUAL',
            'nombre' => 'Sin cambio',
            'precio_normal' => $normal,
            'precio_rebajado' => $rebaja,
            'tipo' => 'simple',
        ]);

        $log = WoocommerceSyncLog::create([
            'tipo' => 'upload_prices',
            'total_productos' => 1,
            'procesados' => 0,
            'estado' => 'pendiente',
            'payload' => ['SKU-IGUAL' => 50],
        ]);

        (new UpdateWooCommercePricesJob($log->id, 0))->handle();

        Http::assertNothingSent();
        Bus::assertNothingDispatched();
        $this->assertSame('completado', $log->fresh()->estado);
    }
}
