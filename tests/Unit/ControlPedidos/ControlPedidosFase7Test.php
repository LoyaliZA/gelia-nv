<?php

namespace Tests\Unit\ControlPedidos;

use App\Models\ControlPedidos\PedidoBma;
use App\Services\ControlPedidos\MatrizResolucionFinancieraCancelacionService;
use App\Support\ControlPedidos\MaquinaEstadosTareaPreparacion;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use PHPUnit\Framework\TestCase;

class ControlPedidosFase7Test extends TestCase
{
    public function test_matriz_sin_pagos_auto_finaliza(): void
    {
        $pedido = new PedidoBma;
        $pedido->setRelation('pagosExhibicion', collect([]));
        $pedido->setRelation('safAplicaciones', collect([]));

        $eval = (new MatrizResolucionFinancieraCancelacionService)->evaluar($pedido);

        $this->assertTrue($eval['puede_auto']);
        $this->assertFalse($eval['requiere_resolutor']);
        $this->assertSame('ninguna', $eval['resolucion']);
    }

    public function test_matriz_con_pagos_bloquea(): void
    {
        $pago = new class
        {
            public float $monto = 100.0;
        };
        $pedido = new PedidoBma;
        $pedido->setRelation('pagosExhibicion', collect([$pago]));
        $pedido->setRelation('safAplicaciones', collect([]));

        $eval = (new MatrizResolucionFinancieraCancelacionService)->evaluar($pedido);

        $this->assertFalse($eval['puede_auto']);
        $this->assertTrue($eval['requiere_resolutor']);
    }

    public function test_maquina_permite_reactivar_desde_liberacion_solicitada(): void
    {
        $this->assertTrue(MaquinaEstadosTareaPreparacion::puedeTransicionar(
            PedidoBmaTareaPreparacion::ESTADO_LIBERACION_SOLICITADA,
            PedidoBmaTareaPreparacion::ESTADO_RESPONDIDA
        ));
        $this->assertTrue(MaquinaEstadosTareaPreparacion::puedeTransicionar(
            PedidoBmaTareaPreparacion::ESTADO_LIBERACION_SOLICITADA,
            PedidoBmaTareaPreparacion::ESTADO_LIBERADA
        ));
    }
}
