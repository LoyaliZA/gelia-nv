<?php

namespace Tests\Unit\Solicitudes;

use App\Support\Solicitudes\BusquedaSolicitudTag;
use Tests\TestCase;

class BusquedaSolicitudTagTest extends TestCase
{
    public function test_normaliza_campo_y_folio_con_prefijo(): void
    {
        $this->assertSame('folio', BusquedaSolicitudTag::normalizar('FOLIO'));
        $this->assertSame('general', BusquedaSolicitudTag::normalizar('desconocido'));
        $this->assertSame(12, BusquedaSolicitudTag::idDesdeFolio('FOL-12'));
        $this->assertSame(12, BusquedaSolicitudTag::idDesdeFolio('fol 12'));
        $this->assertSame(12, BusquedaSolicitudTag::idDesdeFolio('12'));
        $this->assertNull(BusquedaSolicitudTag::idDesdeFolio('Hidalgo'));
    }
}
