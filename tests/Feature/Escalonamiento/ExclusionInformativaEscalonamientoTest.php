<?php

namespace Tests\Feature\Escalonamiento;

use App\Models\CatalogoListaDescuento;
use App\Models\Cliente;
use App\Models\Escalonamiento\EscalonamientoIncidencia;
use App\Services\Escalonamiento\AbrirPeriodoEscalonamiento;
use App\Services\Escalonamiento\MetricasPeriodoEscalonamiento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExclusionInformativaEscalonamientoTest extends TestCase
{
    use RefreshDatabase;

    public function test_las_exclusiones_no_cuentan_como_incidencias_abiertas_operativas(): void
    {
        $periodo = app(AbrirPeriodoEscalonamiento::class)->abrir(2026, 10);
        $lista = CatalogoListaDescuento::create([
            'nombre' => 'COLABORADOR',
            'monto_requerido' => 0,
            'activo' => true,
            'participa_escalonamiento' => false,
        ]);
        $cliente = Cliente::create([
            'numero_cliente' => '9901',
            'nombre' => 'Cliente colaborador',
            'lista_actual_id' => $lista->id,
            'monto_venta_actual' => 0,
        ]);

        EscalonamientoIncidencia::create([
            'escalonamiento_periodo_id' => $periodo->id,
            'cliente_id' => $cliente->id,
            'gravedad' => 'aviso',
            'codigo' => 'exclusion_lealtad',
            'motivo' => 'La lista del cliente no participa en escalonamiento.',
            'estado' => 'abierta',
        ]);
        EscalonamientoIncidencia::create([
            'escalonamiento_periodo_id' => $periodo->id,
            'cliente_id' => $cliente->id,
            'gravedad' => 'aviso',
            'codigo' => 'divergencia_lista_operativa',
            'motivo' => 'Divergencia de prueba.',
            'estado' => 'abierta',
        ]);

        $metricas = app(MetricasPeriodoEscalonamiento::class)->paraPeriodo($periodo);

        $this->assertSame(1, $metricas['incidencias_abiertas']);
        $this->assertSame(1, $metricas['exclusiones_informativas']);
    }
}
