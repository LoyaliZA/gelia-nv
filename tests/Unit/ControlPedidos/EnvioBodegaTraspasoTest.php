<?php

namespace Tests\Unit\ControlPedidos;

use App\Models\ControlPedidos\CatalogoModalidadPreparacionPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Support\ControlPedidos\MaquinaEstadosTareaPreparacion;
use App\Support\ControlPedidos\VisibilidadTareaPreparacion;
use Tests\TestCase;

class EnvioBodegaTraspasoTest extends TestCase
{
    public function test_maquina_estados_incluye_traslado(): void
    {
        $this->assertTrue(MaquinaEstadosTareaPreparacion::puedeTransicionar(
            PedidoBmaTareaPreparacion::ESTADO_EN_ATENCION,
            PedidoBmaTareaPreparacion::ESTADO_LISTA_PARA_TRASLADO
        ));
        $this->assertTrue(MaquinaEstadosTareaPreparacion::puedeTransicionar(
            PedidoBmaTareaPreparacion::ESTADO_LISTA_PARA_TRASLADO,
            PedidoBmaTareaPreparacion::ESTADO_EN_TRASLADO
        ));
        $this->assertTrue(MaquinaEstadosTareaPreparacion::puedeTransicionar(
            PedidoBmaTareaPreparacion::ESTADO_EN_TRASLADO,
            PedidoBmaTareaPreparacion::ESTADO_RECIBIDA_CEDIS
        ));
        $this->assertTrue(MaquinaEstadosTareaPreparacion::puedeTransicionar(
            PedidoBmaTareaPreparacion::ESTADO_EN_TRASLADO,
            PedidoBmaTareaPreparacion::ESTADO_RECHAZADA_CEDIS
        ));
        $this->assertTrue(MaquinaEstadosTareaPreparacion::puedeTransicionar(
            PedidoBmaTareaPreparacion::ESTADO_RECHAZADA_CEDIS,
            PedidoBmaTareaPreparacion::ESTADO_CON_INCIDENCIA
        ));
    }

    public function test_codigos_fase5(): void
    {
        $this->assertContains('ENVIO_BODEGA_NORMAL', CatalogoModalidadPreparacionPedido::CODIGOS_FASE5);
        $this->assertContains('ENVIO_BODEGA_COMPLEMENTO', CatalogoModalidadPreparacionPedido::CODIGOS_FASE5);
        $this->assertContains('ENVIO_BODEGA_NORMAL', CatalogoModalidadPreparacionPedido::CODIGOS_SOLICITABLES);
    }

    public function test_payload_tienda_sin_finanzas_con_progreso(): void
    {
        $tarea = new PedidoBmaTareaPreparacion([
            'id' => 1,
            'estado' => PedidoBmaTareaPreparacion::ESTADO_LISTA_PARA_TRASLADO,
            'version' => 1,
            'requiere_traslado_cedis' => true,
        ]);
        $modalidad = new CatalogoModalidadPreparacionPedido([
            'codigo' => CatalogoModalidadPreparacionPedido::CODIGO_ENVIO_BODEGA_NORMAL,
            'nombre' => 'Envío bodega',
        ]);
        $pedido = new PedidoBma(['id' => 9, 'folio' => 'F-9', 'total_mercancia' => 999, 'banco_id' => 1]);
        $tarea->setRelation('modalidad', $modalidad);
        $tarea->setRelation('almacen', null);
        $tarea->setRelation('productos', collect([]));
        $tarea->setRelation('asignadaA', null);
        $tarea->setRelation('pedido', $pedido);
        $tarea->setRelation('enviadaCedisPor', null);
        $tarea->setRelation('recibidaCedisPor', null);

        $payload = VisibilidadTareaPreparacion::payloadTienda($tarea);
        $json = json_encode($payload);

        $this->assertStringNotContainsString('total_mercancia', $json);
        $this->assertTrue($payload['requiere_traslado_cedis']);
        $this->assertNotEmpty($payload['progreso_traslado']);
        $this->assertSame('lista', $payload['progreso_traslado'][1]['clave']);
    }

    public function test_modalidad_requiere_traslado_por_defecto(): void
    {
        $m = new CatalogoModalidadPreparacionPedido([
            'codigo' => CatalogoModalidadPreparacionPedido::CODIGO_ENVIO_BODEGA_NORMAL,
            'requisitos_json' => ['traslado_cedis' => true],
        ]);
        $this->assertTrue($m->esEnvioBodega());
        $this->assertTrue($m->requiereTrasladoCedisPorDefecto());
    }
}
