<?php

namespace Tests\Unit\Services\GeliaAi;

use App\Services\GeliaAi\GenerarListadoDesdeRutasService;
use App\Services\Listados\PorcentajesListadoService;
use Tests\TestCase;

class GenerarListadoTipoNormalizacionTest extends TestCase
{
    public function test_normaliza_meli_mayusculas_y_aliases(): void
    {
        $porcentajes = new class extends PorcentajesListadoService
        {
            public function __construct() {}
        };
        $svc = new GenerarListadoDesdeRutasService($porcentajes);

        $this->assertSame('meli', $svc->normalizarTipoLista('MELI'));
        $this->assertSame('meli', $svc->normalizarTipoLista('Lista MELI'));
        $this->assertSame('meli', $svc->normalizarTipoLista('mercado_libre'));
        $this->assertSame('resurtido', $svc->normalizarTipoLista('RESURTIDO'));
        $this->assertSame(12, $svc->normalizarTipoLista('12'));
    }
}
