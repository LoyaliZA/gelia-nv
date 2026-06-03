<?php

namespace Tests\Feature\Clientes;

use App\Models\CatalogoListaDescuento;
use App\Models\Cliente;
use App\Services\Clientes\ImportarClientesWizerpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ImportarClientesInactivosTest extends TestCase
{
    use RefreshDatabase;

    private function sincronizarListas(): void
    {
        $listas = [
            ['nombre' => 'MAYOREO DIAMANTE', 'monto_requerido' => 85108.00],
            ['nombre' => 'MAYOREO ORO', 'monto_requerido' => 31250.00],
            ['nombre' => 'MAYOREO PLATA', 'monto_requerido' => 5104.00],
            ['nombre' => 'MAYOREO BRONCE', 'monto_requerido' => 0.01],
            ['nombre' => 'PUBLICO GENERAL', 'monto_requerido' => 0.00],
            ['nombre' => 'PLATAFORMAS', 'monto_requerido' => 999999.00],
            ['nombre' => 'COLABORADORES', 'monto_requerido' => 999999.00],
        ];

        foreach ($listas as $lista) {
            CatalogoListaDescuento::create([
                'nombre' => $lista['nombre'],
                'monto_requerido' => $lista['monto_requerido'],
                'activo' => true,
            ]);
        }
    }

    private function idLista(string $nombre): int
    {
        return CatalogoListaDescuento::where('nombre', $nombre)->value('id');
    }

    private function importarCsv(string $contenido): array
    {
        $path = storage_path('app/test-import-clientes.csv');
        file_put_contents($path, $contenido);

        $archivo = new UploadedFile($path, 'test.csv', 'text/csv', null, true);

        return app(ImportarClientesWizerpService::class)->ejecutar($archivo);
    }

    public function test_codigo_lista_vacio_marca_inactivo_y_lista_pg(): void
    {
        $this->sincronizarListas();
        $listaPlata = $this->idLista('MAYOREO PLATA');

        Cliente::create([
            'numero_cliente' => '100',
            'nombre' => 'Cliente Test',
            'lista_actual_id' => $listaPlata,
            'monto_venta_actual' => 0,
            'es_inactivo' => false,
        ]);

        $resultado = $this->importarCsv("numero_cliente,nombre,codigo_lista,monto_venta_actual\n100,Cliente Test,,0\n");

        $cliente = Cliente::where('numero_cliente', '100')->first();

        $this->assertTrue($cliente->es_inactivo);
        $this->assertEquals($this->idLista('PUBLICO GENERAL'), $cliente->lista_actual_id);
        $this->assertEquals(1, $resultado['clientes_marcados_inactivos']);
    }

    public function test_sin_columna_codigo_lista_solo_actualiza_monto(): void
    {
        $this->sincronizarListas();
        $listaPlata = $this->idLista('MAYOREO PLATA');

        Cliente::create([
            'numero_cliente' => '200',
            'nombre' => 'Cliente Monto',
            'lista_actual_id' => $listaPlata,
            'monto_venta_actual' => 5000,
            'es_inactivo' => false,
        ]);

        $this->importarCsv("numero_cliente,nombre,monto_venta_actual\n200,Cliente Monto,100\n");

        $cliente = Cliente::where('numero_cliente', '200')->first();

        $this->assertEquals(100.0, (float) $cliente->monto_venta_actual);
        $this->assertEquals($listaPlata, $cliente->lista_actual_id);
        $this->assertFalse($cliente->es_inactivo);
    }

    public function test_monto_baja_sin_columna_lista_no_degrada(): void
    {
        $this->sincronizarListas();
        $listaPlata = $this->idLista('MAYOREO PLATA');

        Cliente::create([
            'numero_cliente' => '300',
            'nombre' => 'Cliente Baja',
            'lista_actual_id' => $listaPlata,
            'monto_venta_actual' => 8000,
            'es_inactivo' => false,
        ]);

        $this->importarCsv("numero_cliente,nombre,monto_venta_actual\n300,Cliente Baja,0\n");

        $cliente = Cliente::where('numero_cliente', '300')->first();

        $this->assertEquals(0.0, (float) $cliente->monto_venta_actual);
        $this->assertEquals($listaPlata, $cliente->lista_actual_id);
    }

    public function test_monto_baja_con_columna_lista_aplica_codigo(): void
    {
        $this->sincronizarListas();
        $listaOro = $this->idLista('MAYOREO ORO');
        $listaPlata = $this->idLista('MAYOREO PLATA');

        Cliente::create([
            'numero_cliente' => '400',
            'nombre' => 'Cliente CSV',
            'lista_actual_id' => $listaOro,
            'monto_venta_actual' => 8000,
            'es_inactivo' => false,
        ]);

        $this->importarCsv("numero_cliente,nombre,codigo_lista,monto_venta_actual\n400,Cliente CSV,3,0\n");

        $cliente = Cliente::where('numero_cliente', '400')->first();

        $this->assertEquals($listaPlata, $cliente->lista_actual_id);
        $this->assertFalse($cliente->es_inactivo);
    }

    public function test_codigo_pg_marca_activo(): void
    {
        $this->sincronizarListas();

        Cliente::create([
            'numero_cliente' => '500',
            'nombre' => 'Cliente PG',
            'lista_actual_id' => $this->idLista('MAYOREO PLATA'),
            'monto_venta_actual' => 0,
            'es_inactivo' => true,
        ]);

        $this->importarCsv("numero_cliente,nombre,codigo_lista,monto_venta_actual\n500,Cliente PG,pg,0\n");

        $cliente = Cliente::where('numero_cliente', '500')->first();

        $this->assertFalse($cliente->es_inactivo);
        $this->assertEquals($this->idLista('PUBLICO GENERAL'), $cliente->lista_actual_id);
    }
}
