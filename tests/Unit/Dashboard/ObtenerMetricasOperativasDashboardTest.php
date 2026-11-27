<?php

namespace Tests\Unit\Dashboard;

use App\Models\CatalogoEstadoSolicitud;
use App\Models\CatalogoProceso;
use App\Models\SolicitudTag;
use App\Models\User;
use App\Services\Dashboard\ObtenerMetricasOperativasDashboardService;
use App\Services\Dashboard\ResolverPeriodoDashboard;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ObtenerMetricasOperativasDashboardTest extends TestCase
{
    use RefreshDatabase;

    private CatalogoProceso $proceso;

    private CatalogoProceso $operativo;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['Pendiente', 'Respondida', 'Cancelada'] as $nombre) {
            CatalogoEstadoSolicitud::firstOrCreate(['nombre' => $nombre, 'activo' => true]);
        }
        CatalogoEstadoSolicitud::reiniciarCache();
        $this->proceso = CatalogoProceso::create([
            'nombre' => 'ASIGNAR TAG',
            'categoria_flujo' => CatalogoProceso::CATEGORIA_FINANCIERO,
            'activo' => true,
        ]);
        $this->operativo = CatalogoProceso::create([
            'nombre' => 'CANCELACION',
            'categoria_flujo' => CatalogoProceso::CATEGORIA_OPERATIVO,
            'activo' => true,
        ]);
        Role::findOrCreate('Super Admin', 'web');
    }

    public function test_serie_suma_cotizado_compara_periodo_anterior_y_omite_operativas(): void
    {
        Carbon::setTestNow('2026-11-26 12:00:00');
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $otro = User::factory()->create();

        $this->solicitud($admin, 1000, '2026-11-10 10:00:00');
        $this->solicitud($admin, 500, '2026-11-10 18:00:00');
        $this->solicitud($otro, 250, '2026-11-12 09:00:00');
        $this->solicitud($admin, 900, '2026-10-20 09:00:00');
        $this->solicitud($admin, 400, '2026-11-11 09:00:00', $this->operativo->id);

        $periodo = app(ResolverPeriodoDashboard::class)->resolver(
            Request::create('/', 'GET', ['periodo' => 'mes']),
            Carbon::parse('2026-11-26 12:00:00')
        );
        $metricas = app(ObtenerMetricasOperativasDashboardService::class)->ejecutar($admin, $periodo);

        $this->assertSame(1750.0, $metricas['cotizado']['total']);
        $this->assertSame(900.0, $metricas['cotizado']['total_anterior']);
        $this->assertSame(3, $metricas['actividad']['creadas']);
        $this->assertSame('vendedor', $metricas['cotizado']['desglose']['dimension']);
        $nombres = collect($metricas['cotizado']['desglose']['items'])->pluck('nombre')->all();
        $this->assertContains($admin->name, $nombres);
        $this->assertContains($otro->name, $nombres);
    }

    public function test_vendedor_solo_ve_sus_solicitudes(): void
    {
        Carbon::setTestNow('2026-11-26 12:00:00');
        $vendedor = User::factory()->create();
        $ajeno = User::factory()->create();
        $this->solicitud($vendedor, 300, '2026-11-05 10:00:00');
        $this->solicitud($ajeno, 8000, '2026-11-05 10:00:00');

        $periodo = app(ResolverPeriodoDashboard::class)->resolver(
            Request::create('/', 'GET', ['periodo' => 'mes']),
            Carbon::parse('2026-11-26 12:00:00')
        );
        $metricas = app(ObtenerMetricasOperativasDashboardService::class)->ejecutar($vendedor, $periodo);

        $this->assertSame(300.0, $metricas['cotizado']['total']);
        $this->assertSame(1, $metricas['actividad']['creadas']);
        $this->assertSame('proceso', $metricas['cotizado']['desglose']['dimension']);
    }

    public function test_cuenta_cerradas_por_updated_at_en_el_periodo(): void
    {
        Carbon::setTestNow('2026-11-26 12:00:00');
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $solicitud = $this->solicitud($admin, 100, '2026-10-01 10:00:00');
        $solicitud->forceFill([
            'catalogo_estado_solicitud_id' => CatalogoEstadoSolicitud::idDe('Respondida'),
            'updated_at' => '2026-11-15 08:00:00',
        ])->save();

        $periodo = app(ResolverPeriodoDashboard::class)->resolver(
            Request::create('/', 'GET', ['periodo' => 'mes']),
            Carbon::parse('2026-11-26 12:00:00')
        );
        $metricas = app(ObtenerMetricasOperativasDashboardService::class)->ejecutar($admin, $periodo);

        $this->assertSame(0, $metricas['actividad']['creadas']);
        $this->assertSame(1, $metricas['actividad']['cerradas']);
    }

    private function solicitud(User $vendedor, float $monto, string $fecha, ?int $procesoId = null): SolicitudTag
    {
        $solicitud = SolicitudTag::create([
            'vendedor_id' => $vendedor->id,
            'catalogo_proceso_id' => $procesoId ?? $this->proceso->id,
            'catalogo_estado_solicitud_id' => CatalogoEstadoSolicitud::idDe('Pendiente'),
            'monto_cotizado' => $monto,
        ]);
        $solicitud->forceFill(['created_at' => $fecha, 'updated_at' => $fecha])->save();

        return $solicitud;
    }
}
