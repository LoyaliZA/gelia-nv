<?php

namespace Tests\Unit\ControlPedidos;

use App\Services\ControlPedidos\CalcularSeguroPedidoService;
use PHPUnit\Framework\TestCase;

class CalcularSeguroPedidoServiceTest extends TestCase
{
    private CalcularSeguroPedidoService $servicio;

    protected function setUp(): void
    {
        parent::setUp();
        $this->servicio = new CalcularSeguroPedidoService();
    }

    public function test_fedex_calcula_dos_punto_cinco_porciento(): void
    {
        $this->assertSame(12.5, $this->servicio->calcularCosto('FEDEX', 100, 400));
        $this->assertTrue($this->servicio->tieneCobertura('FEDEX'));
    }

    public function test_dhl_calcula_dos_porciento_mas_cuota_fija(): void
    {
        $this->assertSame(61.0, $this->servicio->calcularCosto('DHL', 100, 400));
        $this->assertTrue($this->servicio->tieneCobertura('DHL'));
    }

    public function test_transporte_local_sin_cobertura_ni_costo(): void
    {
        $this->assertSame(0.0, $this->servicio->calcularCosto('TAXI FRONTERA', 100, 400));
        $this->assertFalse($this->servicio->tieneCobertura('TAXI FRONTERA'));
    }

    public function test_montos_cero_en_comercial(): void
    {
        $this->assertSame(0.0, $this->servicio->calcularCosto('ESTAFETA', 0, 0));
    }

    /** Documenta el bug $3.75: 150 de reexpedición × 2.5% no debe entrar a la base del seguro. */
    public function test_reexpedicion_ciento_cincuenta_no_debe_sumar_tres_setenta_cinco(): void
    {
        $flete = 200.0;
        $reexpedicion = 150.0;
        $mercancia = 1000.0;

        $conRexMezclada = $this->servicio->calcularCosto('FEDEX', $flete + $reexpedicion, $mercancia);
        $soloFlete = $this->servicio->calcularCosto('FEDEX', $flete, $mercancia);

        $this->assertSame(3.75, round($conRexMezclada - $soloFlete, 2));
        $this->assertSame(30.0, $soloFlete); // (200+1000)*0.025
    }
}
