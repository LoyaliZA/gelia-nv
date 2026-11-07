<?php

namespace Tests\Unit\Presencia;

use App\Services\Presencia\FormatearPresenciaService;
use App\Services\Presencia\PresenciaUsuarioService;
use App\Services\Presencia\ResolverEstadoPresenciaService;
use App\Support\PresenciaCatalogo;
use Carbon\Carbon;
use Tests\TestCase;

class PresenciaEnLineaTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_pestana_vigente_esta_en_linea_aunque_la_actividad_sea_vieja(): void
    {
        Carbon::setTestNow('2026-11-02 18:00:00');

        $this->assertTrue(PresenciaCatalogo::enLinea([
            'pestanas' => ['abc' => now()->addSeconds(30)->toIso8601String()],
            'ultima_actividad_at' => now()->subHours(3)->toIso8601String(),
        ]));
    }

    public function test_pestanas_vacias_cuentan_como_fuera_de_linea(): void
    {
        Carbon::setTestNow('2026-11-02 18:00:00');

        $this->assertFalse(PresenciaCatalogo::enLinea([
            'pestanas' => [],
            'ultima_actividad_at' => now()->toIso8601String(),
        ]));
    }

    public function test_sin_pestanas_usa_la_ventana_de_actividad(): void
    {
        Carbon::setTestNow('2026-11-02 18:00:00');

        $this->assertTrue(PresenciaCatalogo::enLinea([
            'ultima_actividad_at' => now()->subSeconds(30)->toIso8601String(),
        ]));
        $this->assertFalse(PresenciaCatalogo::enLinea([
            'ultima_actividad_at' => now()->subSeconds(120)->toIso8601String(),
        ]));
    }

    public function test_el_payload_incluye_en_linea_y_ultima_actividad(): void
    {
        Carbon::setTestNow('2026-11-02 18:00:00');
        $ultima = now()->subMinutes(5)->toIso8601String();
        $servicio = new FormatearPresenciaService(new ResolverEstadoPresenciaService);

        $payload = $servicio->ejecutar([
            'estado' => 'disponible',
            'modo' => 'automatico',
            'automatizar' => false,
            'ultima_actividad_at' => $ultima,
        ]);

        $this->assertFalse($payload['en_linea']);
        $this->assertSame($ultima, $payload['ultima_actividad_at']);
        $this->assertSame('disponible', $payload['estado']);
        $this->assertSame('En línea', $payload['etiqueta']);
        $this->assertNull($payload['emoji']);
        $this->assertArrayNotHasKey('pestanas', $payload);
    }

    public function test_los_horarios_y_el_estado_manual_no_cambian_la_etiqueta(): void
    {
        Carbon::setTestNow('2026-11-02 18:00:00');
        $servicio = new FormatearPresenciaService(new ResolverEstadoPresenciaService);

        $payload = $servicio->ejecutar([
            'estado' => 'en_junta',
            'modo' => 'manual',
            'mensaje' => 'Cierre semanal',
            'automatizar' => true,
            'horarios' => [[
                'estado' => 'comiendo',
                'dias' => [1, 2, 3, 4, 5, 6, 7],
                'inicio' => '00:00',
                'fin' => '23:59',
            ]],
            'pestanas' => ['abc' => now()->addMinute()->toIso8601String()],
        ]);

        $this->assertSame('disponible', $payload['estado']);
        $this->assertSame('En línea', $payload['etiqueta']);
        $this->assertNull($payload['mensaje']);
        $this->assertTrue($payload['en_linea']);
    }

    public function test_solo_marca_fuera_de_linea_si_antes_se_aviso_en_linea(): void
    {
        Carbon::setTestNow('2026-11-02 18:00:00');
        $servicio = new PresenciaUsuarioService(
            new ResolverEstadoPresenciaService,
            new FormatearPresenciaService(new ResolverEstadoPresenciaService)
        );

        $this->assertTrue($servicio->debeMarcarFueraDeLinea([
            'en_linea_notificada' => true,
            'pestanas' => ['abc' => now()->subMinute()->toIso8601String()],
        ]));
        $this->assertFalse($servicio->debeMarcarFueraDeLinea([
            'en_linea_notificada' => true,
            'pestanas' => ['abc' => now()->addMinute()->toIso8601String()],
        ]));
        $this->assertFalse($servicio->debeMarcarFueraDeLinea([
            'pestanas' => [],
        ]));
    }
}
