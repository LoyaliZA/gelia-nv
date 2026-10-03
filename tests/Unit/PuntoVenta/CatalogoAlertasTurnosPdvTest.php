<?php

namespace Tests\Unit\PuntoVenta;

use App\Models\PuntoVenta\TurnoPdvEvento;
use App\Support\PuntoVenta\Alertas\CatalogoAlertasTurnosPdv;
use PHPUnit\Framework\TestCase;

class CatalogoAlertasTurnosPdvTest extends TestCase
{
    public function test_guion_de_llamado_es_breve_y_no_dice_reatencion_ni_lista(): void
    {
        $mensaje = CatalogoAlertasTurnosPdv::guionTerminal(TurnoPdvEvento::TIPO_REATENCION, [
            'folio' => 'V-300',
            'snapshot_nombre_llamado' => 'María López',
            'prioridad_diamante' => true,
            'prioridad_discapacidad' => true,
            'atencion' => ['primer_nombre' => 'Ana'],
        ]);

        $this->assertSame(
            'Ana, tienes un nuevo cliente: María López.',
            $mensaje,
        );
        $this->assertStringNotContainsString('Re-atención', (string) $mensaje);
        $this->assertStringNotContainsString('lista', strtolower((string) $mensaje));
    }

    public function test_sala_publica_omite_discapacidad_y_conserva_prioridad(): void
    {
        $mensaje = CatalogoAlertasTurnosPdv::guionLlamado([
            'folio' => 'V-301',
            'snapshot_nombre_llamado' => 'María López',
            'prioridad_diamante' => true,
            'prioridad_vip' => true,
            'prioridad_discapacidad' => true,
            'atencion_primer_nombre' => 'Ana',
        ], true);

        $this->assertSame('Turno V-301. María López. Tiene prioridad. Pase con Ana.', $mensaje);
    }

    public function test_prorroga_pausa_y_resguardo_tienen_guion_unico(): void
    {
        $this->assertSame(
            'Prórroga iniciada. Turno V-400.',
            CatalogoAlertasTurnosPdv::guionTerminal(TurnoPdvEvento::TIPO_PRORROGA, ['folio' => 'V-400']),
        );
        $this->assertSame(
            'Pausa activa. Ana.',
            CatalogoAlertasTurnosPdv::guionTerminal('pausa.iniciada', ['primer_nombre' => 'Ana']),
        );
        $this->assertSame(
            'Nuevo resguardo pendiente de aprobación. R-10.',
            CatalogoAlertasTurnosPdv::guionTerminal('resguardo.registro_manual_creado', [
                'folio' => 'R-10',
                'snapshot_cliente_nombre' => 'Cliente Uno',
            ]),
        );
    }
}
