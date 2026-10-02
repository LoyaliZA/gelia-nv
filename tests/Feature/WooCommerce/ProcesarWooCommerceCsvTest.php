<?php

namespace Tests\Feature\WooCommerce;

use App\Models\User;
use App\Models\Woocommerce\WoocommerceMargin;
use App\Models\Woocommerce\WoocommerceProduct;
use App\Models\Woocommerce\WoocommerceSyncLog;
use App\Models\Woocommerce\WoocommerceTemplate;
use App\Services\WooCommerce\WooCommercePreciosService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Storage;
use Rap2hpoutre\FastExcel\FastExcel;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\RefreshDatabaseSafe;
use Tests\TestCase;

class ProcesarWooCommerceCsvTest extends TestCase
{
    use RefreshDatabaseSafe;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        Permission::findOrCreate('woocommerce.sincronizar', 'web');
        Permission::findOrCreate('woocommerce.ver', 'web');

        $this->user = User::factory()->create();
        $this->user->givePermissionTo(['woocommerce.sincronizar', 'woocommerce.ver']);
        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    public function test_procesar_solo_exporta_cambios_y_actualiza_bd_local(): void
    {
        $servicio = app(WooCommercePreciosService::class);
        $margenes = WoocommerceMargin::orderBy('precio_min')->get();
        $iva = 1.16;
        $normal = $servicio->calcular(100, 'normal', $margenes, $iva);
        $rebaja = $servicio->calcular(100, 'rebaja', $margenes, $iva);

        WoocommerceProduct::create([
            'id' => 5001,
            'sku' => 'SKU-SIN-CAMBIO',
            'nombre' => 'Igual',
            'precio_normal' => $normal,
            'precio_rebajado' => $rebaja,
            'tipo' => 'simple',
        ]);

        WoocommerceProduct::create([
            'id' => 5002,
            'sku' => 'SKU-CAMBIO',
            'nombre' => 'Cambiar',
            'precio_normal' => 1,
            'precio_rebajado' => 1,
            'tipo' => 'simple',
        ]);

        $excelPath = storage_path('app/temp_woo_procesar_test.xlsx');
        if (! is_dir(dirname($excelPath))) {
            mkdir(dirname($excelPath), 0777, true);
        }
        (new FastExcel(collect([
            ['SKU' => 'SKU-SIN-CAMBIO', 'Plataformas' => 100],
            ['SKU' => 'SKU-CAMBIO', 'Plataformas' => 100],
        ])))->export($excelPath);

        $stored = 'temp/woo_test_' . uniqid() . '.xlsx';
        Storage::put($stored, file_get_contents($excelPath));
        @unlink($excelPath);

        $response = $this->actingAs($this->user)->postJson(route('woocommerce.procesar'), [
            'file_path' => $stored,
            'mapping' => ['sku' => 'SKU', 'precio_base' => 'Plataformas'],
            'columnas_export' => ['sku', 'precio_normal', 'precio_rebajado'],
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['log_id']);

        $log = WoocommerceSyncLog::findOrFail($response->json('log_id'));
        $this->assertSame('completado', $log->estado);
        $this->assertSame(1, $log->payload['resultado']['productos_exportados'] ?? null);
        $this->assertSame(1, $log->payload['resultado']['productos_actualizados_local'] ?? null);

        $producto = WoocommerceProduct::where('sku', 'SKU-CAMBIO')->first();
        $this->assertSame($normal, (float) $producto->precio_normal);
        $this->assertSame($rebaja, (float) $producto->precio_rebajado);

        $template = WoocommerceTemplate::query()->latest('id')->first();
        $this->assertNotNull($template);
        $contenido = Storage::disk('public')->get($template->ruta_fisica);
        $this->assertStringContainsString('SKU-CAMBIO', $contenido);
        $this->assertStringNotContainsString('SKU-SIN-CAMBIO', $contenido);
    }

    public function test_procesar_sin_cambios_devuelve_error(): void
    {
        $servicio = app(WooCommercePreciosService::class);
        $margenes = WoocommerceMargin::orderBy('precio_min')->get();
        $normal = $servicio->calcular(80, 'normal', $margenes, 1.16);
        $rebaja = $servicio->calcular(80, 'rebaja', $margenes, 1.16);

        WoocommerceProduct::create([
            'id' => 5010,
            'sku' => 'SKU-OK',
            'nombre' => 'Ok',
            'precio_normal' => $normal,
            'precio_rebajado' => $rebaja,
            'tipo' => 'simple',
        ]);

        $excelPath = storage_path('app/temp_woo_sin_cambio.xlsx');
        (new FastExcel(collect([
            ['SKU' => 'SKU-OK', 'Plataformas' => 80],
        ])))->export($excelPath);

        $stored = 'temp/woo_sin_cambio_' . uniqid() . '.xlsx';
        Storage::put($stored, file_get_contents($excelPath));
        @unlink($excelPath);

        $response = $this->actingAs($this->user)->postJson(route('woocommerce.procesar'), [
            'file_path' => $stored,
            'mapping' => ['sku' => 'SKU', 'precio_base' => 'Plataformas'],
        ]);

        $response->assertOk()->assertJsonPath('success', true);
        $log = WoocommerceSyncLog::findOrFail($response->json('log_id'));
        $this->assertSame('error', $log->estado);
        $this->assertStringContainsString('No hay cambios', $log->mensaje_error ?? '');
    }
}
