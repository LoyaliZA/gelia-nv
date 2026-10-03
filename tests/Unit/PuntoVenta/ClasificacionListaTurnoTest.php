<?php

namespace Tests\Unit\PuntoVenta;

use App\Support\Catalogos\ClasificacionListaTurno;
use PHPUnit\Framework\TestCase;

class ClasificacionListaTurnoTest extends TestCase
{
    public function test_diamante_sin_configuracion_queda_delante_en_la_cola(): void
    {
        $clasificacion = ClasificacionListaTurno::resolver('MAYOREO DIAMANTE', null, null);

        $this->assertSame('diamante', $clasificacion['tono']);
        $this->assertSame(10, $clasificacion['prioridad_cola']);
        $this->assertTrue($clasificacion['es_diamante']);
    }

    public function test_mayoreo_no_se_confunde_con_oro(): void
    {
        $clasificacion = ClasificacionListaTurno::resolver('MAYOREO', null, null);

        $this->assertNull($clasificacion['tono']);
        $this->assertSame(0, $clasificacion['prioridad_cola']);
    }

    public function test_la_prioridad_explicita_de_otra_lista_se_respeta(): void
    {
        $clasificacion = ClasificacionListaTurno::resolver('PUBLICO', 'plata', 4);

        $this->assertSame('plata', $clasificacion['tono']);
        $this->assertSame(4, $clasificacion['prioridad_cola']);
        $this->assertFalse($clasificacion['es_diamante']);
    }

    public function test_prioridad_cero_explicita_no_adelanta_diamante(): void
    {
        $clasificacion = ClasificacionListaTurno::resolver('DIAMANTE', 'diamante', 0);

        $this->assertSame('diamante', $clasificacion['tono']);
        $this->assertSame(0, $clasificacion['prioridad_cola']);
        $this->assertTrue($clasificacion['es_diamante']);
    }
}
