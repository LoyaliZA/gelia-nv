<?php

namespace Tests\Unit\SaldosAFavor;

use App\Services\ControlPedidos\PagosPedidoBmaConfig;
use App\Services\SaldosAFavor\CoberturaPagoPedidoBmaService;
use App\Services\SaldosAFavor\RegistrarPagoPedidoBmaService;
use PHPUnit\Framework\TestCase;

class IntegridadCoberturaPagoTest extends TestCase
{
    public function test_diferencia_dentro_de_tolerancia_es_cubierto(): void
    {
        $r = CoberturaPagoPedidoBmaService::calcularDesdeMontosEstatico(
            '1000.00',
            '0.00',
            false,
            '0.00',
            '0.00',
            '999.56',
            '0.44',
        );

        $this->assertTrue($r['cubierto']);
        $this->assertSame('cubierto', $r['cobertura']);
        $this->assertSame('0.44', $r['diferencia']);
        $this->assertSame('0.44', $r['tolerancia_aplicada']);
    }

    public function test_diferencia_un_centavo_sobre_tolerancia_no_cubre(): void
    {
        $r = CoberturaPagoPedidoBmaService::calcularDesdeMontosEstatico(
            '1000.00',
            '0.00',
            false,
            '0.00',
            '0.00',
            '999.55',
            '0.44',
        );

        $this->assertFalse($r['cubierto']);
        $this->assertSame('parcial', $r['cobertura']);
        $this->assertSame('0.45', $r['pendiente']);
    }

    public function test_cambiar_tolerancia_cambia_regla(): void
    {
        $bajo = CoberturaPagoPedidoBmaService::calcularDesdeMontosEstatico(
            '100.00', '0.00', false, '0.00', '0.00', '99.50', '0.40'
        );
        $alto = CoberturaPagoPedidoBmaService::calcularDesdeMontosEstatico(
            '100.00', '0.00', false, '0.00', '0.00', '99.50', '0.50'
        );

        $this->assertFalse($bajo['cubierto']);
        $this->assertTrue($alto['cubierto']);
    }

    public function test_centavos_redondeo_estable(): void
    {
        $this->assertSame(44, PagosPedidoBmaConfig::aCentavos('0.44'));
        $this->assertSame('0.44', PagosPedidoBmaConfig::centavosADecimal(44));
    }

    public function test_compat_resumen_legacy_sigue_cubierto_exacto(): void
    {
        $r = RegistrarPagoPedidoBmaService::calcularResumenCobertura(800, 150, true, 50, 200, 800);
        $this->assertSame('cubierto', $r['cobertura']);
        $this->assertEquals(0.0, (float) $r['pendiente']);
    }
}
