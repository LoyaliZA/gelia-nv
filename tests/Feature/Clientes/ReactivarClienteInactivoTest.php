<?php

namespace Tests\Feature\Clientes;

use App\Models\CatalogoListaDescuento;
use App\Models\Cliente;
use App\Services\Clientes\ImportarClientesWizerpService;
use App\Services\Clientes\ReactivarClienteInactivoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ReactivarClienteInactivoTest extends TestCase
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

    public function test_servicio_reactiva_cliente_inactivo_con_monto_positivo(): void
    {
        $this->sincronizarListas();
        $listaPg = $this->idLista('PUBLICO GENERAL');
        $listaPlata = $this->idLista('MAYOREO PLATA');

        $cliente = Cliente::create([
            'numero_cliente' => '701',
            'nombre' => 'Inactivo Comprador',
            'lista_actual_id' => $listaPg,
            'monto_venta_actual' => 0,
            'es_inactivo' => true,
        ]);

        $reactivado = app(ReactivarClienteInactivoService::class)->ejecutar($cliente, 8000.0);
        $cliente->save();

        $this->assertTrue($reactivado);
        $this->assertFalse($cliente->fresh()->es_inactivo);
        $this->assertEquals($listaPlata, $cliente->fresh()->lista_actual_id);
    }

    public function test_servicio_no_reactiva_si_monto_es_cero(): void
    {
        $this->sincronizarListas();
        $listaPg = $this->idLista('PUBLICO GENERAL');

        $cliente = Cliente::create([
            'numero_cliente' => '702',
            'nombre' => 'Inactivo Sin Compras',
            'lista_actual_id' => $listaPg,
            'monto_venta_actual' => 0,
            'es_inactivo' => true,
        ]);

        $reactivado = app(ReactivarClienteInactivoService::class)->ejecutar($cliente, 0.0);

        $this->assertFalse($reactivado);
        $this->assertTrue($cliente->fresh()->es_inactivo);
    }

    public function test_importacion_solo_monto_reactiva_cliente_inactivo(): void
    {
        $this->sincronizarListas();
        $listaPg = $this->idLista('PUBLICO GENERAL');
        $listaPlata = $this->idLista('MAYOREO PLATA');

        Cliente::create([
            'numero_cliente' => '800',
            'nombre' => 'Inactivo Wizerp',
            'lista_actual_id' => $listaPg,
            'monto_venta_actual' => 0,
            'es_inactivo' => true,
        ]);

        $path = storage_path('app/test-reactivar-inactivo.csv');
        file_put_contents($path, "numero_cliente,nombre,monto_venta_actual\n800,Inactivo Wizerp,6000\n");
        $archivo = new UploadedFile($path, 'test.csv', 'text/csv', null, true);

        app(ImportarClientesWizerpService::class)->ejecutar($archivo);

        $cliente = Cliente::where('numero_cliente', '800')->first();

        $this->assertFalse($cliente->es_inactivo);
        $this->assertEquals(6000.0, (float) $cliente->monto_venta_actual);
        $this->assertEquals($listaPlata, $cliente->lista_actual_id);
    }
}
