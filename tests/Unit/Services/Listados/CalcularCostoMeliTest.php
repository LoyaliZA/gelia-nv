<?php

namespace Tests\Unit\Services\Listados;

use App\Services\Listados\PorcentajesListadoService;
use PHPUnit\Framework\TestCase;

class CalcularCostoMeliTest extends TestCase
{
    public function test_formula_costo_full_y_msi_con_defaults_del_spec(): void
    {
        // Plataformas = 100 (base); defaults de mejoras_rapidas.md
        $plataformas = 100.0;
        $d = PorcentajesListadoService::MELI_DEFAULTS;

        $full = PorcentajesListadoService::calcularCostoMeli(
            $plataformas,
            $d['meli_factor_base'],
            $d['meli_full_multiplicador'],
            $d['meli_full_fijo_1'],
            $d['meli_full_fijo_2']
        );

        $msi = PorcentajesListadoService::calcularCostoMeli(
            $plataformas,
            $d['meli_factor_base'],
            $d['meli_msi_multiplicador'],
            $d['meli_msi_fijo_1'],
            $d['meli_msi_fijo_2']
        );

        // ((100 * 1.1) * 1.13) + 45 + 90 = 124.3 + 135 = 259.3
        $this->assertEqualsWithDelta(259.3, $full, 0.0001);

        // ((100 * 1.1) * 1.175) + 90 + 90 = 129.25 + 180 = 309.25
        $this->assertEqualsWithDelta(309.25, $msi, 0.0001);
    }
}
