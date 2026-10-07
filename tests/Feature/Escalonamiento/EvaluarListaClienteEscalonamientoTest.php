<?php

namespace Tests\Feature\Escalonamiento;

use App\Models\CatalogoListaDescuento;
use App\Models\Cliente;
use App\Models\Escalonamiento\EscalonamientoResumenCliente;
use App\Services\Escalonamiento\AbrirPeriodoEscalonamiento;
use App\Services\Escalonamiento\EvaluarListaClienteEscalonamiento;
use App\Services\Escalonamiento\RegistrarMovimiento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EvaluarListaClienteEscalonamientoTest extends TestCase
{
    use RefreshDatabase;

    public function test_fija_lista_base_y_vigente_y_asciende_intrames_sin_tocar_cliente(): void
    {
        $publico = CatalogoListaDescuento::create([
            'nombre' => 'PUBLICO GENERAL',
            'monto_requerido' => 0,
            'activo' => true,
            'participa_escalonamiento' => true,
        ]);
        $plata = CatalogoListaDescuento::create([
            'nombre' => 'MAYOREO PLATA',
            'monto_requerido' => 1000,
            'activo' => true,
            'participa_escalonamiento' => true,
        ]);
        $oro = CatalogoListaDescuento::create([
            'nombre' => 'MAYOREO ORO',
            'monto_requerido' => 5000,
            'activo' => true,
            'participa_escalonamiento' => true,
        ]);

        $cliente = Cliente::create([
            'numero_cliente' => '9901',
            'nombre' => 'Cliente Ascenso',
            'lista_actual_id' => $plata->id,
            'monto_venta_actual' => 0,
        ]);

        $periodo = app(AbrirPeriodoEscalonamiento::class)->abrir(2026, 10);

        app(RegistrarMovimiento::class)->registrar([
            'periodo_id' => $periodo->id,
            'cliente_id' => $cliente->id,
            'tipo' => 'remision',
            'folio' => 'R-1',
            'total' => '500.00',
            'efecto' => '500.00',
            'fecha_emision' => '2026-10-01',
        ]);

        $resumen = EscalonamientoResumenCliente::first();
        $this->assertSame($plata->id, $resumen->lista_base_id);
        $this->assertSame($plata->id, $resumen->lista_vigente_id);
        $this->assertSame($publico->id, $resumen->clasificacion_mes_id);

        app(RegistrarMovimiento::class)->registrar([
            'periodo_id' => $periodo->id,
            'cliente_id' => $cliente->id,
            'tipo' => 'remision',
            'folio' => 'R-2',
            'total' => '5000.00',
            'efecto' => '5000.00',
            'fecha_emision' => '2026-10-05',
        ]);

        $resumen->refresh();
        $this->assertSame($oro->id, $resumen->clasificacion_mes_id);
        $this->assertSame($oro->id, $resumen->lista_vigente_id);
        $this->assertSame($plata->id, $resumen->lista_base_id);

        $cliente->refresh();
        $this->assertSame($plata->id, $cliente->lista_actual_id);
    }

    public function test_umbral_exacto_clasifica_la_lista(): void
    {
        $lista = CatalogoListaDescuento::create([
            'nombre' => 'MAYOREO PLATA',
            'monto_requerido' => 1000,
            'activo' => true,
            'participa_escalonamiento' => true,
        ]);
        $cliente = Cliente::create([
            'numero_cliente' => '9902',
            'nombre' => 'Umbral',
            'lista_actual_id' => $lista->id,
            'monto_venta_actual' => 0,
        ]);
        $periodo = app(AbrirPeriodoEscalonamiento::class)->abrir(2026, 10);
        $evaluar = app(EvaluarListaClienteEscalonamiento::class);

        $this->assertSame($lista->id, $evaluar->clasificarAcumulado($periodo, '1000.00'));
        $this->assertNull($evaluar->clasificarAcumulado($periodo, '999.99'));
    }

    public function test_renovacion_propuesta_usa_mantenimiento_de_lista_vigente(): void
    {
        $plata = CatalogoListaDescuento::create([
            'nombre' => 'MAYOREO PLATA',
            'monto_requerido' => 1000,
            'activo' => true,
            'participa_escalonamiento' => true,
        ]);
        $cliente = Cliente::create([
            'numero_cliente' => '9903',
            'nombre' => 'Mantenimiento',
            'lista_actual_id' => $plata->id,
            'monto_venta_actual' => 0,
        ]);
        $periodo = app(AbrirPeriodoEscalonamiento::class)->abrir(2026, 10);
        $evaluar = app(EvaluarListaClienteEscalonamiento::class);

        $renovacion = $evaluar->evaluarRenovacion($periodo, $plata->id, null, '500.00');
        $this->assertFalse($renovacion['cumple_mantenimiento']);
        $this->assertSame('500.00', $renovacion['faltante_mantenimiento']);
        $this->assertNull($renovacion['lista_siguiente_propuesta_id']);

        $renovacionOk = $evaluar->evaluarRenovacion($periodo, $plata->id, $plata->id, '1000.00');
        $this->assertTrue($renovacionOk['cumple_mantenimiento']);
        $this->assertSame($plata->id, $renovacionOk['lista_siguiente_propuesta_id']);
    }
}
