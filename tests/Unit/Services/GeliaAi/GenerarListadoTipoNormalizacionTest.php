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

    public function test_filtro_de_relojes_solo_conserva_descripciones_que_empiezan_con_r(): void
    {
        $this->assertTrue(GenerarListadoDesdeRutasService::descripcionEsReloj('RELOJ CASIO'));
        $this->assertTrue(GenerarListadoDesdeRutasService::descripcionEsReloj('  reloj de pulso'));
        $this->assertFalse(GenerarListadoDesdeRutasService::descripcionEsReloj('PERFUME'));
        $this->assertFalse(GenerarListadoDesdeRutasService::descripcionEsReloj(45293));
        $this->assertFalse(GenerarListadoDesdeRutasService::descripcionEsReloj(new \DateTimeImmutable('2024-01-02')));
        $this->assertFalse(GenerarListadoDesdeRutasService::descripcionEsReloj(null));
    }
}
