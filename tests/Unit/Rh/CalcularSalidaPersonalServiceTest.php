<?php

namespace Tests\Unit\Rh;

use App\Models\RhColaborador;
use App\Services\Rh\CalcularSalidaPersonalService;
use PHPUnit\Framework\TestCase;

class CalcularSalidaPersonalServiceTest extends TestCase
{
    private CalcularSalidaPersonalService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CalcularSalidaPersonalService();
    }

    public function test_retorna_ceros_si_faltan_horas(): void
    {
        $colaborador = $this->colaborador(0.5);

        $resultado = $this->service->ejecutar([
            'hora_salida' => '08:00',
            'hora_regreso' => '',
        ], $colaborador);

        $this->assertSame(0, $resultado['minutos_ausente']);
        $this->assertSame(0, $resultado['monto_a_deducir']);
        $this->assertEquals(0.5, $resultado['salario_por_minuto_snapshot']);
    }

    public function test_calcula_minutos_ausente_y_monto_deduccion(): void
    {
        $colaborador = $this->colaborador(1.25); // $1.25 por minuto

        $resultado = $this->service->ejecutar([
            'fecha_evento' => '2026-06-02',
            'hora_salida' => '10:00',
            'hora_regreso' => '11:15', // 75 minutos
        ], $colaborador);

        $this->assertSame(75, $resultado['minutos_ausente']);
        $this->assertSame(94, $resultado['monto_a_deducir']); // 75 * 1.25 = 93.75 -> round() = 94
        $this->assertEquals(1.25, $resultado['salario_por_minuto_snapshot']);
    }

    public function test_calcula_cruce_de_medianoche(): void
    {
        $colaborador = $this->colaborador(1.0); // $1.0 por minuto

        $resultado = $this->service->ejecutar([
            'fecha_evento' => '2026-06-02',
            'hora_salida' => '23:30',
            'hora_regreso' => '01:15', // Cruce de medianoche, 1 hora 45 minutos = 105 minutos
        ], $colaborador);

        $this->assertSame(105, $resultado['minutos_ausente']);
        $this->assertSame(105, $resultado['monto_a_deducir']);
    }

    private function colaborador(float $salarioPorMinuto): RhColaborador
    {
        $colaborador = new RhColaborador();
        $colaborador->forceFill([
            'salario_por_minuto' => $salarioPorMinuto,
        ]);

        return $colaborador;
    }
}
