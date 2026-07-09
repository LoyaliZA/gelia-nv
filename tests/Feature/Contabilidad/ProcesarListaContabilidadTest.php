<?php

namespace Tests\Feature\Contabilidad;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ProcesarListaContabilidadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::findOrCreate('contabilidad.importar', 'web');
    }

    public function test_procesar_lista_respeta_mapeo_bronce(): void
    {
        Storage::fake('local');

        $usuario = User::factory()->create();
        $usuario->givePermissionTo('contabilidad.importar');

        $path = 'temp/conta_feature_test.csv';
        Storage::put(
            $path,
            "SKU,Descripcion,Plataformas,Bronce\n001,Producto A,100.00,80.00\n"
        );

        $response = $this->actingAs($usuario)->postJson(route('contabilidad.procesar_lista'), [
            'file_path' => $path,
            'mapping' => [
                'sku' => 'SKU',
                'precio_base' => 'Bronce',
                'descripcion' => 'Descripcion',
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.001.precio', 80)
            ->assertJsonPath('data.001.nombre', 'Producto A')
            ->assertJsonPath('mapping.precio_base', 'Bronce');

        Storage::assertMissing($path);
    }
}
