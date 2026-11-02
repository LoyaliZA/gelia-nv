<?php

namespace Tests\Unit\Solicitudes;

use App\Models\CatalogoProceso;
use App\Models\SolicitudTag;
use App\Services\Solicitudes\ValidarListaInferiorService;
use PHPUnit\Framework\TestCase;

class ExigeListaRebajaTest extends TestCase
{
    public function test_no_exige_rebaja_si_el_folio_no_cambio_la_lista(): void
    {
        $solicitud = new SolicitudTag(['catalogo_lista_descuento_id' => null]);
        $solicitud->setRelation('proceso', new CatalogoProceso([
            'nombre' => 'ASIGNAR TAG Y CAMBIO DE LISTA',
        ]));

        $this->assertFalse((new ValidarListaInferiorService())->exigeListaRebaja($solicitud));
    }

    public function test_exige_rebaja_cuando_el_folio_pidio_otra_lista(): void
    {
        $solicitud = new SolicitudTag(['catalogo_lista_descuento_id' => 3]);
        $solicitud->setRelation('proceso', new CatalogoProceso([
            'nombre' => 'ASIGNAR TAG Y CAMBIO DE LISTA',
        ]));

        $this->assertTrue((new ValidarListaInferiorService())->exigeListaRebaja($solicitud));
    }
}
