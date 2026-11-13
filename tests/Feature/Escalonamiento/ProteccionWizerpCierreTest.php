<?php

namespace Tests\Feature\Escalonamiento;

use App\Models\CatalogoListaDescuento;
use App\Models\Cliente;
use App\Models\Escalonamiento\EscalonamientoCierre;
use App\Models\Escalonamiento\EscalonamientoCierreDetalle;
use App\Models\Escalonamiento\EscalonamientoPeriodo;
use App\Services\Escalonamiento\AplicarCambioLista;
use App\Services\Escalonamiento\EscalonamientoAutoridadConfig;
use App\Services\Escalonamiento\Excepciones\CierreEscalonamientoException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProteccionWizerpCierreTest extends TestCase
{
    use RefreshDatabase;

    public function test_cierre_sin_autoridad_conserva_monto_lista_y_estado(): void
    {
        $this->comprobar(false, true, false);
    }

    public function test_autoridad_global_no_basta_si_cliente_no_participa(): void
    {
        $this->comprobar(true, false, false);
    }

    public function test_cierre_con_autoridad_no_reactiva_por_compra(): void
    {
        $this->comprobar(true, true, true);
    }

    private function comprobar(bool $autoridad, bool $participa, bool $permitido): void
    {
        app(EscalonamientoAutoridadConfig::class)->guardar($autoridad);
        $lista = CatalogoListaDescuento::create(['nombre' => 'MAYOREO BRONCE', 'monto_requerido' => 1, 'activo' => true, 'participa_escalonamiento' => $participa]);
        $cliente = Cliente::create(['numero_cliente' => '990044', 'nombre' => 'Ficticio', 'lista_actual_id' => $lista->id, 'monto_venta_actual' => '1234.56', 'es_inactivo' => true]);
        $periodo = EscalonamientoPeriodo::create(['anio' => 2026, 'mes' => 11, 'estado' => EscalonamientoPeriodo::ESTADO_ABIERTO]);
        $cierre = new EscalonamientoCierre(['escalonamiento_periodo_id' => $periodo->id]);
        $cierre->id = 999;
        $cierre->setRelation('periodo', $periodo);
        $detalle = new EscalonamientoCierreDetalle(['cliente_id' => $cliente->id, 'lista_siguiente_id' => $lista->id, 'aplica_cambio_lista' => false,
            'propone_inactivo' => false, 'meses_sin_compra' => 0, 'extras' => ['tuvo_actividad_compra' => true]]);
        if (! $permitido) {
            try {
                app(AplicarCambioLista::class)->aplicar($cierre, $detalle);
                $this->fail('Debió bloquear cierre');
            } catch (CierreEscalonamientoException) {
                $this->assertSame('1234.56', $cliente->fresh()->monto_venta_actual);
                $this->assertTrue($cliente->fresh()->es_inactivo);
                $this->assertSame($lista->id, $cliente->fresh()->lista_actual_id);
            }
        } else {
            // Guardar padres reales para las claves foráneas del registro de cierre.
            $cierre->id = null;
            $cierre->version = 1;
            $cierre->estado = EscalonamientoCierre::ESTADO_AUTORIZADO;
            $cierre->simulado_en = now();
            $cierre->hash_snapshot = str_repeat('a', 64);
            $cierre->save();
            $detalle->motivo = 'mantenimiento';
            app(AplicarCambioLista::class)->aplicar($cierre, $detalle);
            $this->assertSame('0.00', $cliente->fresh()->monto_venta_actual);
            $this->assertTrue($cliente->fresh()->es_inactivo);
        }
    }
}
