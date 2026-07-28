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

    public function test_rfc_persona_fisica_requiere_13(): void
    {
        $this->assertNull(ReglasCatalogosFiscales::errorRfc('XAXX010101000'));
        $this->assertSame(
            'Persona física: el RFC debe tener 13 caracteres.',
            ReglasCatalogosFiscales::errorRfc('XAXX01010100')
        );
    }

    public function test_rfc_empresa_requiere_12(): void
    {
        $this->assertNull(ReglasCatalogosFiscales::errorRfc('ABC010101AAA'));
        $this->assertSame(
            'Empresa: el RFC debe tener 12 caracteres.',
            ReglasCatalogosFiscales::errorRfc('ABC010101AAAA')
        );
    }

    public function test_rfc_longitud_incompleta(): void
    {
        $this->assertSame(
            'El RFC debe tener 12 caracteres (empresa) o 13 (persona física).',
            ReglasCatalogosFiscales::errorRfc('ABC010101')
        );
    }
}
