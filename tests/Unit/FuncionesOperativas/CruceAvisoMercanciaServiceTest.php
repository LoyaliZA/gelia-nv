<?php

namespace Tests\Unit\FuncionesOperativas;

use App\Services\FuncionesOperativas\CruceAvisoMercanciaService;
use Tests\TestCase;

class CruceAvisoMercanciaServiceTest extends TestCase
{
    public function test_carga_upcs_del_aviso_con_columna_fecha_sin_nombre(): void
    {
        $ruta = base_path('Implementaciones/remapeo/AVISO A CLIENTES DE MERCANCIA - AVISO DE MERCANCIA (1).csv');
        $this->assertFileExists($ruta);

        $avisos = (new CruceAvisoMercanciaService())->cargarAvisos($ruta);

        $this->assertGreaterThan(200, count($avisos));
        $this->assertArrayHasKey('766124253509', $avisos);
        $this->assertSame('FANNY', $avisos['766124253509']['vendedor']);
        $this->assertStringContainsString('ADRIANA', $avisos['766124253509']['cliente']);
    }

    public function test_cruza_oc_con_upc_del_aviso(): void
    {
        $servicio = new CruceAvisoMercanciaService();
        $rutaAviso = base_path('Implementaciones/remapeo/AVISO A CLIENTES DE MERCANCIA - AVISO DE MERCANCIA (1).csv');

        $rutaCompra = sys_get_temp_dir().'/compra_aviso_test_'.uniqid().'.csv';
        file_put_contents($rutaCompra, implode("\n", [
            'Compra',
            'SKU,Descripción,Almacén,Sucursal,Costo,Recibido,Cantidad',
            '766124253509,D MONT B. PRESENCE 75ML. EDT,VTA CEDIS,Matriz,100.00,3.000,3.000',
            '9999999999999,PRODUCTO SIN AVISO,VTA CEDIS,Matriz,10.00,1.000,1.000',
        ])."\n");

        try {
            $out = $servicio->cruzar($rutaAviso, $rutaCompra);
            $this->assertCount(1, $out['resultados']);
            $this->assertSame('766124253509', $out['resultados'][0]['SKU']);
            $this->assertSame(3, $out['resultados'][0]['Piezas Recibidas']);
            $this->assertSame('FANNY', $out['resultados'][0]['Vendedor Asignado']);
        } finally {
            @unlink($rutaCompra);
        }
    }

    public function test_normaliza_codigo_en_notacion_cientifica(): void
    {
        $servicio = new CruceAvisoMercanciaService();
        $this->assertSame('3349668577521', $servicio->normalizarCodigo('3.349668577521E+12'));
        $this->assertSame('766124253509', $servicio->normalizarCodigo(766124253509.0));
    }
}
