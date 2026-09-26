<?php

namespace Tests\Unit\Services\Almacenes;

use App\Models\Producto;
use App\Services\Almacenes\ProcesarFilaProductoImportacionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcesarFilaProductoImportacionServiceTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, string> */
    private function mappingBase(): array
    {
        return ['sku' => 'sku', 'folio' => 'folio', 'descripcion' => 'descripcion'];
    }

    public function test_actualizar_solo_descripcion_conserva_activo(): void
    {
        $producto = Producto::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'folio' => 700001,
            'sku' => 'INACT1',
            'descripcion' => 'ANTES',
            'activo' => false,
        ]);

        $svc = app(ProcesarFilaProductoImportacionService::class);
        $svc->ejecutar(
            ['sku' => 'INACT1', 'folio' => '700001', 'descripcion' => 'DESPUES'],
            $this->mappingBase(),
        );

        $producto->refresh();
        $this->assertSame('DESPUES', $producto->descripcion);
        $this->assertFalse($producto->activo);
        $this->assertSame(700001, (int) $producto->folio);
    }

    public function test_fila_sin_folio_falla(): void
    {
        $svc = app(ProcesarFilaProductoImportacionService::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Folio obligatorio');

        $svc->ejecutar(
            ['sku' => 'X1', 'folio' => '', 'descripcion' => 'DESC'],
            $this->mappingBase(),
        );
    }

    public function test_resuelve_producto_existente_por_folio_y_actualiza_sku(): void
    {
        $producto = Producto::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'folio' => 500100,
            'sku' => 'SKU-INTERNO',
            'descripcion' => 'PRODUCTO POR FOLIO',
            'activo' => true,
        ]);

        $svc = app(ProcesarFilaProductoImportacionService::class);
        $resultado = $svc->ejecutar(
            ['codigo_barras' => '85715166708', 'folio' => '500100', 'descripcion' => 'ACTUALIZADO'],
            ['sku' => 'codigo_barras', 'folio' => 'folio', 'descripcion' => 'descripcion'],
        );

        $this->assertSame('actualizado', $resultado['accion']);
        $producto->refresh();
        $this->assertSame('ACTUALIZADO', $producto->descripcion);
        $this->assertSame('85715166708', $producto->sku);
    }
}
