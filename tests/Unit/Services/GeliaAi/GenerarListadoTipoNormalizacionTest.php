<?php

namespace Tests\Unit\Services\GeliaAi;

use App\Services\GeliaAi\GenerarListadoDesdeRutasService;
use Tests\TestCase;

class GenerarListadoTipoNormalizacionTest extends TestCase
{
    public function test_normaliza_meli_mayusculas_y_aliases(): void
    {
        $svc = app(GenerarListadoDesdeRutasService::class);

        $this->assertSame('meli', $svc->normalizarTipoLista('MELI'));
        $this->assertSame('meli', $svc->normalizarTipoLista('Lista MELI'));
        $this->assertSame('meli', $svc->normalizarTipoLista('mercado_libre'));
        $this->assertSame('resurtido', $svc->normalizarTipoLista('RESURTIDO'));
        $this->assertSame(12, $svc->normalizarTipoLista('12'));
    }
}
