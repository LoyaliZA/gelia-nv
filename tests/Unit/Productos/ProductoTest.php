<?php

namespace Tests\Unit\Productos;

use App\Models\Producto;
use PHPUnit\Framework\TestCase;

class ProductoTest extends TestCase
{
    public function test_normaliza_sku_eliminando_ceros_a_la_izquierda(): void
    {
        $this->assertSame('12345', Producto::normalizarSku('00012345'));
        $this->assertSame('0', Producto::normalizarSku('000'));
        $this->assertSame('ABC', Producto::normalizarSku('  ABC  '));
    }

    public function test_tokens_busqueda_compacta_ml_y_omite_particulas(): void
    {
        $this->assertSame(
            ['armaf', 'mandarin', 'sky', '100ml'],
            Producto::tokensBusqueda('Armaf mandarin Sky de 100 ml')
        );
        $this->assertSame(['zeta'], Producto::tokensBusqueda('Zeta'));
        $this->assertSame([], Producto::tokensBusqueda(' de  '));
    }
}
