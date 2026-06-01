<?php

namespace Tests\Unit\Rh;

use App\Models\CatalogoReglaIncidencia;
use App\Models\RhColaborador;
use App\Models\RhDeduccion;
use App\Models\RhSalidaPersonal;
use App\Services\Rh\CalcularConsolidadoDeduccionesService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CalcularConsolidadoDeduccionesServiceTest extends TestCase
{
    use RefreshDatabase;

    private function crearColaborador(string $nombre, string $folio): RhColaborador
    {
        $departamento = \App\Models\Departamento::create(['nombre' => 'Test Dept ' . Str::random(5)]);
        $area = \App\Models\Area::create([
            'nombre' => 'Test Area ' . Str::random(5),
            'departamento_id' => $departamento->id,
        ]);
        $puesto = \App\Models\CatalogoPuesto::create([
            'uuid' => (string) Str::uuid(),
            'folio' => 'PUE-' . Str::random(5),
            'nombre' => 'Test Puesto ' . Str::random(5),
            'activo' => true,
        ]);

        return RhColaborador::create([
            'uuid' => (string) Str::uuid(),
            'folio' => $folio,
            'nombre' => $nombre,
            'apellido_paterno' => 'Test',
            'departamento_id' => $departamento->id,
            'area_id' => $area->id,
            'catalogo_puesto_id' => $puesto->id,
            'salario_base' => 3000,
            'bono_puntualidad' => 600,
            'bono_productividad' => 400,
            'horas_laboradas_oficiales' => 8,
            'salario_diario' => 100,
            'bono_puntualidad_diario' => 20,
            'bono_productividad_diario' => 13.33,
            'salario_por_hora' => 12.5,
            'salario_por_minuto' => 0.2083,
            'activo' => true,
            'registrado_por_id' => 1,
        ]);
    }

    public function test_calcular_consolidado_deducciones(): void
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $colaborador = $this->crearColaborador('Juan', 'COL-0001');

        // 1. Crear Préstamo (simulado como deducción con rh_prestamo_pago_fijo_id = 999)
        RhDeduccion::create([
            'uuid' => (string) Str::uuid(),
            'folio' => 'DED-001',
            'rh_colaborador_id' => $colaborador->id,
            'rh_prestamo_pago_fijo_id' => 999,
            'fecha_ocurrencia' => '2026-06-05',
            'monto_total_final' => 150.00,
            'estado_deduccion' => 'pendiente_nomina',
            'registrado_por_id' => 1,
        ]);

        // 2. Crear Incidencia Operativa (daño de equipo)
        $reglaOperativa = CatalogoReglaIncidencia::create([
            'uuid' => (string) Str::uuid(),
            'folio' => 'REG-001',
            'nombre' => 'Daño de Equipo',
            'categoria' => 'operativa',
            'tipo_comportamiento' => 'cobro_fijo',
            'monto_fijo' => 500,
            'activo' => true,
        ]);

        RhDeduccion::create([
            'uuid' => (string) Str::uuid(),
            'folio' => 'DED-002',
            'rh_colaborador_id' => $colaborador->id,
            'catalogo_regla_incidencia_id' => $reglaOperativa->id,
            'fecha_ocurrencia' => '2026-06-08',
            'monto_total_final' => 500.00,
            'estado_deduccion' => 'pendiente_nomina',
            'registrado_por_id' => 1,
        ]);

        // 3. Crear Incidencia de Falta/Retardo
        $reglaFalta = CatalogoReglaIncidencia::create([
            'uuid' => (string) Str::uuid(),
            'folio' => 'REG-002',
            'nombre' => 'Retardo Injustificado',
            'categoria' => 'retardo',
            'tipo_comportamiento' => 'deduccion_nomina',
            'activo' => true,
        ]);

        RhDeduccion::create([
            'uuid' => (string) Str::uuid(),
            'folio' => 'DED-003',
            'rh_colaborador_id' => $colaborador->id,
            'catalogo_regla_incidencia_id' => $reglaFalta->id,
            'fecha_ocurrencia' => '2026-06-10',
            'monto_total_final' => 70.00,
            'deduccion_salario_base' => 40.00,
            'deduccion_bono_productividad' => 20.00,
            'deduccion_bono_puntualidad' => 10.00,
            'estado_deduccion' => 'pendiente_nomina',
            'registrado_por_id' => 1,
        ]);

        // 4. Crear Salida Personal
        RhSalidaPersonal::create([
            'uuid' => (string) Str::uuid(),
            'folio' => 'SAL-001',
            'rh_colaborador_id' => $colaborador->id,
            'fecha_evento' => '2026-06-12',
            'hora_salida' => '10:00:00',
            'hora_regreso' => '10:30:00',
            'minutos_ausente' => 30,
            'salario_por_minuto_snapshot' => 2.00,
            'monto_a_deducir' => 60.00,
            'motivo' => 'Trámite personal',
            'registrado_por_id' => 1,
        ]);

        // Ejecutar el servicio de cálculo
        $fechaInicio = Carbon::parse('2026-06-01');
        $fechaFin = Carbon::parse('2026-06-15');

        $service = new CalcularConsolidadoDeduccionesService();
        $resultado = $service->ejecutar($fechaInicio, $fechaFin);

        $this->assertEquals('2026-06-01', $resultado['fecha_inicio']);
        $this->assertEquals('2026-06-15', $resultado['fecha_fin']);
        $this->assertCount(1, $resultado['filas']);

        $fila = $resultado['filas'][0];
        $this->assertEquals($colaborador->id, $fila['colaborador']->id);
        $this->assertEquals(150.00, $fila['prestamos']);
        $this->assertEquals(500.00, $fila['incidencias']);
        $this->assertEquals(40.00, $fila['faltas_salario']);
        $this->assertEquals(20.00, $fila['faltas_productividad']);
        $this->assertEquals(10.00, $fila['faltas_puntualidad']);
        $this->assertEquals(60.00, $fila['salidas_personales']);

        // Gran total: 150 + 500 + (40 + 20 + 10) + 60 = 780
        $this->assertEquals(780.00, $fila['gran_total']);
        // Sin incidencias: 780 - 500 = 280
        $this->assertEquals(280.00, $fila['sin_incidencias']);
    }
}
