<?php

namespace Tests\Unit\Services\Almacenes;

use App\Models\Almacen;
use App\Models\Producto;
use App\Models\ProductoAlmacen;
use App\Models\ProductoCosto;
use App\Services\Almacenes\ProcesarFilaCostoImportacionService;
use App\Services\Almacenes\ProcesarFilaInventarioImportacionService;
use App\Services\Almacenes\ProcesarFilaProductoImportacionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeparacionImportacionCatalogoTest extends TestCase
{
    use RefreshDatabase;

    public function test_cada_importacion_escribe_solo_su_responsabilidad(): void
    {
        $almacen = Almacen::create(['codigo' => 'CEDIS', 'nombre' => 'CEDIS', 'activo' => true]);

        app(ProcesarFilaProductoImportacionService::class)->ejecutar(
            ['sku' => 'FICHA-1', 'folio' => '810001', 'descripcion' => 'Ficha', 'margen' => '30'],
            ['sku' => 'sku', 'folio' => 'folio', 'descripcion' => 'descripcion', 'margen' => 'margen'],
        );

        $ficha = Producto::query()->where('sku', 'FICHA-1')->first();
        $this->assertNotNull($ficha);
        $this->assertSame('30.00', $ficha->margen);
        $this->assertNull($ficha->precio_publico);
        $this->assertSame(0, ProductoAlmacen::query()->count());
        $this->assertSame(0, ProductoCosto::query()->count());

        app(ProcesarFilaCostoImportacionService::class)->ejecutar(
            ['sku' => 'FICHA-1', 'costo' => '100', 'costo_reposicion' => '90'],
            ['sku' => 'sku', 'costo' => 'costo', 'costo_reposicion' => 'costo_reposicion'],
        );

        $ficha->refresh();
        $this->assertSame('130.00', $ficha->precio_publico);
        $this->assertSame(1, ProductoCosto::query()->count());
        $this->assertSame(0, ProductoAlmacen::query()->count());

        app(ProcesarFilaInventarioImportacionService::class)->ejecutar(
            ['sku' => 'FICHA-1', 'existencia' => '4', 'ubicacion' => 'A-1'],
            ['sku' => 'sku', 'existencia' => 'existencia', 'ubicacion' => 'ubicacion'],
            $almacen->id,
        );

        $this->assertSame(1, ProductoCosto::query()->count());
        $this->assertSame('130.00', $ficha->fresh()->precio_publico);
        $this->assertDatabaseHas('inventarios', [
            'producto_id' => $ficha->id,
            'almacen_id' => $almacen->id,
            'existencia' => '4.000',
            'ubicacion' => 'A-1',
        ]);
        $this->assertSame(1, Producto::query()->count());
    }
}
