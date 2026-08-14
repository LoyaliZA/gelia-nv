<?php

namespace Tests\Unit\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Support\ControlPedidos\MaquinaEstadosPedidoBma;
use Tests\TestCase;

class ControlPedidosMaquinaEstadosTest extends TestCase
{
    public function test_transiciones_felices_permitidas(): void
    {
        $this->assertTrue(MaquinaEstadosPedidoBma::puedeTransicionar(
            CatalogoEstatusPedido::FASE_BORRADOR,
            CatalogoEstatusPedido::FASE_PESAJE_PENDIENTE
        ));
        $this->assertTrue(MaquinaEstadosPedidoBma::puedeTransicionar(
            CatalogoEstatusPedido::FASE_PESAJE_PENDIENTE,
            CatalogoEstatusPedido::FASE_PESAJE_RESPONDIDO
        ));
        $this->assertTrue(MaquinaEstadosPedidoBma::puedeTransicionar(
            CatalogoEstatusPedido::FASE_PESAJE_RESPONDIDO,
            CatalogoEstatusPedido::FASE_PENDIENTE_AUXILIAR
        ));
        $this->assertTrue(MaquinaEstadosPedidoBma::puedeTransicionar(
            CatalogoEstatusPedido::FASE_PENDIENTE_AUXILIAR,
            CatalogoEstatusPedido::FASE_EN_CEDIS
        ));
        $this->assertTrue(MaquinaEstadosPedidoBma::puedeTransicionar(
            CatalogoEstatusPedido::FASE_EN_CEDIS,
            CatalogoEstatusPedido::FASE_PENDIENTE_DE_GUIA
        ));
        $this->assertTrue(MaquinaEstadosPedidoBma::puedeTransicionar(
            CatalogoEstatusPedido::FASE_PENDIENTE_DE_GUIA,
            CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO
        ));
        $this->assertTrue(MaquinaEstadosPedidoBma::puedeTransicionar(
            CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO,
            CatalogoEstatusPedido::FASE_ENVIADO
        ));
    }

    public function test_guia_no_salta_a_enviado(): void
    {
        $this->assertFalse(MaquinaEstadosPedidoBma::puedeTransicionar(
            CatalogoEstatusPedido::FASE_PENDIENTE_DE_GUIA,
            CatalogoEstatusPedido::FASE_ENVIADO
        ));
        $this->assertFalse(MaquinaEstadosPedidoBma::puedeTransicionar(
            CatalogoEstatusPedido::FASE_EN_CEDIS,
            CatalogoEstatusPedido::FASE_ENVIADO
        ));
        $this->assertSame(
            CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO,
            MaquinaEstadosPedidoBma::faseDestinoTrasAsignarGuia()
        );
    }

    public function test_assert_transicion_invalida_lanza(): void
    {
        $this->expectException(\RuntimeException::class);
        MaquinaEstadosPedidoBma::assertTransicion(
            CatalogoEstatusPedido::FASE_BORRADOR,
            CatalogoEstatusPedido::FASE_ENVIADO
        );
    }

    public function test_hito_auditoria(): void
    {
        $estatus = new CatalogoEstatusPedido(['fase_ciclo' => CatalogoEstatusPedido::FASE_PENDIENTE_AUXILIAR]);

        $sinPago = new PedidoBma(['pago_validado_at' => null]);
        $sinPago->setRelation('estatus', $estatus);
        $this->assertSame(
            MaquinaEstadosPedidoBma::HITO_PAGO_EN_REVISION,
            MaquinaEstadosPedidoBma::hitoAuditoria($sinPago)
        );

        $this->assertSame(
            'Pago en revisión',
            MaquinaEstadosPedidoBma::etiquetaHito(MaquinaEstadosPedidoBma::HITO_PAGO_EN_REVISION)
        );
        $this->assertSame(
            'Pendiente de remisión',
            MaquinaEstadosPedidoBma::etiquetaHito(MaquinaEstadosPedidoBma::HITO_PENDIENTE_REMISION)
        );
    }

    public function test_errores_graves_bloquean_empaque(): void
    {
        $limpio = new PedidoBma(['campos_incorrectos' => null]);
        $this->assertFalse(MaquinaEstadosPedidoBma::erroresGravesBloqueanEmpaque($limpio));

        $grave = new PedidoBma(['campos_incorrectos' => ['pago_validado', 'empaque']]);
        $this->assertTrue(MaquinaEstadosPedidoBma::erroresGravesBloqueanEmpaque($grave));

        $soloEmpaque = new PedidoBma(['campos_incorrectos' => ['empaque']]);
        $this->assertFalse(MaquinaEstadosPedidoBma::erroresGravesBloqueanEmpaque($soloEmpaque));
    }

    public function test_reabrir_enviado_a_recoleccion(): void
    {
        $this->assertTrue(MaquinaEstadosPedidoBma::puedeTransicionar(
            CatalogoEstatusPedido::FASE_ENVIADO,
            CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO
        ));
        $this->assertFalse(MaquinaEstadosPedidoBma::puedeTransicionar(
            CatalogoEstatusPedido::FASE_ENVIADO,
            CatalogoEstatusPedido::FASE_EN_CEDIS
        ));
        $this->assertFalse(MaquinaEstadosPedidoBma::puedeTransicionar(
            CatalogoEstatusPedido::FASE_ENTREGADO,
            CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO
        ));
        $this->assertFalse(MaquinaEstadosPedidoBma::puedeTransicionar(
            CatalogoEstatusPedido::FASE_CANCELADO,
            CatalogoEstatusPedido::FASE_BORRADOR
        ));
    }
}
