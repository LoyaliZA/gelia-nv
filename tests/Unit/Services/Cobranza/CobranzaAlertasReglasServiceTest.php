<?php

namespace Tests\Unit\Services\Cobranza;

use App\Services\Cobranza\CobranzaAlertasReglasService;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class CobranzaAlertasReglasServiceTest extends TestCase
{
    private CobranzaAlertasReglasService $reglas;

    private array $config;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reglas = new CobranzaAlertasReglasService();
        $this->config = [
            'intervalo_dias' => 3,
            'umbral_diario' => 30,
            'dias_gracia' => 3,
            'dias_habiles' => [1, 2, 3, 4, 5],
        ];
    }

    public function test_no_llama_durante_gracia(): void
    {
        $this->assertFalse($this->reglas->esDiaDeLlamada(1, $this->config));
        $this->assertFalse($this->reglas->esDiaDeLlamada(3, $this->config));
    }

    public function test_primera_llamada_y_ciclo_intervalo(): void
    {
        $this->assertTrue($this->reglas->esDiaDeLlamada(4, $this->config));
        $this->assertFalse($this->reglas->esDiaDeLlamada(5, $this->config));
        $this->assertTrue($this->reglas->esDiaDeLlamada(7, $this->config));
        $this->assertTrue($this->reglas->esDiaDeLlamada(10, $this->config));
    }

    public function test_umbral_diario_activa_llamada_diaria(): void
    {
        $this->assertTrue($this->reglas->esDiaDeLlamada(30, $this->config));
        $this->assertTrue($this->reglas->esDiaDeLlamada(31, $this->config));
    }

    public function test_dia_habil_lunes_a_viernes(): void
    {
        $lunes = Carbon::parse('2026-06-22');
        $sabado = Carbon::parse('2026-06-27');

        $this->assertTrue($this->reglas->esDiaHabil($lunes, $this->config));
        $this->assertFalse($this->reglas->esDiaHabil($sabado, $this->config));
    }
}
