<?php

namespace Tests\Unit\Activos;

use App\Models\CatalogoTipoActivo;
use App\Services\Activos\ValidarAtributosActivoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ValidarAtributosActivoServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_aplica_s_n_cuando_serial_vacio(): void
    {
        $tipo = CatalogoTipoActivo::create([
            'nombre' => 'Equipo TI Unit',
            'slug' => 'equipo-ti-unit-' . Str::random(4),
            'categoria' => 'tecnologico',
            'esquema_atributos' => [
                'fields' => [
                    ['key' => 'serial', 'label' => 'Número de serie', 'type' => 'text', 'required' => true],
                    ['key' => 'mac', 'label' => 'MAC', 'type' => 'text'],
                ],
            ],
            'activo' => true,
        ]);

        $service = app(ValidarAtributosActivoService::class);
        $resultado = $service->ejecutar($tipo, []);

        $this->assertSame('S/N', $resultado['serial']);
        $this->assertSame('N/A', $resultado['mac']);
    }
}
