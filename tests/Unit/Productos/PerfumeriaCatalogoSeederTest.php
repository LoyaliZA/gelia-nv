<?php

namespace Tests\Unit\Productos;

use App\Models\Atributo;
use App\Models\CatalogoCategoriaProducto;
use App\Models\CategoriaAtributo;
use App\Models\CategoriaExtension;
use App\Models\ExtensionProducto;
use App\Models\NotaOlfativa;
use App\Services\Productos\ResolverExtensionesProductoService;
use Database\Seeders\PerfumeriaCatalogoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PerfumeriaCatalogoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_deja_perfumeria_lista_para_productos(): void
    {
        $this->seed(PerfumeriaCatalogoSeeder::class);
        $this->seed(PerfumeriaCatalogoSeeder::class); // idempotente

        $ext = ExtensionProducto::query()->where('codigo', 'perfumeria')->where('habilitada', true)->first();
        $this->assertNotNull($ext);

        $padre = CatalogoCategoriaProducto::query()->where('nombre', 'Perfumería')->first();
        $this->assertNotNull($padre);
        $this->assertTrue(
            CategoriaExtension::query()
                ->where('categoria_id', $padre->id)
                ->where('extension_id', $ext->id)
                ->where('habilitada', true)
                ->exists()
        );

        $perfume = CatalogoCategoriaProducto::query()->where('nombre', 'Perfumes')->first();
        $this->assertNotNull($perfume);
        $this->assertTrue(app(ResolverExtensionesProductoService::class)->tieneEnCategoria($perfume->id, 'perfumeria'));

        $this->assertTrue(Atributo::query()->where('slug', 'familia_olfativa')->exists());
        $this->assertTrue(Atributo::query()->where('slug', 'ano_lanzamiento')->exists());
        $this->assertGreaterThan(0, CategoriaAtributo::query()->where('categoria_id', $perfume->id)->count());
        $this->assertGreaterThan(20, NotaOlfativa::query()->where('estado', true)->count());
        $this->assertTrue(NotaOlfativa::query()->where('slug', 'vainilla')->exists());
    }
}
