<?php

namespace Tests\Unit\ControlPedidos;

use App\Services\ControlPedidos\FusionarSolicitudPreparacionPedido;
use App\Support\ControlPedidos\CorteMismoDiaTienda;
use Carbon\Carbon;
use Tests\TestCase;

class CorteMismoDiaTiendaTest extends TestCase
{
    public function test_el_corte_usa_el_instante_aceptado_y_no_confirma(): void
    {
        $corte = new CorteMismoDiaTienda;
        $zona = 'America/Mexico_City';

        $oportuno = $corte->evaluar(Carbon::parse('2026-11-03 13:00:00', $zona), $zona, true);
        $this->assertSame(CorteMismoDiaTienda::OPORTUNO, $oportuno['estado']);
        $this->assertFalse($oportuno['confirmada']);

        $limite = $corte->evaluar(Carbon::parse('2026-11-03 13:00:01', $zona), $zona, true);
        $this->assertSame(CorteMismoDiaTienda::TARDIO, $limite['estado']);
        $this->assertFalse($limite['confirmada']);

        $fin = $corte->evaluar(Carbon::parse('2026-11-07 10:00:00', $zona), $zona, true);
        $this->assertSame(CorteMismoDiaTienda::TARDIO, $fin['estado']);

        $sin = $corte->evaluar(Carbon::parse('2026-11-03 10:00:00', $zona), $zona, false);
        $this->assertSame(CorteMismoDiaTienda::SIN_INTENCION, $sin['estado']);
        $this->assertFalse($sin['confirmada']);
    }

    public function test_la_lectura_muestra_la_decision_y_no_deja_reescribir_la_operacion(): void
    {
        $fusion = new FusionarSolicitudPreparacionPedido;
        $guardada = [
            'modalidad_entrega' => 'tienda',
            'codigo_modalidad' => 'RECOGE_TIENDA_TRANSFERENCIA',
            'revision_disponibilidad' => [
                'estado' => 'decision_registrada',
                'confirmada' => false,
                'decision_at' => '2026-11-03T10:00:00-06:00',
                'decision_por_id' => 4,
            ],
            'segundo_traslado' => ['estado' => 'pendiente'],
            'credito_wizerp' => ['resultado' => 'aprobado'],
        ];

        $leida = $fusion->ejecutar(null, $guardada);
        $this->assertSame('decision_registrada', $leida['revision_disponibilidad']['estado']);
        $this->assertSame('pendiente', $leida['segundo_traslado']['estado']);
        $this->assertSame('aprobado', $leida['credito_wizerp']['resultado']);

        $conservada = $fusion->ejecutar($guardada, [
            'modalidad_entrega' => 'tienda',
            'credito_wizerp' => ['resultado' => 'bloqueado'],
            'segundo_traslado' => ['estado' => 'autorizado'],
        ]);
        $this->assertSame('aprobado', $conservada['credito_wizerp']['resultado']);
        $this->assertSame('pendiente', $conservada['segundo_traslado']['estado']);
    }
}
