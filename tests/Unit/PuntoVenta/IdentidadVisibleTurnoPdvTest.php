<?php

namespace Tests\Unit\PuntoVenta;

use App\Models\PuntoVenta\TurnoPdv;
use App\Support\PuntoVenta\Turnos\IdentidadVisibleTurnoPdv;
use App\Support\PuntoVenta\Turnos\SerializadorTableroVentasPdv;
use Carbon\Carbon;
use Tests\TestCase;

class IdentidadVisibleTurnoPdvTest extends TestCase
{
    public function test_tercero_con_numero_de_cliente_muestra_quien_entra(): void
    {
        $turno = $this->turno([
            'snapshot_nombre_llamado' => 'Persona que entra',
            'snapshot_cliente_nombre' => 'Cliente Titular',
            'snapshot_json' => ['tipo_persona' => 'representante', 'nombre_presente' => 'Persona que entra'],
        ]);

        $this->assertTrue(IdentidadVisibleTurnoPdv::esTercero($turno));
        $this->assertSame('Persona que entra', IdentidadVisibleTurnoPdv::nombreVisible($turno));
        $this->assertSame('Cliente Titular', IdentidadVisibleTurnoPdv::nombreTitular($turno));
    }

    public function test_nombres_distintos_sin_marca_explicita_siguen_siendo_tercero(): void
    {
        $turno = $this->turno([
            'snapshot_nombre_llamado' => 'Persona que entra',
            'snapshot_cliente_nombre' => 'Cliente Titular',
            'snapshot_json' => ['folio' => 'V-1'],
        ]);

        $this->assertTrue(IdentidadVisibleTurnoPdv::esTercero($turno));
        $this->assertSame('Persona que entra', IdentidadVisibleTurnoPdv::nombreVisible($turno));
    }

    public function test_cliente_titular_no_se_marca_como_tercero(): void
    {
        $turno = $this->turno([
            'snapshot_nombre_llamado' => 'Cliente Titular',
            'snapshot_cliente_nombre' => 'Cliente Titular',
            'snapshot_json' => ['tipo_persona' => 'cliente'],
        ]);

        $this->assertFalse(IdentidadVisibleTurnoPdv::esTercero($turno));
        $this->assertSame('Cliente Titular', IdentidadVisibleTurnoPdv::nombreVisible($turno));
        $this->assertNull(IdentidadVisibleTurnoPdv::nombreTitular($turno));
    }

    public function test_visitante_sin_numero_de_cliente_no_se_marca_como_tercero(): void
    {
        $turno = $this->turno([
            'snapshot_nombre_llamado' => 'Ana Visitante',
            'snapshot_cliente_nombre' => null,
            'snapshot_json' => ['tipo_persona' => 'visitante'],
        ]);

        $this->assertFalse(IdentidadVisibleTurnoPdv::esTercero($turno));
        $this->assertSame('Ana Visitante', IdentidadVisibleTurnoPdv::nombreVisible($turno));
    }

    public function test_tablero_de_ventas_conserva_el_nombre_del_tercero_y_la_marca(): void
    {
        $turno = $this->turno([
            'id' => 15,
            'folio' => 'V-0015',
            'estado' => TurnoPdv::ESTADO_ASIGNADO,
            'servicio' => TurnoPdv::SERVICIO_VENTAS,
            'sucursal_id' => 3,
            'version' => 2,
            'snapshot_nombre_llamado' => 'Persona que entra',
            'snapshot_cliente_nombre' => 'Cliente Titular',
            'snapshot_json' => ['tipo_persona' => 'representante'],
        ]);

        $payload = SerializadorTableroVentasPdv::turno(
            $turno,
            [
                'espera_inicial_minutos' => 5,
                'prorroga_minutos' => 20,
                'ventana_reatencion_minutos' => 30,
            ],
            Carbon::parse('2026-10-31 12:00:00'),
        );

        $this->assertSame('Persona que entra', $payload['snapshot_nombre_llamado']);
        $this->assertTrue($payload['es_tercero']);
        $this->assertSame('Cliente Titular', $payload['cliente_titular']);
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function turno(array $atributos): TurnoPdv
    {
        $turno = new TurnoPdv($atributos);
        if (isset($atributos['id'])) {
            $turno->id = $atributos['id'];
        }

        return $turno;
    }
}
