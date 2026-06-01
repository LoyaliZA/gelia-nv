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
}
