<?php

namespace Tests\Unit\ControlPedidos;

use App\Models\ControlPedidos\CatalogoPaqueteriaPedido;
use App\Models\ControlPedidos\PedidoBma;
use Tests\TestCase;

class ControlPedidosPaqPendienteTest extends TestCase
{
    public function test_es_pendiente_confirmacion_por_nombre_canonico(): void
    {
        $pend = new CatalogoPaqueteriaPedido(['nombre' => CatalogoPaqueteriaPedido::NOMBRE_PENDIENTE]);
        $this->assertTrue($pend->esPendienteConfirmacion());

        $conEspacios = new CatalogoPaqueteriaPedido(['nombre' => '  paq. pendiente  ']);
        $this->assertTrue($conEspacios->esPendienteConfirmacion());

        $fedex = new CatalogoPaqueteriaPedido(['nombre' => 'FEDEX']);
        $this->assertFalse($fedex->esPendienteConfirmacion());
    }

    public function test_pedido_tiene_paqueteria_pendiente(): void
    {
        $pedido = new PedidoBma();
        $pedido->setRelation('paqueteria', new CatalogoPaqueteriaPedido([
            'nombre' => CatalogoPaqueteriaPedido::NOMBRE_PENDIENTE,
        ]));
        $this->assertTrue($pedido->tienePaqueteriaPendiente());

        $pedido->setRelation('paqueteria', new CatalogoPaqueteriaPedido(['nombre' => 'DHL']));
        $this->assertFalse($pedido->tienePaqueteriaPendiente());
    }

    public function test_pendiente_no_ofrece_rastreo_ni_es_comercial(): void
    {
        $pend = new CatalogoPaqueteriaPedido([
            'nombre' => CatalogoPaqueteriaPedido::NOMBRE_PENDIENTE,
            'categoria' => CatalogoPaqueteriaPedido::CATEGORIA_LOCAL_REGIONAL,
            'permite_costo_diferido' => true,
        ]);

        $this->assertTrue($pend->esLocalRegional());
        $this->assertTrue($pend->permiteCostoDiferido());
        $this->assertFalse($pend->ofreceRastreo());
    }
}
