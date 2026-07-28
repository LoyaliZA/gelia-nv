<?php

namespace Tests\Unit\Support;

use App\Support\Facturas\ReglasCatalogosFiscales;
use PHPUnit\Framework\TestCase;

class NormalizarRazonSocialTest extends TestCase
{
    public function test_quita_acentos_mayusculas_y_trim(): void
    {
        $this->assertSame(
            'JOSE PEREZ',
            ReglasCatalogosFiscales::normalizarRazonSocial('  José Pérez  ')
        );
    }

    public function test_conserva_enie(): void
    {
        $this->assertSame(
            'NIÑO SA',
            ReglasCatalogosFiscales::normalizarRazonSocial('Niño SA')
        );
        $this->assertSame(
            'NIÑO SA',
            ReglasCatalogosFiscales::normalizarRazonSocial('NIÑO SA')
        );
    }

    public function test_colapsa_espacios(): void
    {
        $this->assertSame(
            'EMPRESA SA DE CV',
            ReglasCatalogosFiscales::normalizarRazonSocial('Empresa   SA   de   CV')
        );
    }
}
