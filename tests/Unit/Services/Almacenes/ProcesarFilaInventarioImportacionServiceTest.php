<?php

namespace Tests\Unit\Services\Almacenes;

use App\Models\Almacen;
use App\Models\Producto;
use App\Models\ProductoCosto;
use App\Services\Almacenes\ProcesarFilaInventarioImportacionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcesarFilaInventarioImportacionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_crea_producto_ni_escribe_costo(): void
    {
        $almacen = Almacen::create([
            'codigo' => 'CEDIS',
            'nombre' => 'CEDIS',
            'activo' => true,
        ]);
        $producto = Producto::create([
            'sku' => 'SOLOPV',
            'folio' => 800001,
            'descripcion' => 'Ya existe',
            'activo' => true,
        ]);

        app(ProcesarFilaInventarioImportacionService::class)->ejecutar(
            [
                'sku' => 'SOLOPV',
                'folio' => '800001',
                'existencia' => '5',
                'costo' => '88.5',
            ],
            [
                'sku' => 'sku',
                'folio' => 'folio',
                'existencia' => 'existencia',
                'costo' => 'costo',
            ],
            $almacen->id,
        );

        $this->assertSame(1, Producto::where('sku', 'SOLOPV')->count());
        $this->assertSame(0, ProductoCosto::where('producto_id', $producto->id)->count());
        $this->assertDatabaseHas('inventarios', [
            'producto_id' => $producto->id,
            'almacen_id' => $almacen->id,
            'existencia' => '5.000',
        ]);
    }
}
