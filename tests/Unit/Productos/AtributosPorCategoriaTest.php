<?php

namespace Tests\Unit\Productos;

use App\Models\Atributo;
use App\Models\CatalogoCategoriaProducto;
use App\Models\CategoriaAtributo;
use App\Services\Productos\GuardarAtributosProductoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AtributosPorCategoriaTest extends TestCase
{
    use RefreshDatabase;

    public function test_sin_asignacion_no_hay_atributos_permitidos(): void
    {
        Atributo::query()->create([
            'nombre' => 'Material',
            'slug' => 'material',
            'tipo_dato' => 'texto',
            'permite_multiples' => false,
            'filtrable' => true,
            'buscable' => false,
            'visible_en_ficha' => true,
            'estado' => true,
        ]);
        $cat = CatalogoCategoriaProducto::query()->create([
            'nombre' => 'Genérico',
        ]);

        $permitidos = app(GuardarAtributosProductoService::class)->atributosPermitidos($cat->id);

        $this->assertTrue($permitidos->isEmpty());
    }

    public function test_solo_atributos_asignados_a_la_categoria(): void
    {
        $a = Atributo::query()->create([
            'nombre' => 'Volumen',
            'slug' => 'volumen-test',
            'tipo_dato' => 'medida',
            'permite_multiples' => false,
            'dimension_unidad' => 'volumen',
            'filtrable' => true,
            'buscable' => false,
            'visible_en_ficha' => true,
            'estado' => true,
        ]);
        $b = Atributo::query()->create([
            'nombre' => 'Color',
            'slug' => 'color-test',
            'tipo_dato' => 'texto',
            'permite_multiples' => false,
            'filtrable' => true,
            'buscable' => false,
            'visible_en_ficha' => true,
            'estado' => true,
        ]);
        $cat = CatalogoCategoriaProducto::query()->create([
            'nombre' => 'Perfumes',
        ]);
        CategoriaAtributo::query()->create([
            'categoria_id' => $cat->id,
            'atributo_id' => $a->id,
            'requerido' => false,
            'heredable' => true,
            'orden' => 1,
        ]);

        $permitidos = app(GuardarAtributosProductoService::class)->atributosPermitidos($cat->id);

        $this->assertCount(1, $permitidos);
        $this->assertSame($a->id, $permitidos->first()->id);
        $this->assertFalse($permitidos->contains('id', $b->id));
    }
}
