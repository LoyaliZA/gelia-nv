<?php

namespace Tests\Unit\Rh;

use App\Models\RhColaborador;
use App\Models\RhPrestamoPagoFijo;
use App\Models\User;
use App\Services\Rh\GenerarCuotasPrestamoService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class GenerarCuotasPrestamoServiceTest extends TestCase
{
    use RefreshDatabase;

    private function crearColaborador(): RhColaborador
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        return RhColaborador::create([
            'uuid' => (string) Str::uuid(),
            'folio' => 'COL-000001',
            'nombre' => 'Test',
            'apellido_paterno' => 'Colaborador',
            'salario_base' => 2400,
            'bono_puntualidad' => 600,
            'bono_productividad' => 0,
            'horas_laboradas_oficiales' => 8,
            'salario_diario' => 80,
            'bono_puntualidad_diario' => 20,
            'bono_productividad_diario' => 0,
            'salario_por_hora' => 10,
            'salario_por_minuto' => 0.16666667,
            'activo' => true,
            'registrado_por_id' => User::first()->id,
        ]);
    }

    private function crearPrestamo(RhColaborador $colaborador, array $overrides = []): RhPrestamoPagoFijo
    {
        return RhPrestamoPagoFijo::create(array_merge([
            'uuid' => (string) Str::uuid(),
            'folio' => 'PRE-000001',
            'rh_colaborador_id' => $colaborador->id,
            'concepto' => 'Préstamo interno',
            'monto_cuota' => 500,
            'num_pagos_total' => null,
            'pagos_realizados' => 0,
            'modalidad' => RhPrestamoPagoFijo::MODALIDAD_RECURRENTE,
            'fecha_inicio' => now()->subDays(10)->toDateString(),
            'estado' => RhPrestamoPagoFijo::ESTADO_ACTIVO,
            'registrado_por_id' => User::first()->id,
        ], $overrides));
    }

    public function test_genera_cuota_recurrente_indefinida(): void
    {
        $colaborador = $this->crearColaborador();
        $prestamo = $this->crearPrestamo($colaborador);

        $fechaFin = Carbon::parse('2026-06-01');
        $fechaInicio = $fechaFin->copy()->subDays(29);

        $resultado = app(GenerarCuotasPrestamoService::class)->ejecutar($fechaInicio, $fechaFin, User::first());

        $this->assertSame(1, $resultado['generadas']);
        $prestamo->refresh();
        $this->assertSame(1, $prestamo->pagos_realizados);
        $this->assertSame(RhPrestamoPagoFijo::ESTADO_ACTIVO, $prestamo->estado);
        $this->assertDatabaseCount('rh_deducciones', 1);
    }

    public function test_liquida_recurrente_al_alcanzar_num_pagos(): void
    {
        $colaborador = $this->crearColaborador();
        $prestamo = $this->crearPrestamo($colaborador, ['num_pagos_total' => 1]);

        $fechaFin = Carbon::parse('2026-06-01');
        $fechaInicio = $fechaFin->copy()->subDays(29);

        app(GenerarCuotasPrestamoService::class)->ejecutar($fechaInicio, $fechaFin, User::first());

        $prestamo->refresh();
        $this->assertSame(RhPrestamoPagoFijo::ESTADO_LIQUIDADO, $prestamo->estado);
    }

    public function test_genera_unica_vez_y_liquida(): void
    {
        $colaborador = $this->crearColaborador();
        $prestamo = $this->crearPrestamo($colaborador, [
            'folio' => 'PRE-000002',
            'modalidad' => RhPrestamoPagoFijo::MODALIDAD_UNICA_VEZ,
            'num_pagos_total' => 1,
            'fecha_ejecucion_programada' => '2026-06-01',
        ]);

        $fechaFin = Carbon::parse('2026-06-01');
        $fechaInicio = $fechaFin->copy()->subDays(29);

        $resultado = app(GenerarCuotasPrestamoService::class)->ejecutar($fechaInicio, $fechaFin, User::first());

        $this->assertSame(1, $resultado['generadas']);
        $prestamo->refresh();
        $this->assertSame(RhPrestamoPagoFijo::ESTADO_LIQUIDADO, $prestamo->estado);
    }

    public function test_no_duplica_cuota_en_mismo_periodo(): void
    {
        $colaborador = $this->crearColaborador();
        $this->crearPrestamo($colaborador);

        $fechaFin = Carbon::parse('2026-06-01');
        $fechaInicio = $fechaFin->copy()->subDays(29);
        $service = app(GenerarCuotasPrestamoService::class);

        $service->ejecutar($fechaInicio, $fechaFin, User::first());
        $resultado = $service->ejecutar($fechaInicio, $fechaFin, User::first());

        $this->assertSame(0, $resultado['generadas']);
        $this->assertDatabaseCount('rh_deducciones', 1);
    }
}
