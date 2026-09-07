<?php

namespace Tests\Unit\PuntoVenta\Reportes;

use App\Support\PuntoVenta\Reportes\PercentilesPdv;
use Tests\TestCase;

class PercentilesPdvTest extends TestCase
{
    public function test_no_publica_percentiles_con_muestra_insuficiente(): void
    {
        $duraciones = range(1, 12);

        $this->assertNull(PercentilesPdv::calcular($duraciones));
    }

    public function test_calcula_percentiles_con_interpolacion_lineal(): void
    {
        $duraciones = range(1, 30);

        $percentiles = PercentilesPdv::calcular($duraciones);

        $this->assertNotNull($percentiles);
        $this->assertSame(16, $percentiles['p50']);
        $this->assertSame(27, $percentiles['p90']);
        $this->assertSame(29, $percentiles['p95']);
    }

    public function test_percentiles_usan_solo_poblacion_cerrada(): void
    {
        $duraciones = array_merge(range(100, 129), range(200, 209));

        $percentiles = PercentilesPdv::calcular($duraciones);

        $this->assertNotNull($percentiles);
        $this->assertGreaterThanOrEqual(100, $percentiles['p50']);
        $this->assertLessThanOrEqual(209, $percentiles['p95']);
    }
}
