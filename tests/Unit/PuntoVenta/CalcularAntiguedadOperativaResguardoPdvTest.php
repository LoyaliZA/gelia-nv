<?php

namespace Tests\Unit\PuntoVenta;

use App\Services\PuntoVenta\Resguardos\CalcularAntiguedadOperativaResguardoPdvService;
use App\Services\PuntoVenta\Resguardos\PlazosCustodiaResguardoPdvConfig;
use App\Support\PuntoVenta\Resguardos\AntiguedadOperativaResguardoPdv;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CalcularAntiguedadOperativaResguardoPdvTest extends TestCase
{
    use RefreshDatabase;
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function config(array $overrides = []): array
    {
        return (new PlazosCustodiaResguardoPdvConfig)->normalizar(array_merge([
            'activo' => true,
            'zona_horaria' => 'America/Mexico_City',
            'tipo_dias' => PlazosCustodiaResguardoPdvConfig::TIPO_DIAS_HABILES,
            'dias_habiles' => [1, 2, 3, 4, 5],
            'custodia_dias' => 15,
            'aviso_previo_dias' => 3,
            'rezago_dias' => 15,
        ], $overrides));
    }

    private function calc(): CalcularAntiguedadOperativaResguardoPdvService
    {
        return app(CalcularAntiguedadOperativaResguardoPdvService::class);
    }

    public function test_fecha_limite_custodia_suma_dias_habiles_lun_vie(): void
    {
        $ancla = Carbon::parse('2026-08-03 10:00:00', 'America/Mexico_City');
        $limite = $this->calc()->fechaLimiteDesdeAncla($ancla, 15, $this->config());

        $this->assertSame('2026-08-24 23:59:59', $limite->format('Y-m-d H:i:s'));
    }

    public function test_fecha_limite_natural_suma_dias_calendario(): void
    {
        $ancla = Carbon::parse('2026-08-01 10:00:00', 'America/Mexico_City');
        $limite = $this->calc()->fechaLimiteDesdeAncla($ancla, 5, $this->config([
            'tipo_dias' => PlazosCustodiaResguardoPdvConfig::TIPO_DIAS_NATURALES,
        ]));

        $this->assertSame('2026-08-06 23:59:59', $limite->format('Y-m-d H:i:s'));
    }

    public function test_clasificacion_vencido_tras_plazo_de_custodia(): void
    {
        $resguardo = new \App\Models\PuntoVenta\ResguardoPdv([
            'sucursal_id' => 1,
            'estado' => \App\Models\PuntoVenta\ResguardoPdv::ESTADO_EN_CUSTODIA,
            'recepcion_fisica_at' => Carbon::parse('2026-07-01 10:00:00'),
        ]);

        $config = new PlazosCustodiaResguardoPdvConfig;
        $config->guardar($config->configuracionInicialAprobada());

        $evaluacion = $this->calc()->evaluar(
            $resguardo,
            Carbon::parse('2026-08-28 12:00:00', 'America/Mexico_City')
        );

        $this->assertTrue($evaluacion['clasificaciones'][AntiguedadOperativaResguardoPdv::VENCIDO]);
        $this->assertFalse($evaluacion['clasificaciones'][AntiguedadOperativaResguardoPdv::PROXIMO_A_VENCER]);
    }

    public function test_clasificacion_proximo_a_vencer_en_ventana_de_aviso(): void
    {
        $resguardo = new \App\Models\PuntoVenta\ResguardoPdv([
            'sucursal_id' => 1,
            'estado' => \App\Models\PuntoVenta\ResguardoPdv::ESTADO_EN_CUSTODIA,
            'recepcion_fisica_at' => Carbon::parse('2026-08-06 10:00:00', 'America/Mexico_City'),
        ]);

        $config = new PlazosCustodiaResguardoPdvConfig;
        $config->guardar($config->configuracionInicialAprobada());

        $evaluacion = $this->calc()->evaluar(
            $resguardo,
            Carbon::parse('2026-08-24 10:00:00', 'America/Mexico_City')
        );

        $this->assertTrue($evaluacion['clasificaciones'][AntiguedadOperativaResguardoPdv::PROXIMO_A_VENCER]);
        $this->assertFalse($evaluacion['clasificaciones'][AntiguedadOperativaResguardoPdv::VENCIDO]);
    }

    public function test_override_por_sucursal_usa_plazos_distintos(): void
    {
        $config = new PlazosCustodiaResguardoPdvConfig;
        $inicial = $config->configuracionInicialAprobada();
        $inicial['por_sucursal'] = [
            '99' => ['rezago_dias' => 5],
        ];
        $config->guardar($inicial);

        $resguardo = new \App\Models\PuntoVenta\ResguardoPdv([
            'sucursal_id' => 99,
            'estado' => \App\Models\PuntoVenta\ResguardoPdv::ESTADO_PENDIENTE_RECEPCION,
            'salida_cedis_at' => Carbon::parse('2026-08-18 10:00:00', 'America/Mexico_City'),
        ]);

        $evaluacion = $this->calc()->evaluar(
            $resguardo,
            Carbon::parse('2026-08-26 10:00:00', 'America/Mexico_City')
        );

        $this->assertTrue($evaluacion['clasificaciones'][AntiguedadOperativaResguardoPdv::REZAGADO]);
    }
}
