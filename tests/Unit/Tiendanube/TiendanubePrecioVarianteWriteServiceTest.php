<?php

namespace Tests\Unit\Tiendanube;

use App\Exceptions\Tiendanube\TiendanubePrecioEjecucionException;
use App\Models\Tiendanube\TiendanubeConfiguracion;
use App\Models\Tiendanube\TiendanubeProducto;
use App\Models\Tiendanube\TiendanubeProductoVariante;
use App\Services\Tiendanube\Precios\Aplicacion\TiendanubePrecioVarianteWriteService;
use App\Support\Tiendanube\Precios\TiendanubePrecioIntencion;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\Support\RefreshDatabaseSafe;
use Tests\TestCase;

class TiendanubePrecioVarianteWriteServiceTest extends TestCase
{
    use RefreshDatabaseSafe;

    private TiendanubePrecioVarianteWriteService $escritor;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'tiendanube.api_base' => null,
            'tiendanube.api_host' => 'https://api.tiendanube.com',
            'tiendanube.api_version' => '2025-03',
            'tiendanube.retry_sleep_ms' => 0,
        ]);
        TiendanubeConfiguracion::obtener()->fill([
            'store_id' => 8004291,
            'app_id' => '37163',
            'access_token' => Crypt::encryptString('token-test'),
            'config_generation' => 1,
        ])->save();
        $this->escritor = app(TiendanubePrecioVarianteWriteService::class);
    }

    public function test_payload_solo_admite_campos_de_precio(): void
    {
        $payload = $this->escritor->construirPayload([
            'normal' => ['intencion' => TiendanubePrecioIntencion::Establecer->value, 'valor_final' => '110.00'],
            'promocional' => ['intencion' => TiendanubePrecioIntencion::Eliminar->value],
            'costo_remoto' => ['intencion' => TiendanubePrecioIntencion::Conservar->value],
        ]);

        $this->assertSame(['price' => '110.00', 'promotional_price' => null], $payload);
        $this->assertArrayNotHasKey('stock', $payload);
        $this->assertArrayNotHasKey('sku', $payload);
        $this->assertArrayNotHasKey('cost', $payload);
    }

    public function test_rechaza_costo_cero(): void
    {
        $this->expectException(TiendanubePrecioEjecucionException::class);
        $this->escritor->construirPayload([
            'costo_remoto' => ['intencion' => TiendanubePrecioIntencion::Establecer->value, 'valor_final' => '0.00'],
        ]);
    }

    public function test_put_no_incluye_inventario_y_no_hidrata_respuesta_parcial(): void
    {
        $this->crearVariante();
        $puts = [];
        Http::fake(function (Request $request) use (&$puts) {
            if ($request->method() === 'PUT') {
                $puts[] = $request->data();

                return Http::response(['id' => 1000, 'price' => '110.00'], 200);
            }

            return Http::response($this->productoCompleto('110.00'), 200);
        });

        $payload = ['price' => '110.00'];
        $resultado = $this->escritor->escribir(8004291, 100, 1000, $payload);

        $this->assertCount(1, $puts);
        $this->assertSame(['price' => '110.00'], $puts[0]);
        $this->assertArrayNotHasKey('stock', $puts[0]);
        $this->assertTrue($resultado->espejoActualizado);
        $this->assertFalse($resultado->escrituraIncierta);
        $this->assertEquals(110.0, (float) TiendanubeProductoVariante::find(1000)?->getRawOriginal('price'));
        $this->assertEquals(5, (int) TiendanubeProductoVariante::find(1000)?->stock);
        $this->assertNotNull(TiendanubeProductoVariante::find(1001));
    }

    public function test_variante_ajena_no_escribe(): void
    {
        $this->crearVariante();
        Http::fake();
        $this->expectException(TiendanubePrecioEjecucionException::class);
        try {
            $this->escritor->escribir(8004291, 999, 1000, ['price' => '110.00']);
        } finally {
            Http::assertNothingSent();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function productoCompleto(string $precio): array
    {
        return [
            'id' => 100,
            'name' => ['es' => 'Producto 100'],
            'published' => true,
            'variants' => [
                [
                    'id' => 1000,
                    'sku' => 'SKU-A',
                    'price' => $precio,
                    'promotional_price' => null,
                    'cost' => '40.00',
                    'stock' => 5,
                    'stock_management' => true,
                ],
                [
                    'id' => 1001,
                    'sku' => 'SKU-B',
                    'price' => '80.00',
                    'promotional_price' => null,
                    'cost' => '20.00',
                    'stock' => 3,
                    'stock_management' => true,
                ],
            ],
        ];
    }

    private function crearVariante(): void
    {
        TiendanubeProducto::query()->create([
            'id' => 100,
            'name' => ['es' => 'Producto 100'],
            'published' => true,
            'synced_at' => now(),
        ]);
        foreach ([
            [1000, 'SKU-A', '100.00', 5],
            [1001, 'SKU-B', '80.00', 3],
        ] as [$id, $sku, $precio, $stock]) {
            TiendanubeProductoVariante::query()->create([
                'id' => $id,
                'producto_id' => 100,
                'sku' => $sku,
                'price' => $precio,
                'cost' => $id === 1000 ? '40.00' : '20.00',
                'stock' => $stock,
            ]);
        }
    }
}
