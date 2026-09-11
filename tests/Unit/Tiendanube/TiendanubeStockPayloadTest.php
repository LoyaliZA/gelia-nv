<?php

namespace Tests\Unit\Tiendanube;

use App\Exceptions\Tiendanube\TiendanubeStockPayloadException;
use App\Models\Tiendanube\TiendanubeConfiguracion;
use App\Models\Tiendanube\TiendanubeProducto;
use App\Models\Tiendanube\TiendanubeProductoVariante;
use App\Models\Tiendanube\TiendanubeUbicacion;
use App\Models\Tiendanube\TiendanubeVarianteNivel;
use App\Services\Tiendanube\TiendanubeStockPayload;
use Tests\Support\RefreshDatabaseSafe;
use Tests\TestCase;

class TiendanubeStockPayloadTest extends TestCase
{
    use RefreshDatabaseSafe;

    private const LOC_A = '01GQ2ZHK064BQRHGDB7CCV0Y6N';

    private const LOC_AJENA = '01AJENAAAAAAAAAAAAAAA00000';

    protected function setUp(): void
    {
        parent::setUp();

        TiendanubeConfiguracion::obtener()->fill([
            'store_id' => 8004291,
            'locations_probe' => 'ok',
            'multi_inventario_activo' => true,
        ])->save();

        TiendanubeProducto::query()->create([
            'id' => 100,
            'name' => ['es' => 'Demo'],
            'published' => true,
        ]);
        TiendanubeProductoVariante::query()->create([
            'id' => 900,
            'producto_id' => 100,
            'sku' => 'SKU-1',
            'stock' => 10,
            'stock_management' => true,
        ]);
        TiendanubeUbicacion::query()->create([
            'id' => self::LOC_A,
            'store_id' => 8004291,
            'name' => ['es' => 'A'],
            'activa' => true,
            'synced_at' => now(),
        ]);
        TiendanubeVarianteNivel::query()->create([
            'variante_id' => 900,
            'ubicacion_id' => self::LOC_A,
            'stock' => 3,
            'synced_at' => now(),
        ]);
    }

    public function test_payload_valido_serializa(): void
    {
        $payload = TiendanubeStockPayload::fromArray([
            'store_id' => 8004291,
            'product_id' => 100,
            'variant_id' => 900,
            'location_id' => self::LOC_A,
            'stock' => 7,
            'stock_management' => true,
            'mode' => 'set_level',
        ]);
        $payload->validate();

        $this->assertSame(self::LOC_A, $payload->toArray()['location_id']);
        $this->assertSame(7, $payload->toArray()['stock']);
        $this->assertSame([
            'inventory_levels' => [[
                'location_id' => self::LOC_A,
                'stock' => 7,
            ]],
        ], $payload->toVariantApiPayload());
    }

    public function test_set_stock_management_no_incluye_niveles(): void
    {
        $payload = TiendanubeStockPayload::fromArray([
            'store_id' => 8004291,
            'product_id' => 100,
            'variant_id' => 900,
            'stock_management' => false,
            'mode' => 'set_stock_management',
        ]);
        $payload->validate();

        $this->assertSame(['stock_management' => false], $payload->toVariantApiPayload());
    }

    public function test_set_level_incluye_stock_null(): void
    {
        $payload = TiendanubeStockPayload::fromArray([
            'store_id' => 8004291,
            'product_id' => 100,
            'variant_id' => 900,
            'location_id' => self::LOC_A,
            'stock' => null,
            'mode' => 'set_level',
        ]);
        $payload->validate();
        $api = $payload->toVariantApiPayload();
        $this->assertArrayHasKey('stock', $api['inventory_levels'][0]);
        $this->assertNull($api['inventory_levels'][0]['stock']);
    }

    public function test_rechaza_ubicacion_ajena(): void
    {
        $payload = TiendanubeStockPayload::fromArray([
            'store_id' => 8004291,
            'product_id' => 100,
            'variant_id' => 900,
            'location_id' => self::LOC_AJENA,
            'stock' => 1,
            'stock_management' => true,
            'mode' => 'set_level',
        ]);

        try {
            $payload->validate();
            $this->fail('Debió rechazar ubicación ajena');
        } catch (TiendanubeStockPayloadException $e) {
            $this->assertSame('ubicacion_invalida', $e->codigo);
        }
    }

    public function test_rechaza_variante_de_otro_producto(): void
    {
        $payload = TiendanubeStockPayload::fromArray([
            'store_id' => 8004291,
            'product_id' => 999,
            'variant_id' => 900,
            'location_id' => self::LOC_A,
            'stock' => 1,
            'mode' => 'set_level',
        ]);

        try {
            $payload->validate();
            $this->fail('Debió rechazar variante ajena');
        } catch (TiendanubeStockPayloadException $e) {
            $this->assertSame('variante_invalida', $e->codigo);
        }
    }
}
