<?php

namespace Tests\Unit\Services\GeliaAi;

use App\Services\GeliaAi\InspeccionarArchivoGeliaAiService;
use PHPUnit\Framework\TestCase;

class InspeccionarArchivoGeliaAiServiceTest extends TestCase
{
    public function test_detecta_kind_costos_por_headers(): void
    {
        $svc = new InspeccionarArchivoGeliaAiService;
        $kind = $svc->detectarKind(['SKU', 'Costo', 'Precio_venta']);
        $this->assertSame('costos', $kind);
    }

    public function test_detecta_kind_existencias_por_headers(): void
    {
        $svc = new InspeccionarArchivoGeliaAiService;
        $kind = $svc->detectarKind(['sku', 'descripcion', 'existencia']);
        $this->assertSame('existencias', $kind);
    }

    public function test_detecta_kind_por_nombre_archivo(): void
    {
        $svc = new InspeccionarArchivoGeliaAiService;
        $this->assertSame('existencias', $svc->detectarKind([], 'Existencias_Wizerp.xlsx'));
        $this->assertSame('precios', $svc->detectarKind([], 'lista_precios.csv'));
        $this->assertSame('costos', $svc->detectarKind([], 'costos_cedis.xlsx'));
    }

    public function test_adivina_mapping_sku_costo(): void
    {
        $svc = new InspeccionarArchivoGeliaAiService;
        $map = $svc->adivinarMapping(['SKU', 'Descripcion', 'Costo']);
        $this->assertSame('SKU', $map['sku']);
        $this->assertSame('Costo', $map['costo']);
        $this->assertSame('Descripcion', $map['descripcion']);
    }
}
