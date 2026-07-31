<?php

namespace Tests\Unit\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\CatalogoOrigenPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Services\ControlPedidos\ResponderPesajePedidoBmaService;
use App\Services\ControlPedidos\ValidacionCamposPedidoBma;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Checks de pesaje CEDIS sin DB (phpunit fuerza sqlite :memory:).
 */
class ControlPedidosPesajeTest extends TestCase
{
    public function test_constantes_estatus_y_motivos(): void
    {
        $this->assertSame('pendiente_pesaje', PedidoBma::ESTATUS_ENVIO_PENDIENTE_PESAJE);
        $this->assertSame('pesaje_listo', PedidoBma::ESTATUS_ENVIO_PESAJE_LISTO);
        $this->assertContains(PedidoBma::MOTIVO_REPESAJE_ANEXO_PIEZAS, PedidoBma::MOTIVOS_REPESAJE);
        $this->assertContains(PedidoBma::MOTIVO_REPESAJE_QUITA_PIEZAS, PedidoBma::MOTIVOS_REPESAJE);
        $this->assertSame('pdf_pedido', PedidoBmaDocumento::TIPO_PDF_PEDIDO);
    }

    public function test_tiene_pesaje_respondido(): void
    {
        $sin = new PedidoBma(['pesaje_respondido_at' => null]);
        $this->assertFalse($sin->tienePesajeRespondido());

        $con = new PedidoBma(['pesaje_respondido_at' => now()]);
        $this->assertTrue($con->tienePesajeRespondido());
    }

    public function test_puede_solicitar_pesaje_solo_borrador_sin_pesaje_previo(): void
    {
        $origen = new CatalogoOrigenPedido(['requiere_logistica' => true]);
        $estatus = new CatalogoEstatusPedido(['fase_ciclo' => CatalogoEstatusPedido::FASE_BORRADOR]);

        $ok = new PedidoBma(['estatus_envio' => null, 'pesaje_respondido_at' => null]);
        $ok->setRelation('origen', $origen);
        $ok->setRelation('estatus', $estatus);
        $this->assertTrue($ok->puedeSolicitarPesaje());

        $pendiente = new PedidoBma([
            'estatus_envio' => PedidoBma::ESTATUS_ENVIO_PENDIENTE_PESAJE,
            'pesaje_respondido_at' => null,
        ]);
        $pendiente->setRelation('origen', $origen);
        $pendiente->setRelation('estatus', $estatus);
        $this->assertFalse($pendiente->puedeSolicitarPesaje());

        $yaPesado = new PedidoBma([
            'estatus_envio' => PedidoBma::ESTATUS_ENVIO_PESAJE_LISTO,
            'pesaje_respondido_at' => now(),
        ]);
        $yaPesado->setRelation('origen', $origen);
        $yaPesado->setRelation('estatus', $estatus);
        $this->assertFalse($yaPesado->puedeSolicitarPesaje());
    }

    public function test_puede_responder_y_repesaje_gates(): void
    {
        $pendiente = new PedidoBma([
            'estatus_envio' => PedidoBma::ESTATUS_ENVIO_PENDIENTE_PESAJE,
            'empacado_at' => null,
        ]);
        $this->assertTrue($pendiente->puedeResponderPesaje());

        $listo = new PedidoBma([
            'estatus_envio' => PedidoBma::ESTATUS_ENVIO_PESAJE_LISTO,
            'empacado_at' => null,
            'pesaje_respondido_at' => now(),
        ]);
        $estatus = new CatalogoEstatusPedido(['fase_ciclo' => CatalogoEstatusPedido::FASE_BORRADOR]);
        $listo->setRelation('estatus', $estatus);
        $this->assertFalse($listo->puedeResponderPesaje());
        $this->assertTrue($listo->puedeSolicitarRepesaje());

        $empacado = new PedidoBma([
            'estatus_envio' => PedidoBma::ESTATUS_ENVIO_PESAJE_LISTO,
            'empacado_at' => now(),
            'pesaje_respondido_at' => now(),
        ]);
        $empacado->setRelation('estatus', $estatus);
        $this->assertFalse($empacado->puedeSolicitarRepesaje());
    }

    public function test_enviar_sin_pesaje_exige_consulta_cedis(): void
    {
        $origen = new CatalogoOrigenPedido(['requiere_logistica' => true]);
        $pedido = new PedidoBma([
            'folio_remision' => 'REM-1',
            'cliente_id' => 1,
            'origen_id' => 1,
            'catalogo_banco_id' => 1,
            'almacen_id' => 1,
            'total_mercancia' => 100,
            'pesaje_respondido_at' => null,
        ]);
        $pedido->setRelation('origen', $origen);
        $pedido->setRelation('tipoOperacionEnvio', null);

        $validador = new class {
            use ValidacionCamposPedidoBma;

            public function check(PedidoBma $p): void
            {
                $this->validarCamposRequeridos($p);
            }
        };

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('pesaje de CEDIS');
        $validador->check($pedido);
    }

    public function test_normalizar_lineas_caja_agrega_mismo_tipo(): void
    {
        $service = app(ResponderPesajePedidoBmaService::class);
        $method = new ReflectionMethod(ResponderPesajePedidoBmaService::class, 'normalizarLineas');
        $method->setAccessible(true);

        $out = $method->invoke($service, [
            ['catalogo_tipo_caja_id' => 10, 'cantidad' => 2],
            ['catalogo_tipo_caja_id' => 10, 'cantidad' => 1],
            ['catalogo_tipo_caja_id' => 20, 'cantidad' => 3],
            ['catalogo_tipo_caja_id' => 0, 'cantidad' => 5],
            ['catalogo_tipo_caja_id' => 30, 'cantidad' => 0],
        ]);

        $this->assertCount(2, $out);
        $byId = collect($out)->keyBy('catalogo_tipo_caja_id');
        $this->assertSame(3, $byId[10]['cantidad']);
        $this->assertSame(3, $byId[20]['cantidad']);
    }

    public function test_peso_cobrado_guia_usa_maximo(): void
    {
        $this->assertSame(5.0, PedidoBma::calcularPesoCobradoGuia(3.0, 5.0));
        $this->assertSame(4.0, PedidoBma::calcularPesoCobradoGuia(4.0, 2.0));
    }
}
