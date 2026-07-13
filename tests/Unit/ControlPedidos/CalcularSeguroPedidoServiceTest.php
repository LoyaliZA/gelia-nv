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
}
