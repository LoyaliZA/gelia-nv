<?php

namespace Tests\Unit\Rh;

use App\Models\CatalogoReglaIncidencia;
use App\Models\RhColaborador;
use App\Models\RhConfiguracion;
use App\Services\Rh\CalcularDeduccionReglaService;
use App\Services\Rh\CalcularHorasExtraService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CalcularDeduccionReglaServiceTest extends TestCase
{
    use RefreshDatabase;

    private function crearColaborador(array $overrides = []): RhColaborador
    {
        return RhColaborador::create(array_merge([
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
            'registrado_por_id' => 1,
        ], $overrides));
    }

    public function test_calcula_cobro_fijo_con_factor(): void
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $colaborador = $this->crearColaborador();

        $regla = CatalogoReglaIncidencia::create([
            'uuid' => (string) Str::uuid(),
            'folio' => 'REG-TEST01',
            'nombre' => 'Nota Faltante',
            'categoria' => 'operativa',
            'tipo_comportamiento' => CatalogoReglaIncidencia::COMPORTAMIENTO_COBRO_FIJO,
            'monto_fijo' => 100,
            'activo' => true,
        ]);

        $resultado = app(CalcularDeduccionReglaService::class)->ejecutar($colaborador, $regla, 2);

        $this->assertSame(100.0, (float) $resultado['monto_deduccion_base']);
        $this->assertSame(200.0, (float) $resultado['monto_total_final']);
    }

    public function test_calcula_retardo_mitad_bono_puntualidad(): void
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $colaborador = $this->crearColaborador();

        $regla = CatalogoReglaIncidencia::create([
            'uuid' => (string) Str::uuid(),
            'folio' => 'REG-TEST02',
            'nombre' => 'Retardo Menor',
            'categoria' => 'retardo',
            'tipo_comportamiento' => CatalogoReglaIncidencia::COMPORTAMIENTO_DEDUCCION_NOMINA,
            'factor_penalizacion_puntualidad' => 0.5,
            'activo' => true,
        ]);

        $resultado = app(CalcularDeduccionReglaService::class)->ejecutar($colaborador, $regla);

        $this->assertSame(10.0, (float) $resultado['deduccion_bono_puntualidad']);
        $this->assertSame(10.0, (float) $resultado['monto_total_final']);
    }
}

class CalcularHorasExtraServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_usa_tarifa_fija_y_gracia_post_salida(): void
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        RhConfiguracion::obtener()->update([
            'he_tarifa_hora_fija' => 39,
            'he_usar_tarifa_fija' => true,
            'he_multiplicador_pago' => 2,
            'he_minutos_minimos' => 30,
            'he_gracia_minutos_despues_salida' => 30,
        ]);

        $colaborador = RhColaborador::create([
            'uuid' => (string) Str::uuid(),
            'folio' => 'COL-000002',
            'nombre' => 'HE',
            'apellido_paterno' => 'Test',
            'salario_base' => 2400,
            'bono_puntualidad' => 0,
            'bono_productividad' => 0,
            'horas_laboradas_oficiales' => 8,
            'hora_entrada_oficial' => '08:00:00',
            'hora_salida_oficial' => '17:00:00',
            'salario_diario' => 80,
            'bono_puntualidad_diario' => 0,
            'bono_productividad_diario' => 0,
            'salario_por_hora' => 10,
            'salario_por_minuto' => 0.16666667,
            'activo' => true,
            'registrado_por_id' => 1,
        ]);

        $resultado = app(CalcularHorasExtraService::class)->ejecutar([
            'fecha_turno' => '2026-06-01',
            'hora_entrada' => '08:00',
            'hora_salida' => '18:00',
            'salario_por_hora_snapshot' => 10,
        ], RhConfiguracion::obtener(), $colaborador);

        $this->assertSame(39.0, (float) $resultado['tarifa_hora_snapshot']);
        $this->assertSame(78.0, (float) $resultado['monto_horas_extra']);
        $this->assertSame(1, $resultado['horas_extra_a_pagar']);
    }
}
