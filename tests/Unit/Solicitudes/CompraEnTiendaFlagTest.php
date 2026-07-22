<?php

namespace Tests\Unit\Solicitudes;

use App\Models\CatalogoProceso;
use App\Services\Solicitudes\CrearSolicitudService;
use PHPUnit\Framework\TestCase;

class CompraEnTiendaFlagTest extends TestCase
{
    public function test_flag_aplica_en_cualquier_proceso_financiero(): void
    {
        $proceso = new CatalogoProceso([
            'nombre' => 'ASIGNAR TAG',
            'categoria_flujo' => CatalogoProceso::CATEGORIA_FINANCIERO,
        ]);

        $this->assertTrue(CrearSolicitudService::flagCompraEnTiendaAplica($proceso, true));
        $this->assertFalse(CrearSolicitudService::flagCompraEnTiendaAplica($proceso, false));
    }

    public function test_flag_no_aplica_en_proceso_operativo(): void
    {
        $proceso = new CatalogoProceso([
            'nombre' => 'CANCELACIÓN DE REMISIÓN',
            'categoria_flujo' => CatalogoProceso::CATEGORIA_OPERATIVO,
        ]);

        $this->assertFalse(CrearSolicitudService::flagCompraEnTiendaAplica($proceso, true));
    }

    public function test_modos_sin_monto_no_alteran_venta(): void
    {
        $this->assertTrue(CrearSolicitudService::modoValidacionSinMonto('pago_sin_monto'));
        $this->assertTrue(CrearSolicitudService::modoValidacionSinMonto('atencion_gelia'));
        $this->assertFalse(CrearSolicitudService::modoValidacionSinMonto('pago'));
    }

    public function test_flujo_tienda_incluye_solo_tag(): void
    {
        $this->assertTrue(CrearSolicitudService::esFlujoTienda([
            'compra_en_tienda' => false,
            'compra_en_tienda_solo_tag' => true,
        ]));
        $this->assertTrue(CrearSolicitudService::esFlujoTienda([
            'compra_en_tienda' => true,
            'compra_en_tienda_solo_tag' => false,
        ]));
        $this->assertFalse(CrearSolicitudService::esFlujoTienda([
            'compra_en_tienda' => false,
            'compra_en_tienda_solo_tag' => false,
        ]));
    }

    public function test_solo_tag_solo_en_asignar_tag(): void
    {
        $tag = new CatalogoProceso([
            'nombre' => 'ASIGNAR TAG',
            'categoria_flujo' => CatalogoProceso::CATEGORIA_FINANCIERO,
        ]);
        $tagYLista = new CatalogoProceso([
            'nombre' => 'ASIGNAR TAG Y CAMBIO DE LISTA',
            'categoria_flujo' => CatalogoProceso::CATEGORIA_FINANCIERO,
        ]);

        $this->assertTrue(CrearSolicitudService::esProcesoAsignarTagSolo($tag));
        $this->assertFalse(CrearSolicitudService::esProcesoAsignarTagSolo($tagYLista));
    }
}
