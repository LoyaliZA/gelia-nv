<?php

namespace Tests\Unit\PuntoVenta\Resguardos;

use App\Models\Departamento;
use App\Support\PuntoVenta\Resguardos\DepartamentosOrigenResguardoManualPdv;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepartamentosOrigenResguardoManualPdvTest extends TestCase
{
    use RefreshDatabase;

    public function test_lista_solo_departamentos_activos_y_marcados_para_resguardo(): void
    {
        $visible = Departamento::query()->create([
            'nombre' => 'Bellaroma City Center',
            'activo' => true,
            'visible_origen_resguardo_pdv' => true,
        ]);
        Departamento::query()->create([
            'nombre' => 'Cedis',
            'activo' => true,
            'visible_origen_resguardo_pdv' => false,
        ]);
        Departamento::query()->create([
            'nombre' => 'Aromas',
            'activo' => false,
            'visible_origen_resguardo_pdv' => true,
        ]);

        $ids = collect(DepartamentosOrigenResguardoManualPdv::serializar())
            ->pluck('id')
            ->all();

        $this->assertSame([$visible->id], $ids);
    }

    public function test_encontrar_activo_rechaza_departamento_no_configurado(): void
    {
        $oculto = Departamento::query()->create([
            'nombre' => 'TI',
            'activo' => true,
            'visible_origen_resguardo_pdv' => false,
        ]);

        $this->assertNull(DepartamentosOrigenResguardoManualPdv::encontrarActivo($oculto->id));
    }
}
