<?php

namespace Tests\Unit\Rh;

use App\Models\CatalogoReglaIncidencia;
use App\Models\Departamento;
use App\Models\RhColaborador;
use App\Models\User;
use App\Services\Rh\FiltrarReglasIncidenciaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FiltrarReglasIncidenciaServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_filtra_por_colaborador_y_usuario(): void
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $depto = Departamento::first();
        $usuario = User::factory()->create();
        if ($depto) {
            $usuario->departamentos()->sync([$depto->id]);
        }

        $colaborador = RhColaborador::create([
            'uuid' => (string) Str::uuid(),
            'folio' => 'COL-FILT01',
            'nombre' => 'Filtro',
            'apellido_paterno' => 'Test',
            'departamento_id' => $depto?->id,
            'salario_base' => 1000,
            'bono_puntualidad' => 0,
            'bono_productividad' => 0,
            'horas_laboradas_oficiales' => 8,
            'salario_diario' => 33.33,
            'bono_puntualidad_diario' => 0,
            'bono_productividad_diario' => 0,
            'salario_por_hora' => 4,
            'salario_por_minuto' => 0.06,
            'activo' => true,
            'registrado_por_id' => $usuario->id,
        ]);

        $universal = CatalogoReglaIncidencia::create([
            'uuid' => (string) Str::uuid(),
            'folio' => 'REG-FILT01',
            'nombre' => 'Universal',
            'categoria' => 'operativa',
            'tipo_comportamiento' => CatalogoReglaIncidencia::COMPORTAMIENTO_COBRO_FIJO,
            'monto_fijo' => 50,
            'activo' => true,
        ]);

        $soloOtroDepto = CatalogoReglaIncidencia::create([
            'uuid' => (string) Str::uuid(),
            'folio' => 'REG-FILT02',
            'nombre' => 'Otro depto',
            'categoria' => 'operativa',
            'tipo_comportamiento' => CatalogoReglaIncidencia::COMPORTAMIENTO_COBRO_FIJO,
            'monto_fijo' => 75,
            'activo' => true,
        ]);

        if ($depto) {
            $otroDepto = Departamento::where('id', '!=', $depto->id)->first();
            if ($otroDepto) {
                $soloOtroDepto->departamentosAplicables()->sync([$otroDepto->id]);
            }
        }

        $reglas = app(FiltrarReglasIncidenciaService::class)->ejecutar($usuario, $colaborador);

        $this->assertTrue($reglas->contains('id', $universal->id));
        $this->assertFalse($reglas->contains('id', $soloOtroDepto->id));
    }
}
