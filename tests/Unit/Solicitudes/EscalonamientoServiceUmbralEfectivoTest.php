<?php

namespace Tests\Unit\Solicitudes;

use App\Models\CatalogoListaDescuento;
use App\Services\Solicitudes\EscalonamientoService;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class EscalonamientoServiceUmbralEfectivoTest extends TestCase
{
    private EscalonamientoService $svc;

    private Collection $listas;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new EscalonamientoService();
        $this->listas = $this->catalogoMayoreo();
    }

    private function catalogoMayoreo(): Collection
    {
        return collect([
            new CatalogoListaDescuento([
                'id' => 5,
                'nombre' => 'MAYOREO DIAMANTE',
                'monto_requerido' => 80001.00,
                'porcentaje_descuento' => 6.00,
                'activo' => true,
            ]),
            new CatalogoListaDescuento([
                'id' => 4,
                'nombre' => 'MAYOREO ORO',
                'monto_requerido' => 30001.00,
                'porcentaje_descuento' => 4.00,
                'activo' => true,
            ]),
            new CatalogoListaDescuento([
                'id' => 3,
                'nombre' => 'MAYOREO PLATA',
                'monto_requerido' => 5001.00,
                'porcentaje_descuento' => 2.00,
                'activo' => true,
            ]),
            new CatalogoListaDescuento([
                'id' => 2,
                'nombre' => 'MAYOREO BRONCE',
                'monto_requerido' => 0.01,
                'porcentaje_descuento' => 0.00,
                'activo' => true,
            ]),
            new CatalogoListaDescuento([
                'id' => 1,
                'nombre' => 'PUBLICO GENERAL',
                'monto_requerido' => 0.00,
                'porcentaje_descuento' => 0.00,
                'activo' => true,
            ]),
        ]);
    }

    public function test_umbral_efectivo_plata_con_2_por_ciento(): void
    {
        $plata = $this->listas->firstWhere('nombre', 'MAYOREO PLATA');
        $this->assertEquals(5103.06, $this->svc->umbralEfectivo($plata));
    }

    public function test_umbral_efectivo_cambia_con_porcentaje(): void
    {
        $plata = new CatalogoListaDescuento([
            'nombre' => 'MAYOREO PLATA',
            'monto_requerido' => 5001.00,
            'porcentaje_descuento' => 3.00,
        ]);

        $this->assertEquals(5155.67, $this->svc->umbralEfectivo($plata));
    }

    public function test_resolver_5098_queda_en_bronce_no_plata(): void
    {
        $lista = $this->svc->resolverListaPorMonto(5098.0, $this->listas);
        $this->assertSame('MAYOREO BRONCE', $lista->nombre);
    }

    public function test_resolver_5107_alcanza_plata(): void
    {
        $lista = $this->svc->resolverListaPorMonto(5107.0, $this->listas);
        $this->assertSame('MAYOREO PLATA', $lista->nombre);
    }

    public function test_evaluar_cotizacion_5098_casi_alcanza_sin_ascenso_plata(): void
    {
        $resultado = $this->svc->evaluar(0.0, 5098.0, null, $this->listas, 0.0);

        $this->assertSame('MAYOREO BRONCE', $resultado['lista_calificada_efectiva']->nombre);
        $this->assertSame('MAYOREO BRONCE', $resultado['lista_anticipada']->nombre);
        $this->assertTrue($resultado['casi_alcanza_siguiente']);
        $this->assertSame('MAYOREO PLATA', $resultado['lista_casi_alcanzada']->nombre);
        $this->assertEquals(5.06, $resultado['faltante_bruto_casi']);
        $this->assertTrue($resultado['es_ascenso']);
        $this->assertEquals(2, $resultado['lista_solicitada_id_efectivo']);
    }

    public function test_evaluar_cotizacion_5107_plata_estable(): void
    {
        $resultado = $this->svc->evaluar(0.0, 5107.0, null, $this->listas, 0.0);

        $this->assertSame('MAYOREO PLATA', $resultado['lista_calificada_efectiva']->nombre);
        $this->assertFalse($resultado['casi_alcanza_siguiente']);
        $this->assertTrue($resultado['es_ascenso']);
        $this->assertEquals(2.0, $resultado['porcentaje_descuento']);
        $this->assertEquals(5004.86, $resultado['monto_final_tentativo']);
    }
}
