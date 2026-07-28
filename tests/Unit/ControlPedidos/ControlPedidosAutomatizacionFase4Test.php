<?php

namespace Tests\Unit\ControlPedidos;

use App\Models\ControlPedidos\CatalogoPaqueteriaPedido;
use App\Models\ControlPedidos\CatalogoTipoOperacionEnvio;
use App\Models\ControlPedidos\PedidoBma;
use App\Services\ControlPedidos\ValidacionCamposPedidoBma;
use Tests\TestCase;

/**
 * Check Fase 4: automatización tipo envío (sin DB).
 */
class ControlPedidosAutomatizacionFase4Test extends TestCase
{
    private function resolverEstatus(): object
    {
        return new class {
            use ValidacionCamposPedidoBma;

            public function estatus(PedidoBma $pedido): string
            {
                return $this->resolverEstatusEnvioAlEnviar($pedido);
            }
        };
    }

    public function test_paqueteria_flag_permite_costo_diferido(): void
    {
        $local = new CatalogoPaqueteriaPedido([
            'categoria' => CatalogoPaqueteriaPedido::CATEGORIA_LOCAL_REGIONAL,
            'permite_costo_diferido' => true,
        ]);
        $comercial = new CatalogoPaqueteriaPedido([
            'categoria' => CatalogoPaqueteriaPedido::CATEGORIA_COMERCIAL,
            'permite_costo_diferido' => false,
        ]);

        $this->assertTrue($local->permiteCostoDiferido());
        $this->assertTrue($local->esLocalRegional());
        $this->assertFalse($comercial->permiteCostoDiferido());
        $this->assertTrue($comercial->ofreceRastreo());
    }

    public function test_complementario_al_enviar_queda_completo(): void
    {
        $tipo = new CatalogoTipoOperacionEnvio([
            'codigo' => CatalogoTipoOperacionEnvio::CODIGO_RESGUARDO_COMPLEMENTARIO,
        ]);
        $pedido = new PedidoBma(['costo_envio' => 90]);
        $pedido->setRelation('tipoOperacionEnvio', $tipo);

        $this->assertTrue($pedido->esResguardoComplementario());
        $this->assertSame(
            PedidoBma::ESTATUS_ENVIO_COMPLETO,
            $this->resolverEstatus()->estatus($pedido)
        );
    }

    public function test_municipio_diferido_sigue_pendiente_si_sin_costo(): void
    {
        $tipo = new CatalogoTipoOperacionEnvio([
            'codigo' => CatalogoTipoOperacionEnvio::CODIGO_MUNICIPIO_DIFERIDO,
        ]);
        $pedido = new PedidoBma(['costo_envio' => null]);
        $pedido->setRelation('tipoOperacionEnvio', $tipo);

        $this->assertSame(
            PedidoBma::ESTATUS_ENVIO_PENDIENTE_REGULARIZACION,
            $this->resolverEstatus()->estatus($pedido)
        );
    }

    public function test_matriz_decision_codigos_fase4(): void
    {
        $this->assertSame('MUNICIPIO_DIFERIDO', CatalogoTipoOperacionEnvio::CODIGO_MUNICIPIO_DIFERIDO);
        $this->assertSame('RESGUARDO_ABIERTO', CatalogoTipoOperacionEnvio::CODIGO_RESGUARDO_ABIERTO);
        $this->assertSame('RESGUARDO_COMPLEMENTARIO', CatalogoTipoOperacionEnvio::CODIGO_RESGUARDO_COMPLEMENTARIO);
        $this->assertSame('pendiente_consolidacion', PedidoBma::ESTATUS_ENVIO_PENDIENTE_CONSOLIDACION);
    }

    public function test_peso_cobrado_guia_es_el_mayor_entre_real_y_volumetrico(): void
    {
        $this->assertSame(12.5, PedidoBma::calcularPesoCobradoGuia(12.5, 8.0));
        $this->assertSame(10.0, PedidoBma::calcularPesoCobradoGuia(7.0, 10.0));
        $this->assertSame(5.0, PedidoBma::calcularPesoCobradoGuia(5.0, null));
        $this->assertSame(3.0, PedidoBma::calcularPesoCobradoGuia(null, 3.0));
        $this->assertNull(PedidoBma::calcularPesoCobradoGuia(null, null));
    }
}
