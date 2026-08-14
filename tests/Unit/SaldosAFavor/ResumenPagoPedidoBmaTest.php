<?php

namespace Tests\Unit\SaldosAFavor;

use App\Services\SaldosAFavor\RegistrarPagoPedidoBmaService;
use PHPUnit\Framework\TestCase;

class ResumenPagoPedidoBmaTest extends TestCase
{
    public function test_saf_mas_pago_exacto_al_neto_es_cubierto_sin_excedente(): void
    {
        $r = RegistrarPagoPedidoBmaService::calcularResumenCobertura(800, 150, true, 50, 200, 800);

        $this->assertEquals(1000.0, $r['total_a_cubrir']);
        $this->assertEquals(200.0, $r['saldo_a_favor_aplicado']);
        $this->assertEquals(800.0, $r['total_a_cobrar']);
        $this->assertEquals(800.0, $r['total_pagado']);
        $this->assertEquals(0.0, $r['pendiente']);
        $this->assertEquals(0.0, $r['excedente_generado']);
        $this->assertSame('cubierto', $r['cobertura']);
    }

    public function test_saf_solo_cubriendo_bruto_es_cubierto_sin_excedente(): void
    {
        $r = RegistrarPagoPedidoBmaService::calcularResumenCobertura(1000, 0, false, 0, 1000, 0);

        $this->assertSame('cubierto', $r['cobertura']);
        $this->assertEquals(0.0, $r['excedente_generado']);
        $this->assertEquals(0.0, $r['pendiente']);
        $this->assertEquals(1000.0, $r['saldo_a_favor_aplicado']);
        $this->assertEquals(0.0, $r['total_pagado']);
    }

    public function test_pago_igual_al_bruto_con_saf_genera_excedente_igual_al_saf(): void
    {
        $r = RegistrarPagoPedidoBmaService::calcularResumenCobertura(1000, 0, false, 0, 200, 1000);

        $this->assertSame('con_excedente', $r['cobertura']);
        $this->assertEquals(200.0, $r['excedente_generado']);
        $this->assertEquals(0.0, $r['pendiente']);
        $this->assertEquals(200.0, $r['saldo_a_favor_aplicado']);
        $this->assertEquals(1000.0, $r['total_pagado']);
    }

    public function test_parcial_no_usa_etiqueta_excedente(): void
    {
        $r = RegistrarPagoPedidoBmaService::calcularResumenCobertura(1000, 200, false, 0, 200, 500);

        $this->assertSame('parcial', $r['cobertura']);
        $this->assertEquals(500.0, $r['pendiente']);
        $this->assertEquals(0.0, $r['excedente_generado']);
    }

    public function test_mensaje_faltante_incluye_monto(): void
    {
        $msg = RegistrarPagoPedidoBmaService::mensajeMontoFaltante(123.45);
        $this->assertStringContainsString('Faltan $123.45', $msg);
        $this->assertStringContainsString('saldo a favor aplicado', $msg);
    }
}
