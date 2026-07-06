<?php

namespace Tests\Unit\Rh;

use App\Support\MatrizHorarioTurno;
use PHPUnit\Framework\TestCase;

class MatrizHorarioTurnoTest extends TestCase
{
    public function test_defecto_usa_claves_en_espanol(): void
    {
        $matriz = MatrizHorarioTurno::defecto();

        $this->assertArrayHasKey('lunes', $matriz);
        $this->assertArrayHasKey('domingo', $matriz);
        $this->assertTrue($matriz['domingo']['descanso']);
        $this->assertSame('18:00', $matriz['lunes']['salida']);
        $this->assertSame('14:00', $matriz['sabado']['salida']);
    }

    public function test_normalizar_convierte_claves_numericas_legacy(): void
    {
        $legacy = [
            '1' => ['entrada' => '08:00', 'salida' => '17:00', 'descanso' => false],
            '6' => ['entrada' => '09:00', 'salida' => '13:00', 'descanso' => false],
            '0' => ['entrada' => '00:00', 'salida' => '00:00', 'descanso' => true],
        ];

        $normalizada = MatrizHorarioTurno::normalizar($legacy);

        $this->assertSame('17:00', $normalizada['lunes']['salida']);
        $this->assertSame('13:00', $normalizada['sabado']['salida']);
        $this->assertTrue($normalizada['domingo']['descanso']);
        $this->assertArrayHasKey('martes', $normalizada);
    }

    public function test_normalizar_preserva_claves_espanol(): void
    {
        $matriz = [
            'miercoles' => ['entrada' => '10:00', 'salida' => '19:00', 'horas' => 9, 'descanso' => false],
        ];

        $normalizada = MatrizHorarioTurno::normalizar($matriz);

        $this->assertSame('19:00', $normalizada['miercoles']['salida']);
        $this->assertSame('18:00', $normalizada['viernes']['salida']);
    }

    public function test_resolver_clave_dia(): void
    {
        $this->assertSame('lunes', MatrizHorarioTurno::resolverClaveDia('1'));
        $this->assertSame('domingo', MatrizHorarioTurno::resolverClaveDia('0'));
        $this->assertSame('viernes', MatrizHorarioTurno::resolverClaveDia('viernes'));
        $this->assertNull(MatrizHorarioTurno::resolverClaveDia('invalido'));
    }
}
