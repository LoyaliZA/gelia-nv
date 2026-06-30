<?php

namespace Tests\Unit\Productos;

use App\Services\Productos\GenerarFolioProductoService;
use App\Services\Productos\ImportarProductosService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ImportarProductosServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_importa_y_actualiza_por_sku(): void
    {
        $csv = "sku,descripcion\n00099,Perfume Test\n";
        $archivo = UploadedFile::fake()->createWithContent('productos.csv', $csv);

        $service = new ImportarProductosService(new GenerarFolioProductoService());
        $resultado = $service->ejecutar($archivo);

        $this->assertSame(1, $resultado['creados']);
        $this->assertSame(0, $resultado['actualizados']);
        $this->assertDatabaseHas('productos', ['sku' => '99', 'descripcion' => 'Perfume Test']);

        $csv2 = "sku,descripcion\n00099,Perfume Actualizado\n";
        $archivo2 = UploadedFile::fake()->createWithContent('productos2.csv', $csv2);
        $resultado2 = $service->ejecutar($archivo2);

        $this->assertSame(0, $resultado2['creados']);
        $this->assertSame(1, $resultado2['actualizados']);
        $this->assertDatabaseHas('productos', ['sku' => '99', 'descripcion' => 'Perfume Actualizado']);
    }
}
