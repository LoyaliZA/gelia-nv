<?php

namespace Tests\Unit\ControlPedidos;

use App\Models\ControlPedidos\CatalogoModalidadPreparacionPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Services\ControlPedidos\PreparacionTiendaConfig;
use App\Support\ControlPedidos\MaquinaEstadosTareaPreparacion;
use App\Support\ControlPedidos\VisibilidadTareaPreparacion;
use Tests\TestCase;

class PreparacionTiendaTest extends TestCase
{
    public function test_maquina_estados_transiciones_fase4(): void
    {
        $this->assertTrue(MaquinaEstadosTareaPreparacion::puedeTransicionar(
            PedidoBmaTareaPreparacion::ESTADO_PENDIENTE,
            PedidoBmaTareaPreparacion::ESTADO_EN_ATENCION
        ));
        $this->assertTrue(MaquinaEstadosTareaPreparacion::puedeTransicionar(
            PedidoBmaTareaPreparacion::ESTADO_PENDIENTE,
            PedidoBmaTareaPreparacion::ESTADO_CON_INCIDENCIA
        ));
        $this->assertTrue(MaquinaEstadosTareaPreparacion::puedeTransicionar(
            PedidoBmaTareaPreparacion::ESTADO_RESPONDIDA,
            PedidoBmaTareaPreparacion::ESTADO_LIBERADA
        ));
        $this->assertTrue(MaquinaEstadosTareaPreparacion::puedeTransicionar(
            PedidoBmaTareaPreparacion::ESTADO_CON_INCIDENCIA,
            PedidoBmaTareaPreparacion::ESTADO_PENDIENTE
        ));
        $this->assertFalse(MaquinaEstadosTareaPreparacion::puedeTransicionar(
            PedidoBmaTareaPreparacion::ESTADO_LIBERADA,
            PedidoBmaTareaPreparacion::ESTADO_PENDIENTE
        ));
    }

    public function test_permisos_por_destino(): void
    {
        $this->assertSame(
            'control_pedidos.tienda.tomar',
            MaquinaEstadosTareaPreparacion::permisoParaDestino(PedidoBmaTareaPreparacion::ESTADO_EN_ATENCION)
        );
        $this->assertSame(
            'control_pedidos.preparacion.corregir',
            MaquinaEstadosTareaPreparacion::permisoParaDestino(PedidoBmaTareaPreparacion::ESTADO_CANCELADA)
        );
    }

    public function test_config_flag_por_defecto_apagado(): void
    {
        $def = PreparacionTiendaConfig::flagPorDefecto();
        $this->assertFalse($def['activo']);
        $this->assertSame([], $def['modalidades_habilitadas']);
    }

    public function test_modalidades_fase4_incluyen_resguardo_con_credito(): void
    {
        $this->assertSame([
            'RECOGE_TIENDA',
            'RECOGE_TIENDA_TRANSFERENCIA',
            'RECOGE_TIENDA_CREDITO',
        ], CatalogoModalidadPreparacionPedido::CODIGOS_FASE4);
        $this->assertContains(
            'RECOGE_TIENDA_CREDITO',
            CatalogoModalidadPreparacionPedido::CODIGOS_SOLICITABLES
        );
    }

    public function test_resguardo_con_credito_espera_pago_en_tienda(): void
    {
        $politica = new \App\Support\ControlPedidos\PoliticaSalidaPreparacion(
            CatalogoModalidadPreparacionPedido::CODIGO_RECOGE_TIENDA_CREDITO
        );

        $this->assertTrue($politica->esCreditoDiferido());
        $this->assertTrue($politica->esResguardoDiferido());
        $this->assertTrue($politica->abreResguardo());
        $this->assertTrue($politica->cierraEnPdv());
        $this->assertFalse($politica->permitePorCobrar());
        $this->assertFalse($politica->soloApartado());
    }

    public function test_payload_tienda_sin_datos_financieros(): void
    {
        $tarea = \Mockery::mock(PedidoBmaTareaPreparacion::class)->makePartial();
        $tarea->shouldReceive('piezasSolicitadas')->andReturn(0);
        $tarea->fill([
            'id' => 1,
            'estado' => PedidoBmaTareaPreparacion::ESTADO_PENDIENTE,
            'version' => 1,
            'observaciones_solicitud' => 'Solicitud piloto',
        ]);
        $modalidad = new CatalogoModalidadPreparacionPedido([
            'id' => 10,
            'codigo' => CatalogoModalidadPreparacionPedido::CODIGO_RECOGE_TIENDA_TRANSFERENCIA,
            'nombre' => 'Transferencia',
        ]);
        $pedido = new PedidoBma([
            'id' => 99,
            'folio' => 'F-1',
            'total_mercancia' => 1500.50,
            'banco_id' => 3,
        ]);
        $tarea->setRelation('modalidad', $modalidad);
        $tarea->setRelation('almacen', null);
        $tarea->setRelation('productos', collect([]));
        $tarea->setRelation('asignadaA', null);
        $tarea->setRelation('pedido', $pedido);
        foreach (['caratulas', 'paqueteria', 'atendidaPor', 'enviadaCedisPor', 'recibidaCedisPor', 'cumplimientoFisico'] as $relacion) {
            $tarea->setRelation($relacion, $relacion === 'caratulas' ? collect() : null);
        }
        $tarea->setRelation('solicitudTraspaso', null);
        $pedido->setRelation('referencias', collect());
        $pedido->setRelation('cliente', null);
        $pedido->setRelation('vendedor', null);

        $payload = VisibilidadTareaPreparacion::payloadTienda($tarea);
        $json = json_encode($payload);

        $this->assertStringNotContainsString('total_mercancia', $json);
        $this->assertStringNotContainsString('banco', $json);
        $this->assertArrayHasKey('pedido', $payload);
        $this->assertArrayNotHasKey('total_mercancia', $payload['pedido']);
    }
}
