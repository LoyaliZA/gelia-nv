<?php

namespace Tests\Feature\GestionInterna;

use App\Models\Almacen;
use App\Models\CatalogoCategoriaProducto;
use App\Models\FaseOlfativa;
use App\Models\Producto;
use App\Models\ProductoRelacion;
use App\Models\ProductoVentaAlmacen;
use App\Models\User;
use App\Services\Productos\ArmarFichaProductoService;
use App\Services\Productos\GuardarRelacionesProductoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ProductoUniversoTest extends TestCase
{
    use RefreshDatabase;

    protected User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'gestion_interna.productos.ver',
            'gestion_interna.productos.gestionar',
            'reportes.ventas.ver',
            'reportes.ventas.importar',
        ] as $perm) {
            Permission::findOrCreate($perm, 'web');
        }

        $this->usuario = User::factory()->create();
        $this->usuario->givePermissionTo([
            'gestion_interna.productos.ver',
            'gestion_interna.productos.gestionar',
            'reportes.ventas.ver',
        ]);
    }

    public function test_index_productos_en_gestion_interna(): void
    {
        $this->actingAs($this->usuario)
            ->get(route('gestion_interna.productos.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('GestionInterna/Productos/Index', false));
    }

    public function test_relaciones_simetricas_sin_cascada_al_borrar_hermano(): void
    {
        $a = $this->producto(['sku' => 'MS100', 'descripcion' => 'Mandarin 100']);
        $b = $this->producto(['sku' => 'MS200', 'descripcion' => 'Mandarin 200']);

        app(GuardarRelacionesProductoService::class)->sincronizar($a, [
            ['producto_id' => $b->id, 'tipo' => 'presentacion'],
        ]);

        $this->assertDatabaseHas('producto_relaciones', [
            'producto_id' => $a->id,
            'producto_relacionado_id' => $b->id,
            'tipo' => 'presentacion',
        ]);
        $this->assertDatabaseHas('producto_relaciones', [
            'producto_id' => $b->id,
            'producto_relacionado_id' => $a->id,
            'tipo' => 'presentacion',
        ]);

        $a->delete();

        $this->assertDatabaseMissing('productos', ['id' => $a->id]);
        $this->assertDatabaseHas('productos', ['id' => $b->id, 'sku' => 'MS200']);
        $this->assertSame(0, ProductoRelacion::query()->where('producto_id', $a->id)->count());
        $this->assertSame(0, ProductoRelacion::query()->where('producto_relacionado_id', $a->id)->count());
    }

    public function test_ficha_incluye_relacionados_y_ventas(): void
    {
        $cat = CatalogoCategoriaProducto::create([
            'nombre' => 'Perfumes',
        ]);
        $ext = \App\Models\ExtensionProducto::query()->firstOrCreate(
            ['codigo' => 'perfumeria'],
            ['nombre' => 'Perfumería', 'habilitada' => true, 'version' => '1']
        );
        \App\Models\CategoriaExtension::create([
            'categoria_id' => $cat->id,
            'extension_id' => $ext->id,
            'habilitada' => true,
            'heredable' => true,
        ]);
        $a = $this->producto(['sku' => 'F1', 'descripcion' => 'Ficha uno', 'categoria_id' => $cat->id]);
        $b = $this->producto(['sku' => 'F2', 'descripcion' => 'Ficha dos']);
        app(GuardarRelacionesProductoService::class)->sincronizar($a, [
            ['producto_id' => $b->id, 'tipo' => 'presentacion'],
        ]);

        $almacen = Almacen::create(['codigo' => 'PDV', 'nombre' => 'PDV', 'activo' => true]);
        ProductoVentaAlmacen::create([
            'producto_id' => $a->id,
            'almacen_id' => $almacen->id,
            'periodo' => '2026-07',
            'monto_venta' => 999.5,
            'cantidad_vendida' => 3,
        ]);

        $this->assertTrue(FaseOlfativa::query()->where('codigo', 'salida')->exists());

        $ficha = app(ArmarFichaProductoService::class)->paraProducto($a->fresh(), $almacen->id);
        $this->assertSame('F1', $ficha['sku']);
        $this->assertArrayHasKey('extensiones', $ficha);
        $this->assertArrayHasKey('perfumeria', $ficha['extensiones']);
        $this->assertArrayNotHasKey('notas_olfativas', $ficha);
        $this->assertArrayNotHasKey('extension_perfumeria', $ficha);
        $this->assertNotEmpty($ficha['relacionados']);
        $this->assertSame('F2', $ficha['relacionados'][0]['sku']);
        $this->assertNotEmpty($ficha['ventas']);
        $this->assertSame(999.5, $ficha['ventas'][0]['monto']);
    }

    public function test_reportes_ventas_index(): void
    {
        $this->actingAs($this->usuario)
            ->get(route('reportes.ventas.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Reportes/Ventas/Index', false));
    }

    private function producto(array $attrs = []): Producto
    {
        static $folio = 700000;
        $folio++;

        return Producto::create(array_merge([
            'uuid' => (string) Str::uuid(),
            'folio' => $folio,
            'sku' => 'SKU'.$folio,
            'descripcion' => 'Prod '.$folio,
            'activo' => true,
        ], $attrs));
    }
}
