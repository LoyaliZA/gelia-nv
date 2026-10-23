<?php

namespace Tests\Unit\Services\Almacenes;

use App\Models\Almacen;
use App\Models\Inventario;
use App\Models\Producto;
use App\Models\ProductoAlmacen;
use App\Models\ProductoCosto;
use App\Services\Almacenes\ProcesarFilaAsignacionAlmacenImportacionService;
use App\Services\Almacenes\ProcesarFilaCantidadReferenciaImportacionService;
use App\Services\Almacenes\ProcesarFilaCostoImportacionService;
use App\Services\Almacenes\ProcesarFilaImportacionHubService;
use App\Services\Almacenes\ProcesarFilaProductoImportacionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ImportacionPorFolioTest extends TestCase
{
    use RefreshDatabase;

    public function test_costo_con_folio_desconocido_no_crea_producto(): void
    {
        $almacen = $this->almacen();
        $svc = app(ProcesarFilaCostoImportacionService::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('no existe');

        $svc->ejecutar(
            ['folio' => '770001', 'sku' => 'NO-USAR', 'costo' => '10'],
            ['folio' => 'folio', 'sku' => 'sku', 'costo' => 'costo'],
            $almacen->id,
        );

        $this->assertSame(0, Producto::query()->count());
    }

    public function test_existencia_con_folio_desconocido_no_crea_producto(): void
    {
        $almacen = $this->almacen();
        $svc = app(ProcesarFilaCantidadReferenciaImportacionService::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('no existe');

        $svc->ejecutar(
            ['folio' => '770002', 'existencia' => '4'],
            ['folio' => 'folio', 'existencia' => 'existencia'],
            $almacen->id,
        );
    }

    public function test_asignacion_usa_folio_aunque_el_sku_del_archivo_sea_otro(): void
    {
        $almacen = $this->almacen();
        $producto = $this->producto(610010, 'SKU-REAL');
        $this->producto(610011, 'SKU-AJENO');

        $svc = app(ProcesarFilaAsignacionAlmacenImportacionService::class);
        $resultado = $svc->ejecutar(
            ['folio' => '610010', 'sku' => 'SKU-AJENO', 'ubicacion' => 'B-2'],
            ['folio' => 'folio', 'sku' => 'sku', 'ubicacion' => 'ubicacion'],
            $almacen->id,
        );

        $this->assertSame('importado', $resultado['accion']);
        $this->assertDatabaseHas('producto_almacen', [
            'producto_id' => $producto->id,
            'almacen_id' => $almacen->id,
            'ubicacion' => 'B-2',
        ]);
        $this->assertSame('SKU-REAL', $producto->fresh()->sku);
    }

    public function test_caso_productos_vincula_el_alta_al_almacen(): void
    {
        $almacen = $this->almacen();
        $svc = app(ProcesarFilaImportacionHubService::class);

        $resultado = $svc->ejecutar(
            ['folio' => '', 'sku' => 'ALTA-1', 'descripcion' => 'Nuevo en almacén', 'ubicacion' => 'C-1'],
            ['folio' => 'folio', 'sku' => 'sku', 'descripcion' => 'descripcion', 'ubicacion' => 'ubicacion'],
            $almacen->id,
            ['ficha_producto', 'asignacion_almacen'],
        );

        $this->assertSame(1, $resultado['productos_creados']);
        $this->assertSame(1, $resultado['asignaciones_creadas']);
        $producto = Producto::query()->where('sku', 'ALTA-1')->first();
        $this->assertNotNull($producto);
        $this->assertGreaterThanOrEqual(100001, (int) $producto->folio);
        $this->assertTrue(ProductoAlmacen::query()
            ->where('producto_id', $producto->id)
            ->where('almacen_id', $almacen->id)
            ->where('ubicacion', 'C-1')
            ->exists());
    }

    public function test_celda_vacia_no_borra_descripcion(): void
    {
        $producto = $this->producto(610020, 'SKU-DESC');
        $svc = app(ProcesarFilaProductoImportacionService::class);

        $resultado = $svc->ejecutar(
            ['folio' => '610020', 'sku' => '', 'descripcion' => ''],
            ['folio' => 'folio', 'sku' => 'sku', 'descripcion' => 'descripcion'],
        );

        $this->assertSame('sin_cambios', $resultado['accion']);
        $this->assertSame('Producto 610020', $producto->fresh()->descripcion);
        $this->assertSame('SKU-DESC', $producto->fresh()->sku);
    }

    public function test_existencias_solo_actualiza_la_existencia(): void
    {
        $almacen = $this->almacen();
        $producto = $this->producto(610030, 'SKU-EX');
        $inventario = Inventario::create([
            'producto_id' => $producto->id,
            'almacen_id' => $almacen->id,
            'existencia' => 4,
            'apartado' => 1,
            'ubicacion' => 'ANAQUEL',
        ]);
        ProductoCosto::create([
            'producto_id' => $producto->id,
            'almacen_id' => $almacen->id,
            'costo' => 8,
            'precio_venta' => 12,
        ]);

        $resultado = app(ProcesarFilaCantidadReferenciaImportacionService::class)->ejecutar(
            ['folio' => '610030', 'existencia' => '9'],
            ['folio' => 'folio', 'existencia' => 'existencia'],
            $almacen->id,
        );

        $this->assertSame('actualizado', $resultado['accion']);
        $inventario->refresh();
        $producto->refresh();
        $this->assertSame('9.000', $inventario->existencia);
        $this->assertSame('1.000', $inventario->apartado);
        $this->assertSame('ANAQUEL', $inventario->ubicacion);
        $this->assertSame('SKU-EX', $producto->sku);
        $this->assertSame('Producto 610030', $producto->descripcion);
        $this->assertDatabaseHas('producto_costos', [
            'producto_id' => $producto->id,
            'almacen_id' => $almacen->id,
            'costo' => '8.00',
            'precio_venta' => '12.00',
        ]);
    }

    private function almacen(): Almacen
    {
        return Almacen::create([
            'codigo' => 'CEDIS',
            'nombre' => 'CEDIS',
            'activo' => true,
        ]);
    }

    private function producto(int $folio, string $sku): Producto
    {
        return Producto::create([
            'uuid' => (string) Str::uuid(),
            'folio' => $folio,
            'sku' => $sku,
            'descripcion' => 'Producto '.$folio,
            'activo' => true,
        ]);
    }
}
