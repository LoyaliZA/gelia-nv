<?php

namespace Tests\Unit\Tiendanube;

use App\Models\Tiendanube\TiendanubeProducto;
use App\Models\Tiendanube\TiendanubeProductoVariante;
use App\Services\Tiendanube\TiendanubeImageSkuParser;
use App\Services\Tiendanube\TiendanubeImageSkuResolverService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TiendanubeImageSkuResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_parser_sku_con_guion_bajo_y_posicion(): void
    {
        $this->assertSame(
            ['sku' => 'FOO_BAR', 'position' => 3, 'extension' => 'jpg'],
            TiendanubeImageSkuParser::parse('FOO_BAR_3.jpg')
        );
    }

    public function test_sku_en_dos_productos_es_ambiguo(): void
    {
        TiendanubeProducto::create(['id' => 10, 'name' => ['es' => 'A'], 'published' => true]);
        TiendanubeProducto::create(['id' => 20, 'name' => ['es' => 'B'], 'published' => true]);
        TiendanubeProductoVariante::create(['id' => 1, 'producto_id' => 10, 'sku' => 'DUP', 'price' => 1]);
        TiendanubeProductoVariante::create(['id' => 2, 'producto_id' => 20, 'sku' => 'DUP', 'price' => 1]);

        $res = app(TiendanubeImageSkuResolverService::class)->resolver('DUP');

        $this->assertSame('ambiguo', $res['estado']);
        $this->assertNull($res['producto_id']);
        $this->assertCount(2, $res['candidatos']);
    }

    public function test_sku_en_variantes_del_mismo_producto_es_encontrado(): void
    {
        TiendanubeProducto::create(['id' => 11, 'name' => ['es' => 'Uno'], 'published' => true]);
        TiendanubeProductoVariante::create(['id' => 3, 'producto_id' => 11, 'sku' => 'SAME', 'price' => 1]);
        TiendanubeProductoVariante::create(['id' => 4, 'producto_id' => 11, 'sku' => 'SAME', 'price' => 1]);

        $res = app(TiendanubeImageSkuResolverService::class)->resolver('SAME');

        $this->assertSame('encontrado', $res['estado']);
        $this->assertSame(11, $res['producto_id']);
        $this->assertCount(1, $res['candidatos']);
    }

    public function test_preserva_ceros_iniciales(): void
    {
        TiendanubeProducto::create(['id' => 12, 'name' => ['es' => 'Cero'], 'published' => true]);
        TiendanubeProductoVariante::create(['id' => 5, 'producto_id' => 12, 'sku' => '00123', 'price' => 1]);

        $res = app(TiendanubeImageSkuResolverService::class)->resolver('00123');

        $this->assertSame('encontrado', $res['estado']);
        $this->assertSame('00123', $res['sku']);
        $this->assertSame(12, $res['producto_id']);

        $numerico = app(TiendanubeImageSkuResolverService::class)->resolver('123');
        $this->assertSame('no_encontrado', $numerico['estado']);
    }
}
