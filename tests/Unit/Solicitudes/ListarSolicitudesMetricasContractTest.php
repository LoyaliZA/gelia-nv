<?php

namespace Tests\Unit\Solicitudes;

use App\Services\Solicitudes\ListarSolicitudesService;
use PHPUnit\Framework\TestCase;

class ListarSolicitudesMetricasContractTest extends TestCase
{
    public function test_empaquetar_metricas_expone_claves_del_widget(): void
    {
        $metricas = ListarSolicitudesService::empaquetarMetricas(3, 1, 2);

        $this->assertSame(['pendientes', 'respondidas_hoy', 'incorrectas'], array_keys($metricas));
        $this->assertSame(3, $metricas['pendientes']);
        $this->assertSame(1, $metricas['respondidas_hoy']);
        $this->assertSame(2, $metricas['incorrectas']);
    }

    public function test_metricas_es_metodo_publico(): void
    {
        $ref = new \ReflectionMethod(ListarSolicitudesService::class, 'metricas');
        $this->assertTrue($ref->isPublic());
        $this->assertSame(1, $ref->getNumberOfParameters());
    }
}
