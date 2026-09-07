<?php

namespace Tests\Unit\Services\Listados;

use App\Services\Listados\PorcentajesListadoService;
use PHPUnit\Framework\TestCase;

class CalcularCostoMeliTest extends TestCase
{
    public function test_formula_costo_full_y_msi_con_defaults_del_spec(): void
    {
        $plataformas = 100.0;
        $d = PorcentajesListadoService::MELI_DEFAULTS;

        $full = PorcentajesListadoService::calcularCostoMeli(
            $plataformas,
            $d['meli_full_fijo_1'],
            $d['meli_full_fijo_2'],
            $d['meli_full_pct_1'],
            $d['meli_full_pct_2'],
            $d['meli_pct_iva'],
            $d['meli_factor_iva']
        );

        $msi = PorcentajesListadoService::calcularCostoMeli(
            $plataformas,
            $d['meli_msi_fijo_1'],
            $d['meli_msi_fijo_2'],
            $d['meli_msi_pct_1'],
            $d['meli_msi_pct_2'],
            $d['meli_pct_iva'],
            $d['meli_factor_iva']
        );

        // (100 + 45 + 90) / (1 - 14% - 8% - 2.5%/1.16)
        $this->assertEqualsWithDelta(309.84, $full, 0.01);

        // (100 + 90 + 90) / (1 - 17.5% - 8% - 2.5%/1.16)
        $this->assertEqualsWithDelta(387.03, $msi, 0.01);
    }

    public function test_retorna_cero_si_denominador_no_es_positivo(): void
    {
        $resultado = PorcentajesListadoService::calcularCostoMeli(
            100.0,
            45.0,
            90.0,
            90.0,
            8.0,
            2.5,
            1.16
        );

        $this->assertSame(0.0, $resultado);
    }
}
