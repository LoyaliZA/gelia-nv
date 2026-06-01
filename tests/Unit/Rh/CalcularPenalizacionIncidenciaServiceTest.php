<?php

namespace Tests\Unit\Rh;

use App\Models\CatalogoTipoFalta;
use App\Models\RhColaborador;
use App\Services\Rh\CalcularPenalizacionIncidenciaService;
use PHPUnit\Framework\TestCase;

class CalcularPenalizacionIncidenciaServiceTest extends TestCase
{
    private CalcularPenalizacionIncidenciaService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CalcularPenalizacionIncidenciaService();
    }

    public function test_falta_injustificada_aplica_factores_y_salario(): void
    {
        $colaborador = $this->colaborador(salario: 300, bonoPunt: 100, bonoProd: 80);
        $tipo = $this->tipo(punt: 15, prod: 15, deduceSalario: true);

        $resultado = $this->service->ejecutar($colaborador, $tipo);

        $this->assertSame(300.0, $resultado['deduccion_salario_base']);
        $this->assertSame(1500.0, $resultado['deduccion_bono_puntualidad']);
        $this->assertSame(1200.0, $resultado['deduccion_bono_productividad']);
        $this->assertSame(3000, $resultado['total_deduccion']);
        $this->assertSame('pendiente', $resultado['estado_deduccion']);
    }

    public function test_permiso_sin_goce_solo_deduce_salario(): void
    {
        $colaborador = $this->colaborador(salario: 250, bonoPunt: 50, bonoProd: 40);
        $tipo = $this->tipo(punt: 0, prod: 0, deduceSalario: true);

        $resultado = $this->service->ejecutar($colaborador, $tipo);

        $this->assertSame(250.0, $resultado['deduccion_salario_base']);
        $this->assertSame(0.0, $resultado['deduccion_bono_puntualidad']);
        $this->assertSame(0.0, $resultado['deduccion_bono_productividad']);
        $this->assertSame(250, $resultado['total_deduccion']);
    }

    public function test_retardo_menor_solo_penaliza_puntualidad_parcial(): void
    {
        $colaborador = $this->colaborador(salario: 200, bonoPunt: 100, bonoProd: 60);
        $tipo = $this->tipo(punt: 0.5, prod: 0, deduceSalario: false);

        $resultado = $this->service->ejecutar($colaborador, $tipo);

        $this->assertSame(0.0, $resultado['deduccion_salario_base']);
        $this->assertSame(50.0, $resultado['deduccion_bono_puntualidad']);
        $this->assertSame(0.0, $resultado['deduccion_bono_productividad']);
        $this->assertSame(50, $resultado['total_deduccion']);
    }

    public function test_total_se_redondea_a_entero(): void
    {
        $colaborador = $this->colaborador(salario: 100.33, bonoPunt: 33.33, bonoProd: 0);
        $tipo = $this->tipo(punt: 1, prod: 0, deduceSalario: false);

        $resultado = $this->service->ejecutar($colaborador, $tipo);

        $this->assertSame(33, $resultado['total_deduccion']);
    }

    public function test_fecha_deduccion_programa_estado(): void
    {
        $colaborador = $this->colaborador();
        $tipo = $this->tipo();

        $resultado = $this->service->ejecutar($colaborador, $tipo, '2026-06-15');

        $this->assertSame('programado', $resultado['estado_deduccion']);
    }

    private function colaborador(float $salario = 300, float $bonoPunt = 100, float $bonoProd = 80): RhColaborador
    {
        $colaborador = new RhColaborador();
        $colaborador->forceFill([
            'salario_diario' => $salario,
            'bono_puntualidad_diario' => $bonoPunt,
            'bono_productividad_diario' => $bonoProd,
        ]);

        return $colaborador;
    }

    private function tipo(float $punt = 0, float $prod = 0, bool $deduceSalario = false): CatalogoTipoFalta
    {
        $tipo = new CatalogoTipoFalta();
        $tipo->forceFill([
            'nombre' => 'Tipo prueba',
            'factor_penalizacion_puntualidad' => $punt,
            'factor_penalizacion_productividad' => $prod,
            'aplica_deduccion_salario_base' => $deduceSalario,
        ]);

        return $tipo;
    }
}
