<?php

namespace Tests\Feature\Almacenes;

use App\Models\Almacen;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AlcanceSucursalAlmacenesTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    private Sucursal $sucursalA;

    private Sucursal $sucursalB;

    private Almacen $almacenA;

    private Almacen $almacenB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([
            ValidateCsrfToken::class,
            PreventRequestForgery::class,
        ]);

        foreach ([
            'almacenes.inventarios.ver',
            'almacenes.inventarios.importar',
            'almacenes.inventarios.gestionar',
        ] as $perm) {
            Permission::findOrCreate($perm, 'web');
        }

        $this->usuario = User::factory()->create();
        $this->usuario->givePermissionTo([
            'almacenes.inventarios.ver',
            'almacenes.inventarios.importar',
        ]);

        $this->sucursalA = Sucursal::factory()->create(['nombre' => 'Sucursal A']);
        $this->sucursalB = Sucursal::factory()->create(['nombre' => 'Sucursal B']);
        $this->usuario->concederAccesoSucursal($this->sucursalA, esPrincipal: true);

        $this->almacenA = Almacen::create([
            'sucursal_id' => $this->sucursalA->id,
            'codigo' => 'ALM-A',
            'nombre' => 'Almacén A',
            'activo' => true,
        ]);
        $this->almacenB = Almacen::create([
            'sucursal_id' => $this->sucursalB->id,
            'codigo' => 'ALM-B',
            'nombre' => 'Almacén B',
            'activo' => true,
        ]);
    }

    public function test_importaciones_index_solo_lista_almacenes_de_sucursales_asignadas(): void
    {
        $response = $this->actingAs($this->usuario)->get(route('almacenes.importaciones.index'));

        $response->assertOk();
        $props = $response->viewData('page')['props'] ?? [];
        $ids = collect($props['almacenes'] ?? [])->pluck('id')->all();
        $this->assertContains($this->almacenA->id, $ids);
        $this->assertNotContains($this->almacenB->id, $ids);
    }

    public function test_analizar_rechaza_almacen_de_sucursal_no_asignada(): void
    {
        $archivo = UploadedFile::fake()->create('datos.csv', 100, 'text/csv');

        $response = $this->actingAs($this->usuario)->postJson(route('almacenes.importaciones.analizar'), [
            'archivo' => $archivo,
            'operaciones' => ['cantidades_referencia'],
            'almacen_id' => $this->almacenB->id,
            'sucursal_id' => $this->sucursalB->id,
        ]);

        $response->assertStatus(403);
    }

    public function test_establecer_sucursal_activa_solo_operables(): void
    {
        $this->actingAs($this->usuario)
            ->putJson(route('almacenes.contexto.sucursal_activa'), ['sucursal_id' => $this->sucursalA->id])
            ->assertOk()
            ->assertJsonPath('sucursal_activa.id', $this->sucursalA->id);

        $this->actingAs($this->usuario)
            ->putJson(route('almacenes.contexto.sucursal_activa'), ['sucursal_id' => $this->sucursalB->id])
            ->assertUnprocessable();
    }

    public function test_catalogo_modulo_requiere_sucursal_operable_en_alta(): void
    {
        Permission::findOrCreate('almacenes.inventarios.gestionar', 'web');
        $this->usuario->givePermissionTo('almacenes.inventarios.gestionar');

        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);

        app(\App\Services\Almacenes\AlcanceAlmacenesService::class)
            ->asegurarSucursalOperable($this->usuario, $this->sucursalB->id);
    }
}
