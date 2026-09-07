<?php

namespace Tests\Unit\PuntoVenta;

use App\Services\PuntoVenta\Alertas\NormalizarPreferenciasAlertasPdvService;
use Tests\TestCase;

class NormalizarPreferenciasAlertasPdvTest extends TestCase
{
    public function test_aplica_defaults_seguros_sin_configuracion(): void
    {
        $servicio = new NormalizarPreferenciasAlertasPdvService;

        $resultado = $servicio->ejecutar([]);

        $this->assertTrue($resultado['canales']['sonido']);
        $this->assertTrue($resultado['canales']['voz']);
        $this->assertTrue($resultado['canales']['web_push']);
        $this->assertSame('default', $resultado['tono_id']);
    }
}
