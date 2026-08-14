<?php

namespace Tests\Unit\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaRevisionProducto;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use Tests\TestCase;

class ControlPedidosSinExistenciaTest extends TestCase
{
    public function test_pieza_sin_resolucion_esta_abierta(): void
    {
        $abierta = new PedidoBmaRevisionProducto([
            'estado_fisico' => PedidoBmaRevisionProducto::ESTADO_SIN_EXISTENCIA,
            'resolucion' => null,
        ]);
        $this->assertTrue($abierta->estaSinExistenciaAbierta());

        $contactar = new PedidoBmaRevisionProducto([
            'estado_fisico' => PedidoBmaRevisionProducto::ESTADO_SIN_EXISTENCIA,
            'resolucion' => PedidoBmaRevisionProducto::RESOLUCION_CONTACTAR,
        ]);
        $this->assertTrue($contactar->estaSinExistenciaAbierta());

        $esperar = new PedidoBmaRevisionProducto([
            'estado_fisico' => PedidoBmaRevisionProducto::ESTADO_SIN_EXISTENCIA,
            'resolucion' => PedidoBmaRevisionProducto::RESOLUCION_ESPERAR,
        ]);
        $this->assertTrue($esperar->estaSinExistenciaAbierta());
    }

    public function test_retirar_sustituir_stock_ok_cierran(): void
    {
        foreach ([
            PedidoBmaRevisionProducto::RESOLUCION_RETIRAR,
            PedidoBmaRevisionProducto::RESOLUCION_SUSTITUIR,
            PedidoBmaRevisionProducto::RESOLUCION_STOCK_OK,
        ] as $res) {
            $r = new PedidoBmaRevisionProducto([
                'estado_fisico' => PedidoBmaRevisionProducto::ESTADO_SIN_EXISTENCIA,
                'resolucion' => $res,
            ]);
            $this->assertFalse($r->estaSinExistenciaAbierta(), $res);
        }
    }

    public function test_bueno_no_es_sin_existencia_abierta(): void
    {
        $r = new PedidoBmaRevisionProducto([
            'estado_fisico' => PedidoBmaRevisionProducto::ESTADO_BUENO,
            'resolucion' => null,
        ]);
        $this->assertFalse($r->estaSinExistenciaAbierta());
    }

    public function test_pedido_assert_bloquea_si_hay_abierta(): void
    {
        $pedido = new PedidoBma([]);
        $pedido->setRelation('revisionesProducto', collect([
            new PedidoBmaRevisionProducto([
                'estado_fisico' => PedidoBmaRevisionProducto::ESTADO_SIN_EXISTENCIA,
                'resolucion' => PedidoBmaRevisionProducto::RESOLUCION_ESPERAR,
            ]),
        ]));
        $this->assertTrue($pedido->tieneSinExistenciaAbierta());
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('sin existencias');
        $pedido->assertSinExistenciaAtendida();
    }

    public function test_pedido_assert_pasa_si_stock_ok(): void
    {
        $pedido = new PedidoBma([]);
        $pedido->setRelation('revisionesProducto', collect([
            new PedidoBmaRevisionProducto([
                'estado_fisico' => PedidoBmaRevisionProducto::ESTADO_SIN_EXISTENCIA,
                'resolucion' => PedidoBmaRevisionProducto::RESOLUCION_STOCK_OK,
            ]),
        ]));
        $this->assertFalse($pedido->tieneSinExistenciaAbierta());
        $pedido->assertSinExistenciaAtendida();
        $this->assertTrue(true);
    }

    public function test_acciones_historial_sin_existencia(): void
    {
        $this->assertSame(
            'Decisión por sin existencias',
            AccionesHistorialPedidoBma::etiqueta(AccionesHistorialPedidoBma::DECISION_SIN_EXISTENCIA)
        );
        $this->assertSame(
            'Existencias confirmadas (CEDIS)',
            AccionesHistorialPedidoBma::etiqueta(AccionesHistorialPedidoBma::STOCK_SIN_EXISTENCIA)
        );
    }

    public function test_puede_cancelar_en_cedis_si_sin_existencia_abierta(): void
    {
        $pedido = new PedidoBma(['numero_rastreo' => null, 'es_resguardo' => false, 'cancelado_at' => null]);
        $pedido->setRelation('estatus', new CatalogoEstatusPedido([
            'fase_ciclo' => CatalogoEstatusPedido::FASE_EN_CEDIS,
        ]));
        $pedido->setRelation('revisionesProducto', collect([
            new PedidoBmaRevisionProducto([
                'estado_fisico' => PedidoBmaRevisionProducto::ESTADO_SIN_EXISTENCIA,
                'resolucion' => null,
            ]),
        ]));
        $this->assertTrue($pedido->puedeCancelarDirecto());
    }

    public function test_estado_fisico_se_conserva_con_stock_ok(): void
    {
        $r = new PedidoBmaRevisionProducto([
            'estado_fisico' => PedidoBmaRevisionProducto::ESTADO_SIN_EXISTENCIA,
            'resolucion' => PedidoBmaRevisionProducto::RESOLUCION_STOCK_OK,
        ]);
        $this->assertSame(PedidoBmaRevisionProducto::ESTADO_SIN_EXISTENCIA, $r->estado_fisico);
        $this->assertSame('Ya hay existencias', $r->resolucion_etiqueta);
    }
}
