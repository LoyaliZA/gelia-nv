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

    public function test_resolver_bruto_5098_queda_en_bronce_no_plata(): void
    {
        $lista = $this->svc->resolverListaPorMontoBrutoAcumulado(5098.0, $this->listas);
        $this->assertSame('MAYOREO BRONCE', $lista->nombre);
    }

    public function test_resolver_bruto_5107_alcanza_plata(): void
    {
        $lista = $this->svc->resolverListaPorMontoBrutoAcumulado(5107.0, $this->listas);
        $this->assertSame('MAYOREO PLATA', $lista->nombre);
    }

    public function test_resolver_neto_5100_18_califica_plata_fol_1476(): void
    {
        $lista = $this->svc->resolverListaPorAcumuladoNeto(5100.18, $this->listas);
        $this->assertSame('MAYOREO PLATA', $lista->nombre);
    }

    public function test_resolver_neto_5000_99_no_califica_plata(): void
    {
        $lista = $this->svc->resolverListaPorAcumuladoNeto(5000.99, $this->listas);
        $this->assertSame('MAYOREO BRONCE', $lista->nombre);
    }

    public function test_resolver_neto_5001_califica_plata(): void
    {
        $lista = $this->svc->resolverListaPorAcumuladoNeto(5001.0, $this->listas);
        $this->assertSame('MAYOREO PLATA', $lista->nombre);
    }

    public function test_evaluar_cotizacion_5098_casi_alcanza_sin_confirmar_plata(): void
    {
        $resultado = $this->svc->evaluar(0.0, 5098.0, null, $this->listas, 0.0);

        $this->assertSame('MAYOREO PLATA', $resultado['lista_provisional']->nombre);
        $this->assertSame('MAYOREO BRONCE', $resultado['lista_calificada_efectiva']->nombre);
        $this->assertTrue($resultado['casi_alcanza_siguiente']);
        $this->assertSame('MAYOREO PLATA', $resultado['lista_casi_alcanzada']->nombre);
        $this->assertEquals(4.96, $resultado['faltante_neto_casi']);
        $this->assertTrue($resultado['es_ascenso']);
        $this->assertSame('MAYOREO PLATA', $resultado['lista_provisional']->nombre);
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

    public function test_cliente_oro_hacia_diamante_aplica_solo_2_por_ciento_adicional(): void
    {
        $resultado = $this->svc->evaluar(76836.61, 15101.62, null, $this->listas, 30001.0);

        $this->assertSame('MAYOREO DIAMANTE', $resultado['lista_anticipada']->nombre);
        $this->assertEquals(2.0, $resultado['porcentaje_descuento']);
        $this->assertEquals(14799.59, $resultado['monto_final_tentativo']);
    }

    public function test_descuento_adicional_toma_los_porcentajes_del_catalogo(): void
    {
        $listas = collect([
            new CatalogoListaDescuento([
                'id' => 5,
                'nombre' => 'MAYOREO DIAMANTE',
                'monto_requerido' => 80001.00,
                'porcentaje_descuento' => 9.00,
                'activo' => true,
            ]),
            new CatalogoListaDescuento([
                'id' => 4,
                'nombre' => 'MAYOREO ORO',
                'monto_requerido' => 30001.00,
                'porcentaje_descuento' => 5.00,
                'activo' => true,
            ]),
            new CatalogoListaDescuento([
                'id' => 3,
                'nombre' => 'MAYOREO PLATA',
                'monto_requerido' => 5001.00,
                'porcentaje_descuento' => 1.50,
                'activo' => true,
            ]),
        ]);

        $oro = $listas->firstWhere('nombre', 'MAYOREO ORO');
        $resultado = $this->svc->evaluar(90000.0, 1000.0, null, $listas, 30001.0, $oro);

        $this->assertSame('MAYOREO DIAMANTE', $resultado['lista_anticipada']->nombre);
        $this->assertEquals(4.0, $resultado['porcentaje_descuento']);
        $this->assertEquals(960.0, $resultado['monto_final_tentativo']);
    }

    public function test_cliente_plata_cotizacion_30620_alcanza_oro_con_2_por_ciento_adicional(): void
    {
        $plata = $this->listas->firstWhere('nombre', 'MAYOREO PLATA');
        $resultado = $this->svc->evaluar(0.0, 30620.0, null, $this->listas, 5001.0, $plata);

        $this->assertSame('MAYOREO ORO', $resultado['lista_anticipada']->nombre);
        $this->assertSame('MAYOREO ORO', $resultado['lista_calificada_efectiva']->nombre);
        $this->assertEquals(2.0, $resultado['porcentaje_descuento']);
        $this->assertEquals(30007.60, $resultado['monto_final_tentativo']);
        $this->assertEquals(30007.60, $resultado['total_proyectado_neto']);
        $this->assertTrue($resultado['mantiene_lista_anticipada']);
        $this->assertFalse($resultado['casi_alcanza_siguiente']);
        $this->assertTrue($resultado['es_ascenso']);

        $oro = collect($resultado['desglose_listas'])->firstWhere('nombre', 'MAYOREO ORO');
        $this->assertEquals(30001.0, $oro['monto_requerido']);
        $this->assertTrue($oro['cubre']);

        $demo = new CatalogoListaDescuento([
            'id' => 9,
            'nombre' => 'Lista demostración',
            'monto_requerido' => 0.00,
            'porcentaje_descuento' => 0.00,
            'activo' => true,
            'visible_en_solicitudes' => false,
        ]);
        $conDemo = $this->listas->push($demo);
        $filtradas = $this->svc->filtrarListasValidas($conDemo);

        $this->assertNull($filtradas->firstWhere('nombre', 'Lista demostración'));
        $this->assertNotNull($filtradas->firstWhere('nombre', 'MAYOREO ORO'));
    }

    public function test_historico_pagado_4094_cotizacion_1007_50_califica_plata(): void
    {
        $bronce = $this->listas->firstWhere('nombre', 'MAYOREO BRONCE');
        $resultado = $this->svc->evaluar(4094.0, 1007.5, null, $this->listas, 0.01, $bronce);

        $this->assertSame('MAYOREO PLATA', $resultado['lista_anticipada']->nombre);
        $this->assertSame('MAYOREO PLATA', $resultado['lista_calificada_efectiva']->nombre);
        $this->assertSame('MAYOREO PLATA', $resultado['lista_calificada_neto']->nombre);
        $this->assertTrue($resultado['mantiene_lista_anticipada']);
        $this->assertFalse($resultado['casi_alcanza_siguiente']);
        $this->assertEquals(5081.35, $resultado['total_proyectado_neto']);

        $plata = collect($resultado['desglose_listas'])->firstWhere('nombre', 'MAYOREO PLATA');
        $this->assertTrue($plata['cubre']);
        $this->assertEquals(5103.06, $plata['umbral_efectivo']);
    }

    public function test_evaluar_cotizacion_5204_13_provisional_plata_neto_estable(): void
    {
        $resultado = $this->svc->evaluar(0.0, 5204.13, null, $this->listas, 0.0);

        $this->assertSame('MAYOREO PLATA', $resultado['lista_provisional']->nombre);
        $this->assertSame('MAYOREO PLATA', $resultado['lista_confirmacion_estimada']->nombre);
        $this->assertTrue($resultado['mantiene_lista_anticipada']);
        $this->assertEquals(5100.05, $resultado['monto_final_tentativo']);
    }
}
