<?php

namespace Tests\Unit\ControlPedidos;

use App\Services\ControlPedidos\FusionarSolicitudPreparacionPedido;
use App\Support\ControlPedidos\RegistrarPedidos\DestinoRevisionRegistrarPedidos;
use App\Support\ControlPedidos\RegistrarPedidos\LimiteModalidadCapturaPedido;
use App\Support\ControlPedidos\RegistrarPedidos\OrigenCapturaPedido;
use App\Support\ControlPedidos\RegistrarPedidos\PoliticaAccionesRegistrarPedidos;
use App\Support\ControlPedidos\RegistrarPedidos\ProgresoCapturaTienda;
use PHPUnit\Framework\TestCase;

class RegistrarPedidosTiendaTest extends TestCase
{
    public function test_la_solicitud_exige_un_receptor_definido(): void
    {
        $decision = (new PoliticaAccionesRegistrarPedidos)->evaluar([
            'modalidad' => 'tienda',
            'tiene_cliente' => true,
            'tiene_departamento' => true,
            'tiene_folio' => true,
            'tiene_archivo' => true,
        ]);

        $this->assertTrue($decision['guardar']['habilitada']);
        $this->assertFalse($decision['principal']['habilitada']);
        $this->assertSame(DestinoRevisionRegistrarPedidos::MENSAJE_AREA, $decision['principal']['motivo']);

        $cedis = (new PoliticaAccionesRegistrarPedidos)->evaluar([
            'modalidad' => 'tienda',
            'tiene_cliente' => true,
            'tiene_departamento' => true,
            'tiene_folio' => true,
            'tiene_archivo' => true,
            'area' => DestinoRevisionRegistrarPedidos::CONTROL_PEDIDOS,
            'receptor_definido' => true,
        ]);
        $this->assertTrue($cedis['principal']['habilitada']);
        $this->assertSame('Solicitar separación a CEDIS', $cedis['principal']['etiqueta']);

        $tienda = (new PoliticaAccionesRegistrarPedidos)->evaluar([
            'modalidad' => 'tienda',
            'tiene_cliente' => true,
            'tiene_departamento' => true,
            'tiene_folio' => true,
            'tiene_archivo' => true,
            'area' => DestinoRevisionRegistrarPedidos::PREPARACION_TIENDA,
            'receptor_definido' => false,
            'motivo_receptor' => DestinoRevisionRegistrarPedidos::MENSAJE_ALMACEN,
        ]);
        $this->assertFalse($tienda['principal']['habilitada']);
        $this->assertSame(DestinoRevisionRegistrarPedidos::MENSAJE_ALMACEN, $tienda['principal']['motivo']);
        $this->assertSame('Solicitar apartado en tienda', $tienda['principal']['etiqueta']);
    }

    public function test_el_almacen_de_tienda_solo_se_precarga_si_es_unico(): void
    {
        $this->assertSame(
            ['definido' => true, 'id' => 7, 'motivo' => null],
            DestinoRevisionRegistrarPedidos::resolverAlmacen([7], null)
        );
        $this->assertFalse(DestinoRevisionRegistrarPedidos::resolverAlmacen([7, 8], null)['definido']);
        $this->assertSame(DestinoRevisionRegistrarPedidos::MENSAJE_SIN_ALMACEN, DestinoRevisionRegistrarPedidos::resolverAlmacen([], null)['motivo']);
    }

    public function test_solicitar_exige_el_archivo_y_el_borrador_no(): void
    {
        $decision = (new PoliticaAccionesRegistrarPedidos)->evaluar([
            'modalidad' => 'tienda',
            'tiene_cliente' => true,
            'tiene_departamento' => true,
            'tiene_folio' => true,
            'tiene_archivo' => false,
        ]);

        $this->assertTrue($decision['guardar']['habilitada']);
        $this->assertSame(PoliticaAccionesRegistrarPedidos::MENSAJE_ARCHIVO, $decision['principal']['motivo']);
    }

    public function test_mismo_dia_sin_pago_queda_previsto_y_no_se_confirma(): void
    {
        $atencion = (new PoliticaAccionesRegistrarPedidos)->evaluarAtencion([
            'cobertura' => 'transferencia',
            'atencion' => 'prevista',
            'facultad' => true,
            'sabado' => false,
        ]);

        $this->assertSame('prevista', $atencion['estado']);
        $this->assertFalse($atencion['confirmada']);
    }

    public function test_sabado_facultad_y_credito_bloquean_la_condicion(): void
    {
        $politica = new PoliticaAccionesRegistrarPedidos;

        $sabado = $politica->evaluarAtencion([
            'cobertura' => 'transferencia',
            'atencion' => 'prevista',
            'facultad' => true,
            'sabado' => true,
        ]);
        $this->assertSame(PoliticaAccionesRegistrarPedidos::MENSAJE_SABADO, $sabado['motivo']);

        $facultad = $politica->evaluarAtencion([
            'cobertura' => 'transferencia',
            'atencion' => 'prevista',
            'facultad' => false,
            'sabado' => false,
        ]);
        $this->assertSame(PoliticaAccionesRegistrarPedidos::MENSAJE_FACULTAD, $facultad['motivo']);

        $credito = $politica->evaluarAtencion([
            'cobertura' => 'credito',
            'atencion' => 'prevista',
            'facultad' => true,
        ]);
        $this->assertSame(PoliticaAccionesRegistrarPedidos::MENSAJE_COMBINACION, $credito['motivo']);
    }

    public function test_la_intencion_conserva_piezas_y_no_acepta_confirmacion(): void
    {
        $fusion = new FusionarSolicitudPreparacionPedido;
        $salida = $fusion->ejecutar(null, [
            'codigo_modalidad' => 'RECOGE_TIENDA_TRANSFERENCIA',
            'atencion_tienda' => 'confirmada',
            'piezas_tienda' => [
                ['sku' => 'SAV-88', 'nombre' => 'Sauvage', 'cantidad' => 1],
                ['sku' => '', 'cantidad' => 3],
            ],
        ]);

        $this->assertNull($salida['atencion_tienda']);
        $this->assertSame([
            ['sku' => 'SAV-88', 'nombre' => 'Sauvage', 'cantidad' => 1],
        ], $salida['piezas_tienda']);
    }

    public function test_el_progreso_de_tienda_no_alcanza_a_un_pedido_anterior(): void
    {
        $this->assertFalse(ProgresoCapturaTienda::aplica([
            'codigo_modalidad' => 'RECOGE_TIENDA',
        ]));
        $this->assertTrue(ProgresoCapturaTienda::aplica([
            'codigo_modalidad' => 'RECOGE_TIENDA',
            'atencion_tienda' => null,
        ]));
        $this->assertTrue(ProgresoCapturaTienda::aplica([
            'modalidad_entrega' => 'tienda',
        ]));
        $this->assertFalse(ProgresoCapturaTienda::aplica([
            'modalidad_entrega' => 'nacional',
            'codigo_modalidad' => 'ENVIO_BODEGA_NORMAL',
        ]));
        $this->assertSame('tienda', ProgresoCapturaTienda::respuesta()['perfil']);
    }

    public function test_la_captura_no_asigna_cobertura_ni_borra_la_ya_guardada(): void
    {
        $fusion = new FusionarSolicitudPreparacionPedido;
        $alta = $fusion->ejecutar(null, ['modalidad_entrega' => 'tienda']);
        $this->assertSame('tienda', $alta['modalidad_entrega']);
        $this->assertArrayNotHasKey('codigo_modalidad', $alta);
        $this->assertArrayNotHasKey('piezas_tienda', $alta);

        $previa = [
            'modalidad_entrega' => 'tienda',
            'codigo_modalidad' => 'RECOGE_TIENDA_TRANSFERENCIA',
            'atencion_tienda' => 'despues',
            'piezas_tienda' => [['sku' => 'SAV-88', 'nombre' => 'Sauvage', 'cantidad' => 1]],
        ];
        $salida = $fusion->ejecutar($previa, ['modalidad_entrega' => 'tienda']);
        $this->assertSame('RECOGE_TIENDA_TRANSFERENCIA', $salida['codigo_modalidad']);
        $this->assertSame('despues', $salida['atencion_tienda']);
        $this->assertSame($previa['piezas_tienda'], $salida['piezas_tienda']);
    }

    public function test_un_pedido_avanzado_conserva_la_modalidad(): void
    {
        $actual = ['modalidad_entrega' => 'tienda', 'codigo_modalidad' => 'RECOGE_TIENDA'];
        $this->assertNull(LimiteModalidadCapturaPedido::bloqueado(false, $actual, [
            'modalidad_entrega' => 'nacional',
            'codigo_modalidad' => 'ENVIO_BODEGA_NORMAL',
        ]));
        $this->assertSame(
            LimiteModalidadCapturaPedido::MENSAJE,
            LimiteModalidadCapturaPedido::bloqueado(true, $actual, ['modalidad_entrega' => 'nacional'])
        );
        $this->assertNull(LimiteModalidadCapturaPedido::bloqueado(true, $actual, ['modalidad_entrega' => 'tienda']));
    }

    public function test_la_modalidad_de_entrega_se_conserva_aunque_el_resto_este_vacio(): void
    {
        $fusion = new FusionarSolicitudPreparacionPedido;
        $alta = $fusion->ejecutar(null, ['modalidad_entrega' => null]);
        $this->assertArrayHasKey('modalidad_entrega', $alta);
        $this->assertNull($alta['modalidad_entrega']);

        $envio = $fusion->ejecutar($alta, [
            'modalidad_entrega' => 'nacional',
            'codigo_modalidad' => 'ENVIO_BODEGA_NORMAL',
            'atencion_tienda' => null,
            'piezas_tienda' => [],
        ]);
        $this->assertSame('nacional', $envio['modalidad_entrega']);
        $this->assertSame('ENVIO_BODEGA_NORMAL', $envio['codigo_modalidad']);
    }

    public function test_el_canal_de_tienda_solo_se_precarga_si_es_unico(): void
    {
        $this->assertSame(3, OrigenCapturaPedido::idTiendaUnico([
            ['id' => 3, 'requiere_logistica' => false, 'activo' => true],
            ['id' => 4, 'requiere_logistica' => true, 'activo' => true],
        ]));
        $this->assertNull(OrigenCapturaPedido::idTiendaUnico([
            ['id' => 3, 'requiere_logistica' => false],
            ['id' => 8, 'requiere_logistica' => false],
        ]));
    }
}
