<?php

namespace Tests\Unit\Services\Contabilidad;

use App\Models\Contabilidad\ContabilidadConfiguracion;
use App\Services\Contabilidad\ProcesarListaPreciosContabilidadService;
use App\Services\WooCommerce\WooCommercePreciosService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcesarListaPreciosContabilidadTest extends TestCase
{
    use RefreshDatabase;

    private function crearCsv(): string
    {
        $path = sys_get_temp_dir().'/conta_test_'.uniqid().'.csv';
        file_put_contents(
            $path,
            "SKU,Descripcion,Plataformas,Bronce\n001,Producto A,100.00,80.00\n002,Producto B,200.00,150.00\n"
        );

        return $path;
    }

    public function test_usa_precios_bronce_cuando_mapeo_indica_bronce(): void
    {
        $path = $this->crearCsv();
        $service = app(ProcesarListaPreciosContabilidadService::class);

        $resultado = $service->ejecutar($path, [
            'sku' => 'SKU',
            'precio_base' => 'Bronce',
            'descripcion' => 'Descripcion',
        ]);

        $this->assertEquals('Producto A', $resultado['001']['nombre']);
        $this->assertEquals(80.0, $resultado['001']['precio']);
        $this->assertEquals(150.0, $resultado['002']['precio']);

        unlink($path);
    }

    public function test_usa_precios_plataformas_cuando_mapeo_indica_plataformas(): void
    {
        $path = $this->crearCsv();
        $service = app(ProcesarListaPreciosContabilidadService::class);

        $resultado = $service->ejecutar($path, [
            'sku' => 'SKU',
            'precio_base' => 'Plataformas',
            'descripcion' => 'Descripcion',
        ]);

        $this->assertEquals(100.0, $resultado['001']['precio']);
        $this->assertEquals(200.0, $resultado['002']['precio']);

        unlink($path);
    }

    public function test_sugerir_mapeo_prioriza_bronce(): void
    {
        $preciosService = app(WooCommercePreciosService::class);
        $headers = ['SKU', 'Descripcion', 'Plataformas', 'Bronce'];

        $mapeo = $preciosService->sugerirMapeoContabilidad($headers, [
            'sku' => 'SKU',
            'precio_base' => 'Bronce',
            'descripcion' => 'Descripcion',
        ]);

        $this->assertSame('SKU', $mapeo['sku']);
        $this->assertSame('Bronce', $mapeo['precio_base']);
        $this->assertSame('Descripcion', $mapeo['descripcion']);
    }

    public function test_configuracion_default_usa_bronce_como_precio_base(): void
    {
        $config = ContabilidadConfiguracion::obtener();

        $this->assertSame('Bronce', $config->mapeoPreciosEfectivo()['precio_base']);
    }

    public function test_falla_si_falta_mapeo_obligatorio(): void
    {
        $path = $this->crearCsv();
        $service = app(ProcesarListaPreciosContabilidadService::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Debes mapear SKU y columna de precio.');

        try {
            $service->ejecutar($path, ['sku' => 'SKU', 'precio_base' => '']);
        } finally {
            unlink($path);
        }
    }
}
