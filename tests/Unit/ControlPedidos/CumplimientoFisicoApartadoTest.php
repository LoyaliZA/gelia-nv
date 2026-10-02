<?php

namespace Tests\Unit\ControlPedidos;

use App\Models\ControlPedidos\CatalogoModalidadPreparacionPedido;
use App\Models\ControlPedidos\PedidoBmaCumplimientoFisico;
use App\Support\ControlPedidos\MaquinaEstadosCumplimientoFisico;
use App\Support\ControlPedidos\PoliticaSalidaPreparacion;
use App\Support\ControlPedidos\PlazosApartadoFisico;
use Carbon\Carbon;
use Tests\TestCase;

class CumplimientoFisicoApartadoTest extends TestCase
{
    public function test_recogida_separada_puede_pasar_a_devolucion_y_solo_entonces_al_anaquel(): void
    {
        $this->assertTrue(MaquinaEstadosCumplimientoFisico::puedeTransicionar(
            PedidoBmaCumplimientoFisico::ESTADO_SEPARADA,
            PedidoBmaCumplimientoFisico::ESTADO_DEVOLUCION_PENDIENTE
        ));
        $this->assertFalse(MaquinaEstadosCumplimientoFisico::puedeTransicionar(
            PedidoBmaCumplimientoFisico::ESTADO_SEPARADA,
            PedidoBmaCumplimientoFisico::ESTADO_DEVUELTA_ANAQUEL
        ));
        $this->assertTrue(MaquinaEstadosCumplimientoFisico::puedeTransicionar(
            PedidoBmaCumplimientoFisico::ESTADO_DEVOLUCION_PENDIENTE,
            PedidoBmaCumplimientoFisico::ESTADO_DEVUELTA_ANAQUEL
        ));
    }

    public function test_prorroga_de_un_dia_habil_salta_el_fin_de_semana(): void
    {
        $viernes = Carbon::parse('2026-10-02 20:00:00', 'America/Mexico_City');
        $nuevo = PlazosApartadoFisico::sumarDiasHabiles($viernes, 1);

        $this->assertSame('2026-10-05 20:00:00', $nuevo->format('Y-m-d H:i:s'));
    }

    public function test_recoge_hoy_cierra_sin_remision_ni_resguardo_y_acepta_por_cobrar(): void
    {
        $politica = new PoliticaSalidaPreparacion(CatalogoModalidadPreparacionPedido::CODIGO_RECOGE_TIENDA);

        $this->assertTrue($politica->entregaDirectaSinResguardo());
        $this->assertFalse($politica->abreResguardo());
        $this->assertFalse($politica->exigeRemisionParaCierre());
        $this->assertTrue($politica->permitePorCobrar());
        $this->assertTrue(MaquinaEstadosCumplimientoFisico::puedeTransicionar(
            PedidoBmaCumplimientoFisico::ESTADO_LISTA_PARA_SALIDA,
            PedidoBmaCumplimientoFisico::ESTADO_ENTREGADA
        ));
        $this->assertFalse(MaquinaEstadosCumplimientoFisico::puedeTransicionar(
            PedidoBmaCumplimientoFisico::ESTADO_SEPARADA,
            PedidoBmaCumplimientoFisico::ESTADO_ENTREGADA
        ));
    }

    public function test_transferencia_exige_pago_y_resguardo_sin_segunda_entrega_directa(): void
    {
        $politica = new PoliticaSalidaPreparacion(CatalogoModalidadPreparacionPedido::CODIGO_RECOGE_TIENDA_TRANSFERENCIA);

        $this->assertTrue($politica->abreResguardo());
        $this->assertFalse($politica->permitePorCobrar());
        $this->assertFalse($politica->entregaDirectaSinResguardo());
        $this->assertFalse($politica->exigeRemisionParaCierre());
    }

    public function test_cierre_operativo_conserva_el_dia_en_la_zona_de_la_tienda(): void
    {
        $momento = Carbon::parse('2026-10-02 15:00:00', 'America/Mexico_City');
        $cierre = PlazosApartadoFisico::cierreOperativo($momento, 'America/Mexico_City', '20:00');

        $this->assertSame('2026-10-02 20:00:00', $cierre->timezone('America/Mexico_City')->format('Y-m-d H:i:s'));
    }
}
