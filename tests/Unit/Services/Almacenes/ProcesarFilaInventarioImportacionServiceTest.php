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

    public function test_solo_precio_venta_crea_fila_en_producto_costos(): void
    {
        $almacen = Almacen::create([
            'codigo' => 'CEDIS',
            'nombre' => 'CEDIS',
            'activo' => true,
        ]);

        $svc = app(ProcesarFilaInventarioImportacionService::class);
        $svc->ejecutar(
            [
                'sku' => 'SOLOPV',
                'descripcion' => 'Solo precio',
                'existencia' => '5',
                'precio_venta' => '88.5',
            ],
            [
                'sku' => 'sku',
                'descripcion' => 'descripcion',
                'existencia' => 'existencia',
                'precio_venta' => 'precio_venta',
            ],
            $almacen->id,
        );

        $producto = Producto::where('sku', 'SOLOPV')->first();
        $this->assertNotNull($producto);
        $this->assertSame(1, Producto::where('sku', 'SOLOPV')->count());

        $costo = ProductoCosto::where('producto_id', $producto->id)
            ->where('almacen_id', $almacen->id)
            ->first();
        $this->assertNotNull($costo);
        $this->assertSame('88.50', (string) $costo->precio_venta);
        $this->assertSame('0.00', (string) $costo->costo);
    }
}
