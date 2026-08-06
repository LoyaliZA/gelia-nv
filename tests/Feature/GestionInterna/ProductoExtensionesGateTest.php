<?php

namespace Tests\Feature\GestionInterna;

use App\Models\CatalogoCategoriaProducto;
use App\Models\CategoriaExtension;
use App\Models\ExtensionProducto;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ProductoExtensionesGateTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    private ExtensionProducto $perfumeria;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class);
        foreach (['gestion_interna.productos.ver', 'gestion_interna.productos.gestionar'] as $perm) {
            Permission::findOrCreate($perm, 'web');
        }
        $this->usuario = User::factory()->create();
        $this->usuario->givePermissionTo([
            'gestion_interna.productos.ver',
            'gestion_interna.productos.gestionar',
        ]);
        $this->perfumeria = ExtensionProducto::query()->firstOrCreate(
            ['codigo' => 'perfumeria'],
            ['nombre' => 'Perfumería', 'habilitada' => true, 'version' => '1']
        );
    }

    public function test_rechaza_guardar_perfumeria_sin_extension_en_categoria(): void
    {
        $cat = CatalogoCategoriaProducto::create(['nombre' => 'Tornillos']);
        $producto = $this->producto(['categoria_id' => $cat->id]);

        $this->actingAs($this->usuario)
            ->put(route('gestion_interna.productos.update', $producto), [
                'sku' => $producto->sku,
                'descripcion' => $producto->descripcion,
                'categoria_id' => $cat->id,
                'activo' => true,
                'extensiones' => [
                    'perfumeria' => [
                        'salida' => ['Bergamota'],
                        'corazon' => [],
                        'fondo' => [],
                    ],
                ],
            ])
            ->assertSessionHasErrors('extensiones.perfumeria');
    }

    public function test_acepta_guardar_perfumeria_con_extension(): void
    {
        $cat = CatalogoCategoriaProducto::create(['nombre' => 'Fragancias']);
        CategoriaExtension::create([
            'categoria_id' => $cat->id,
            'extension_id' => $this->perfumeria->id,
            'habilitada' => true,
            'heredable' => true,
        ]);
        $producto = $this->producto(['categoria_id' => $cat->id]);

        $this->actingAs($this->usuario)
            ->put(route('gestion_interna.productos.update', $producto), [
                'sku' => $producto->sku,
                'descripcion' => $producto->descripcion,
                'categoria_id' => $cat->id,
                'activo' => true,
                'extensiones' => [
                    'perfumeria' => [
                        'salida' => ['Bergamota'],
                        'corazon' => [],
                        'fondo' => [],
                    ],
                ],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('producto_notas_olfativas', [
            'producto_id' => $producto->id,
        ]);
    }

    public function test_index_incluye_extensiones_por_categoria(): void
    {
        $cat = CatalogoCategoriaProducto::create(['nombre' => 'EDP']);
        CategoriaExtension::create([
            'categoria_id' => $cat->id,
            'extension_id' => $this->perfumeria->id,
            'habilitada' => true,
            'heredable' => true,
        ]);

        $this->actingAs($this->usuario)
            ->get(route('gestion_interna.productos.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('GestionInterna/Productos/Index', false)
                ->has('extensiones_por_categoria')
            );
    }

    private function producto(array $attrs = []): Producto
    {
        static $folio = 800000;
        $folio++;

        return Producto::create(array_merge([
            'uuid' => (string) Str::uuid(),
            'folio' => $folio,
            'sku' => 'EXT'.$folio,
            'descripcion' => 'Prod ext '.$folio,
            'activo' => true,
        ], $attrs));
    }
}
