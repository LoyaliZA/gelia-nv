<?php

namespace Tests\Unit\ControlPedidos;

use App\Models\ControlPedidos\CatalogoTipoOperacionEnvio;
use App\Models\ControlPedidos\PedidoBma;
use App\Services\ControlPedidos\ValidacionCamposPedidoBma;
use Tests\TestCase;

/**
 * Check Fase 1 envío diferido (sin DB): estatus_envio, flags y totales.
 */
class ControlPedidosEnvioDiferidoFase1Test extends TestCase
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

    public function test_municipio_diferido_con_costo_null_resuelve_pendiente_regularizacion(): void
    {
        $tipo = new CatalogoTipoOperacionEnvio(['codigo' => CatalogoTipoOperacionEnvio::CODIGO_MUNICIPIO_DIFERIDO]);
        $pedido = new PedidoBma(['costo_envio' => null]);
        $pedido->setRelation('tipoOperacionEnvio', $tipo);

        $this->assertTrue($pedido->esMunicipioDiferido());
        $this->assertSame(
            PedidoBma::ESTATUS_ENVIO_PENDIENTE_REGULARIZACION,
            $this->resolverEstatus()->estatus($pedido)
        );
    }

    public function test_normal_con_costo_resuelve_completo(): void
    {
        $tipo = new CatalogoTipoOperacionEnvio(['codigo' => CatalogoTipoOperacionEnvio::CODIGO_NORMAL]);
        $pedido = new PedidoBma(['costo_envio' => 120]);
        $pedido->setRelation('tipoOperacionEnvio', $tipo);

        $this->assertSame(
            PedidoBma::ESTATUS_ENVIO_COMPLETO,
            $this->resolverEstatus()->estatus($pedido)
        );
    }

    public function test_municipio_con_costo_tambien_cierra_completo(): void
    {
        $tipo = new CatalogoTipoOperacionEnvio(['codigo' => CatalogoTipoOperacionEnvio::CODIGO_MUNICIPIO_DIFERIDO]);
        $pedido = new PedidoBma(['costo_envio' => 80]);
        $pedido->setRelation('tipoOperacionEnvio', $tipo);

        $this->assertSame(
            PedidoBma::ESTATUS_ENVIO_COMPLETO,
            $this->resolverEstatus()->estatus($pedido)
        );
    }

    public function test_flags_anexo_por_estatus_envio(): void
    {
        $pendiente = new PedidoBma(['estatus_envio' => PedidoBma::ESTATUS_ENVIO_PENDIENTE_REGULARIZACION]);
        $this->assertTrue($pendiente->puedeAnexarPagoEnvio());

        $rechazado = new PedidoBma(['estatus_envio' => PedidoBma::ESTATUS_ENVIO_ANEXO_RECHAZADO]);
        $this->assertTrue($rechazado->puedeAnexarPagoEnvio());

        $revision = new PedidoBma(['estatus_envio' => PedidoBma::ESTATUS_ENVIO_PENDIENTE_REVISION_ANEXO]);
        $this->assertTrue($revision->tieneAnexoEnvioPorRevisar());
        $this->assertFalse($revision->puedeAnexarPagoEnvio());

        $completo = new PedidoBma(['estatus_envio' => PedidoBma::ESTATUS_ENVIO_COMPLETO]);
        $this->assertFalse($completo->puedeAnexarPagoEnvio());
    }

    public function test_aprobar_anexo_recalcula_total_como_mercancia_mas_envio(): void
    {
        // Espeja AprobarAnexoEnvioPedidoBmaService al aplicar monto.
        $this->assertSame(350.5, PedidoBma::calcularTotal(200, 150.5, false, 0, 0));
    }
}
