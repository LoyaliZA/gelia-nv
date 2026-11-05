<?php

namespace Tests\Unit\Productos;

use App\Services\Productos\CalcularPrecioPublicoService;
use InvalidArgumentException;
use stdClass;
use Tests\TestCase;

class CalcularPrecioPublicoServiceTest extends TestCase
{
    public function test_publico_general_usa_costo_promedio_y_margen(): void
    {
        $svc = new CalcularPrecioPublicoService;

        $this->assertSame('130.00', $svc->calcular(100, 30));
        $this->assertSame('115.50', $svc->calcular(100, 15.5));
        $this->assertNull($svc->calcular(null, 30));
        $this->assertNull($svc->calcular(100, null));
        $this->assertNull($svc->calcular('', 30));

        $this->expectException(InvalidArgumentException::class);
        $svc->calcular(100, 101);
    }

    public function test_elige_cedis_o_el_primer_costo_distinto_de_cero(): void
    {
        $svc = new CalcularPrecioPublicoService;
        $cero = $this->fila(1, 0, 'VTA', 'Ventas');
        $cedis = $this->fila(4, 10, 'CEDIS', 'Central');
        $otro = $this->fila(2, 8, 'CC', 'City Center');

        $this->assertSame(4, (int) $svc->elegirFilaCosto([$otro, $cero, $cedis])->id);
        $this->assertSame(2, (int) $svc->elegirFilaCosto([$cero, $otro])->id);
        $this->assertSame(1, (int) $svc->elegirFilaCosto([$cero])->id);
    }

    private function fila(int $id, float $costo, string $codigo, string $nombre): stdClass
    {
        $fila = new stdClass;
        $fila->id = $id;
        $fila->costo = $costo;
        $fila->codigo = $codigo;
        $fila->nombre = $nombre;

        return $fila;
    }
}
