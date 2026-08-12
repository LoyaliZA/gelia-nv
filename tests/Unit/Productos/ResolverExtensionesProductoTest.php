<?php

namespace Tests\Unit\Productos;

use App\Models\CatalogoCategoriaProducto;
use App\Models\CategoriaExtension;
use App\Models\ExtensionProducto;
use App\Models\Producto;
use App\Services\Productos\ResolverExtensionesProductoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ResolverExtensionesProductoTest extends TestCase
{
    use RefreshDatabase;

    private ResolverExtensionesProductoService $resolver;

    private ExtensionProducto $perfumeria;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = app(ResolverExtensionesProductoService::class);
        $this->perfumeria = ExtensionProducto::query()->firstOrCreate(
            ['codigo' => 'perfumeria'],
            ['nombre' => 'Perfumería', 'habilitada' => true, 'version' => '1']
        );
    }

    public function test_sin_asignacion_no_tiene_extension(): void
    {
        $cat = CatalogoCategoriaProducto::create(['nombre' => 'Ferretería']);
        $this->assertFalse($this->resolver->tieneEnCategoria($cat->id, 'perfumeria'));
        $this->assertFalse($this->resolver->algunaCategoriaUsa('perfumeria'));
    }

    public function test_asignacion_directa_habilitada(): void
    {
        $cat = CatalogoCategoriaProducto::create(['nombre' => 'Perfumes']);
        CategoriaExtension::create([
            'categoria_id' => $cat->id,
            'extension_id' => $this->perfumeria->id,
            'habilitada' => true,
            'heredable' => true,
        ]);

        $this->assertTrue($this->resolver->tieneEnCategoria($cat->id, 'perfumeria'));
        $this->assertTrue($this->resolver->algunaCategoriaUsa('perfumeria'));
    }

    public function test_herencia_desde_padre(): void
    {
        $padre = CatalogoCategoriaProducto::create(['nombre' => 'Perfumería']);
        $hijo = CatalogoCategoriaProducto::create(['nombre' => 'EDP', 'parent_id' => $padre->id]);
        CategoriaExtension::create([
            'categoria_id' => $padre->id,
            'extension_id' => $this->perfumeria->id,
            'habilitada' => true,
            'heredable' => true,
        ]);

        $codes = $this->resolver->paraCategoria($hijo)->pluck('codigo');
        $this->assertTrue($codes->contains('perfumeria'));
        $this->assertSame('heredada', $this->resolver->paraCategoria($hijo)->firstWhere('codigo', 'perfumeria')['origen']);
    }

    public function test_bloqueo_directo_deshabilitado(): void
    {
        $padre = CatalogoCategoriaProducto::create(['nombre' => 'Padre']);
        $hijo = CatalogoCategoriaProducto::create(['nombre' => 'Hijo', 'parent_id' => $padre->id]);
        CategoriaExtension::create([
            'categoria_id' => $padre->id,
            'extension_id' => $this->perfumeria->id,
            'habilitada' => true,
            'heredable' => true,
        ]);
        CategoriaExtension::create([
            'categoria_id' => $hijo->id,
            'extension_id' => $this->perfumeria->id,
            'habilitada' => false,
            'heredable' => true,
        ]);

        $this->assertFalse($this->resolver->tieneEnCategoria($hijo->id, 'perfumeria'));
    }

    public function test_extension_global_deshabilitada(): void
    {
        $cat = CatalogoCategoriaProducto::create(['nombre' => 'P']);
        CategoriaExtension::create([
            'categoria_id' => $cat->id,
            'extension_id' => $this->perfumeria->id,
            'habilitada' => true,
            'heredable' => true,
        ]);
        $this->perfumeria->update(['habilitada' => false]);
        $this->resolver->invalidarCacheCategoria($cat->id);

        $this->assertFalse($this->resolver->tieneEnCategoria($cat->id, 'perfumeria'));
        $this->assertFalse($this->resolver->algunaCategoriaUsa('perfumeria'));
    }

    public function test_producto_sin_categoria(): void
    {
        $p = Producto::create([
            'uuid' => (string) Str::uuid(),
            'folio' => 911001,
            'sku' => 'SINCAT',
            'descripcion' => 'Sin cat',
            'activo' => true,
        ]);
        $this->assertFalse($this->resolver->tiene($p, 'perfumeria'));
    }
}
