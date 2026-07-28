<?php

namespace Tests\Unit\Support\Facturas;

use App\Support\Facturas\ReglasCatalogosFiscales;
use PHPUnit\Framework\TestCase;

class ReglasCatalogosFiscalesTest extends TestCase
{
    public function test_sueldos_fuerza_sin_efectos_fiscales(): void
    {
        $this->assertSame(
            'S01',
            ReglasCatalogosFiscales::usoForzadoPorRegimen('605')
        );

        $aplicado = ReglasCatalogosFiscales::aplicarForzados([
            'regimen_fiscal' => '605',
            'uso_factura' => 'G03',
        ]);

        $this->assertSame('S01', $aplicado['uso_factura']);
    }

    public function test_otros_regimenes_no_fuerzan_uso(): void
    {
        $this->assertNull(ReglasCatalogosFiscales::usoForzadoPorRegimen('626'));

        $aplicado = ReglasCatalogosFiscales::aplicarForzados([
            'regimen_fiscal' => '626',
            'uso_factura' => 'G03',
        ]);

        $this->assertSame('G03', $aplicado['uso_factura']);
    }
}
