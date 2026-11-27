<?php

namespace Tests\Unit\Dashboard;

use App\Services\Dashboard\ResolverPeriodoDashboard;
use Carbon\Carbon;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

class ResolverPeriodoDashboardTest extends TestCase
{
    private ResolverPeriodoDashboard $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new ResolverPeriodoDashboard();
    }

    public function test_mes_usa_el_mes_en_curso_hasta_hoy(): void
    {
        $ahora = Carbon::parse('2026-11-26 15:00:00');
        $periodo = $this->resolver->resolver(Request::create('/', 'GET'), $ahora);

        $this->assertSame(ResolverPeriodoDashboard::MES, $periodo['clave']);
        $this->assertSame('2026-11-01', $periodo['desde']);
        $this->assertSame('2026-11-26', $periodo['hasta']);
        $this->assertSame(26, $periodo['dias']);
        $this->assertSame(ResolverPeriodoDashboard::GRANOS_DIA, $periodo['grano']);
        $this->assertSame('2026-10-06', $periodo['comparacion_inicio']->toDateString());
        $this->assertSame('2026-10-31', $periodo['comparacion_fin']->toDateString());
    }

    public function test_siete_dias_incluye_hoy_y_compara_la_semana_previa(): void
    {
        $ahora = Carbon::parse('2026-11-26 10:00:00');
        $periodo = $this->resolver->resolver(
            Request::create('/', 'GET', ['periodo' => '7d']),
            $ahora
        );

        $this->assertSame('2026-11-20', $periodo['desde']);
        $this->assertSame('2026-11-26', $periodo['hasta']);
        $this->assertSame(7, $periodo['dias']);
        $this->assertSame('2026-11-13', $periodo['comparacion_inicio']->toDateString());
        $this->assertSame('2026-11-19', $periodo['comparacion_fin']->toDateString());
    }

    public function test_rango_invalido_cae_al_mes(): void
    {
        $ahora = Carbon::parse('2026-11-26 10:00:00');
        $periodo = $this->resolver->resolver(
            Request::create('/', 'GET', ['periodo' => 'rango', 'desde' => '2026-11-20', 'hasta' => '2026-11-01']),
            $ahora
        );

        $this->assertSame(ResolverPeriodoDashboard::MES, $periodo['clave']);
        $this->assertSame('2026-11-01', $periodo['desde']);
    }

    public function test_rango_largo_usa_grano_semanal(): void
    {
        $ahora = Carbon::parse('2026-11-26 10:00:00');
        $periodo = $this->resolver->resolver(
            Request::create('/', 'GET', [
                'periodo' => 'rango',
                'desde' => '2026-09-01',
                'hasta' => '2026-11-26',
            ]),
            $ahora
        );

        $this->assertSame(ResolverPeriodoDashboard::GRANOS_SEMANA, $periodo['grano']);
        $this->assertSame('custom', $this->resolver->parametrosContabilidad($periodo)['filtro']);
    }
}
