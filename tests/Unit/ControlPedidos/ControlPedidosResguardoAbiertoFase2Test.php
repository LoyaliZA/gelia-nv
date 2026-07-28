<?php

namespace Tests\Unit\ControlPedidos;

use App\Models\ControlPedidos\CatalogoTipoOperacionEnvio;
use App\Models\ControlPedidos\PedidoBma;
use App\Services\ControlPedidos\ValidacionCamposPedidoBma;
use Tests\TestCase;

/**
 * Check Fase 2 resguardo abierto (sin DB): estatus pendiente_liberacion y flags.
 */
class ControlPedidosResguardoAbiertoFase2Test extends TestCase
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

    public function test_resguardo_abierto_al_enviar_queda_pendiente_liberacion(): void
    {
        $tipo = new CatalogoTipoOperacionEnvio(['codigo' => CatalogoTipoOperacionEnvio::CODIGO_RESGUARDO_ABIERTO]);
        $pedido = new PedidoBma(['costo_envio' => 50, 'es_resguardo' => true]);
        $pedido->setRelation('tipoOperacionEnvio', $tipo);

        $this->assertTrue($pedido->esResguardoAbierto());
        $this->assertSame(
            PedidoBma::ESTATUS_ENVIO_PENDIENTE_LIBERACION,
            $this->resolverEstatus()->estatus($pedido)
        );
    }

    public function test_pendiente_liberacion_no_permite_anexar_hasta_liberar(): void
    {
        $pedido = new PedidoBma([
            'estatus_envio' => PedidoBma::ESTATUS_ENVIO_PENDIENTE_LIBERACION,
            'es_resguardo' => true,
        ]);
        $tipo = new CatalogoTipoOperacionEnvio(['codigo' => CatalogoTipoOperacionEnvio::CODIGO_RESGUARDO_ABIERTO]);
        $pedido->setRelation('tipoOperacionEnvio', $tipo);

        $this->assertFalse($pedido->puedeAnexarPagoEnvio());
        $this->assertTrue($pedido->puedeLiberarConCaptura());
    }

    public function test_tras_liberacion_flags_revision_anexo(): void
    {
        $pedido = new PedidoBma([
            'estatus_envio' => PedidoBma::ESTATUS_ENVIO_PENDIENTE_REVISION_ANEXO,
            'es_resguardo' => false,
            'costo_envio' => 120,
        ]);

        $this->assertTrue($pedido->tieneAnexoEnvioPorRevisar());
        $this->assertFalse($pedido->puedeAnexarPagoEnvio());
        $this->assertSame(320.0, PedidoBma::calcularTotal(200, 120, false, 0, 0));
    }

    public function test_resguardo_abierto_constante_catalogo(): void
    {
        $this->assertSame('RESGUARDO_ABIERTO', CatalogoTipoOperacionEnvio::CODIGO_RESGUARDO_ABIERTO);
        $tipo = new CatalogoTipoOperacionEnvio(['codigo' => CatalogoTipoOperacionEnvio::CODIGO_RESGUARDO_ABIERTO]);
        $this->assertTrue($tipo->esResguardoAbierto());
        $this->assertFalse($tipo->esMunicipioDiferido());
    }
}
