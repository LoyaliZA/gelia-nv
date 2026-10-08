<?php

namespace Tests\Unit\Support;

use App\Support\NormalizarFondoBase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class NormalizarFondoBaseTest extends TestCase
{
    #[DataProvider('hexFondoSistemaProvider')]
    public function test_normaliza_hex_blanco_negro_a_none(string $hex): void
    {
        $this->assertSame('none', NormalizarFondoBase::aplicar($hex));
    }

    public static function hexFondoSistemaProvider(): array
    {
        return [
            ['#ffffff'],
            ['#FFFFFF'],
            ['#fff'],
            ['#000000'],
            ['#0a0a0a'],
        ];
    }

    public function test_conserva_hex_personalizado_y_vectores(): void
    {
        $this->assertSame('#1e293b', NormalizarFondoBase::aplicar('#1e293b'));
        $this->assertSame('blob', NormalizarFondoBase::aplicar('blob'));
    }
}
