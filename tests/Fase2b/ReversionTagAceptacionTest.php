<?php

namespace Tests\Fase2b;

use PHPUnit\Framework\TestCase;

/**
 * Aceptación de Fase 2B. Fuera de las suites Unit y Feature.
 * Está incompleta a propósito: no cuenta como reversión aprobada.
 * El contrato está en docs/clientes/FASE_2B_REVERSION_TAG.md.
 */
class ReversionTagAceptacionTest extends TestCase
{
    public function test_revertir_la_unica_solicitud_restaura_la_condicion_oficial(): void
    {
        $this->markTestIncomplete(
            'Cancelar, rechazar o vencer la única solicitud restaura lista, tipo y TAG oficiales o anteriores, sin cambiar monto_venta_actual.'
        );
    }

    public function test_otra_solicitud_vigente_conserva_el_beneficio(): void
    {
        $this->markTestIncomplete(
            'Si otra solicitud vigente sostiene la misma lista, tipo o TAG, la reversión no los quita.'
        );
    }

    public function test_el_oficial_que_ya_califica_la_lista_no_se_rebaja(): void
    {
        $this->markTestIncomplete(
            'Si monto_venta_actual ya califica la lista provisional, cancelar la cotización no baja la lista.'
        );
    }

    public function test_repetir_la_reversion_es_idempotente(): void
    {
        $this->markTestIncomplete(
            'Repetir la reversión no cambia el cliente por segunda vez ni duplica la auditoría de negocio.'
        );
    }

    public function test_revertir_la_primera_no_deshace_la_segunda(): void
    {
        $this->markTestIncomplete(
            'Revertir la primera de dos solicitudes no deshace el beneficio que la segunda todavía justifica.'
        );
    }

    public function test_aprobacion_y_cancelacion_concurrentes_respetan_la_vigente(): void
    {
        $this->markTestIncomplete(
            'En MySQL, aprobación y cancelación concurrentes no dejan el beneficio de la solicitud excluida ni quitan el de la vigente.'
        );
    }

    public function test_la_marca_historica_y_la_conciliacion_anulada_permanecen(): void
    {
        $this->markTestIncomplete(
            'La reversión no reescribe cubierto_por_carga_masiva ni borra una conciliación anulada.'
        );
    }

    public function test_la_autoridad_apagada_no_cambia_y_la_encendida_no_queda_activa(): void
    {
        $this->markTestIncomplete(
            'Con autoridad apagada se lee monto_venta_actual. Una prueba aislada con autoridad encendida no la deja activa.'
        );
    }
}
