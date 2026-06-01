<?php

namespace Tests\Unit\Rh;

use App\Models\RhColaborador;
use App\Models\RhHorasExtra;
use App\Services\Rh\CalcularConsolidadoHorasExtraService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CalcularConsolidadoHorasExtraServiceTest extends TestCase
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

    public function test_calcular_consolidado_horas_extra_pendientes(): void
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $colaborador = $this->crearColaborador('Juan', 'COL-0001');

        // Entrada de horas extra 1: 2 horas extra, $100 total
        RhHorasExtra::create([
            'uuid' => (string) Str::uuid(),
            'folio' => 'HE-001',
            'rh_colaborador_id' => $colaborador->id,
            'fecha_turno' => '2026-06-05',
            'hora_entrada' => '08:00:00',
            'hora_salida' => '18:00:00',
            'total_horas_laboradas' => 10.0,
            'horas_normales_snapshot' => 8.0,
            'tiempo_extra_minutos' => 120,
            'horas_extra_a_pagar' => 2,
            'salario_por_hora_snapshot' => 25.0,
            'multiplicador_snapshot' => 2.0,
            'tarifa_hora_snapshot' => 25.0,
            'total_economico' => 100.0,
            'monto_horas_extra' => 100.0,
            'estado_pago' => 'pendiente',
            'registrado_por_id' => 1,
        ]);

        // Entrada de horas extra 2: 1 hora extra, $50 total
        RhHorasExtra::create([
            'uuid' => (string) Str::uuid(),
            'folio' => 'HE-002',
            'rh_colaborador_id' => $colaborador->id,
            'fecha_turno' => '2026-06-06',
            'hora_entrada' => '08:00:00',
            'hora_salida' => '17:30:00',
            'total_horas_laboradas' => 9.5,
            'horas_normales_snapshot' => 8.0,
            'tiempo_extra_minutos' => 60,
            'horas_extra_a_pagar' => 1,
            'salario_por_hora_snapshot' => 25.0,
            'multiplicador_snapshot' => 2.0,
            'tarifa_hora_snapshot' => 25.0,
            'total_economico' => 50.0,
            'monto_horas_extra' => 50.0,
            'estado_pago' => 'pendiente',
            'registrado_por_id' => 1,
        ]);

        // Entrada de horas extra 3: Ya programada para pago (no debe sumarse)
        RhHorasExtra::create([
            'uuid' => (string) Str::uuid(),
            'folio' => 'HE-003',
            'rh_colaborador_id' => $colaborador->id,
            'fecha_turno' => '2026-06-07',
            'hora_entrada' => '08:00:00',
            'hora_salida' => '17:30:00',
            'total_horas_laboradas' => 9.5,
            'horas_normales_snapshot' => 8.0,
            'tiempo_extra_minutos' => 60,
            'horas_extra_a_pagar' => 1,
            'salario_por_hora_snapshot' => 25.0,
            'multiplicador_snapshot' => 2.0,
            'tarifa_hora_snapshot' => 25.0,
            'total_economico' => 50.0,
            'monto_horas_extra' => 50.0,
            'fecha_programada_pago' => '2026-06-15',
            'estado_pago' => 'programado',
            'registrado_por_id' => 1,
        ]);

        $fechaInicio = Carbon::parse('2026-06-01');
        $fechaFin = Carbon::parse('2026-06-15');

        $service = new CalcularConsolidadoHorasExtraService();
        $resultado = $service->ejecutar($fechaInicio, $fechaFin);

        $this->assertEquals('2026-06-01', $resultado['fecha_inicio']);
        $this->assertEquals('2026-06-15', $resultado['fecha_fin']);
        $this->assertCount(1, $resultado['filas']);

        $fila = $resultado['filas'][0];
        $this->assertEquals($colaborador->id, $fila['colaborador']->id);
        // Horas extras a pagar: 2 + 1 = 3
        $this->assertEquals(3.0, $fila['horas_extra_acumuladas']);
        // Total económico acumulado: 100 + 50 = 150
        $this->assertEquals(150.0, $fila['total_economico_acumulado']);
    }

    public function test_liquidar_pago_horas_extra(): void
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $colaborador = $this->crearColaborador('Juan', 'COL-0001');

        RhHorasExtra::create([
            'uuid' => (string) Str::uuid(),
            'folio' => 'HE-001',
            'rh_colaborador_id' => $colaborador->id,
            'fecha_turno' => '2026-06-05',
            'hora_entrada' => '08:00:00',
            'hora_salida' => '18:00:00',
            'total_horas_laboradas' => 10.0,
            'horas_normales_snapshot' => 8.0,
            'tiempo_extra_minutos' => 120,
            'horas_extra_a_pagar' => 2,
            'salario_por_hora_snapshot' => 25.0,
            'multiplicador_snapshot' => 2.0,
            'tarifa_hora_snapshot' => 25.0,
            'total_economico' => 100.0,
            'monto_horas_extra' => 100.0,
            'estado_pago' => 'pendiente',
            'registrado_por_id' => 1,
        ]);

        // Simular autenticación de un usuario con permisos o Super Admin
        $user = \App\Models\User::first();
        $this->actingAs($user);

        // Llamar a la ruta POST para liquidar horas extra
        $response = $this->post(route('rh.consolidado_horas_extra.liquidar'), [
            'fecha_fin' => '2026-06-15',
            'rh_colaborador_id' => $colaborador->id,
        ]);

        $response->assertRedirect();
        
        // Verificar que el registro ahora esté liquidado (con fecha de pago hoy)
        $registro = RhHorasExtra::where('folio', 'HE-001')->first();
        $this->assertNotNull($registro->fecha_programada_pago);
        $this->assertEquals(now()->toDateString(), $registro->fecha_programada_pago->toDateString());
        $this->assertEquals('programado', $registro->estado_pago);

        // Verificar que el consolidado ahora retorne 0 horas extra pendientes
        $service = new CalcularConsolidadoHorasExtraService();
        $resultado = $service->ejecutar(Carbon::parse('2026-06-01'), Carbon::parse('2026-06-15'));
        $fila = $resultado['filas'][0];
        $this->assertEquals(0, $fila['horas_extra_acumuladas']);
        $this->assertEquals(0, $fila['total_economico_acumulado']);
    }
}
