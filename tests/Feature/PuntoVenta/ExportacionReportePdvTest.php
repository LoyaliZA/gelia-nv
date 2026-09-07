<?php

namespace Tests\Feature\PuntoVenta;

use App\Jobs\GenerarExportacionReportePdvJob;
use App\Models\ConfiguracionSistema;
use App\Models\PuntoVenta\ReportePdvExportacion;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\TurnoPdv;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\PuntoVenta\AlcancePdv;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Services\PuntoVenta\Reportes\GenerarCsvExportacionReportePdvService;
use App\Services\PuntoVenta\Reportes\ReportePdvExportacionFormato;
use App\Services\PuntoVenta\Reportes\ReportePdvExportacionTipo;
use App\Support\PuntoVenta\Reportes\ColumnasExportacionReportePdv;
use App\Support\PuntoVenta\Reportes\MetricaResguardoPdvIds;
use App\Support\PuntoVenta\Reportes\MetricaTurnoOperacionPdvIds;
use Carbon\Carbon;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ExportacionReportePdvTest extends TestCase
{
    use RefreshDatabase;

    private User $exportador;

    private User $otroExportador;

    private Sucursal $sucursalA;

    private Sucursal $sucursalB;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('Super Admin', 'web');
        $this->withoutMiddleware([
            ValidateCsrfToken::class,
            PreventRequestForgery::class,
        ]);
        $this->activarModulo();
        $this->seedPermisos();
        Storage::fake('local');

        $this->sucursalA = Sucursal::factory()->create(['nombre' => 'Sucursal Norte']);
        $this->sucursalB = Sucursal::factory()->create(['nombre' => 'Sucursal Sur']);

        $this->exportador = $this->crearExportador($this->sucursalA);
        $this->otroExportador = $this->crearExportador($this->sucursalB);
    }

    public function test_exportacion_csv_resguardos_sincrona_coincide_con_metricas(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-07 12:00:00', 'America/Mexico_City'));

        $this->crearResguardo($this->sucursalA, ['estado' => ResguardoPdv::ESTADO_PENDIENTE_RECEPCION]);
        $this->crearResguardo($this->sucursalA, [
            'estado' => ResguardoPdv::ESTADO_EN_CUSTODIA,
            'recepcion_fisica_at' => now()->subDay(),
        ]);
        $this->crearResguardo($this->sucursalB, ['estado' => ResguardoPdv::ESTADO_PENDIENTE_RECEPCION]);

        $response = $this->actingAs($this->exportador)->post(route('punto_venta.reportes.exportaciones.store'), [
            'tipo_reporte' => ReportePdvExportacionTipo::RESGUARDOS,
            'formato' => ReportePdvExportacionFormato::CSV,
            'desde' => '2026-09-01T00:00:00-06:00',
            'hasta' => '2026-09-08T00:00:00-06:00',
            'sucursal_id' => $this->sucursalA->id,
        ]);

        $response->assertOk();

        $exportacion = ReportePdvExportacion::query()->firstOrFail();
        $contenido = Storage::disk('local')->get($exportacion->ruta_archivo);
        $this->assertNotNull($contenido);
        $this->assertStringStartsWith("\xEF\xBB\xBF", $contenido);
        $this->assertStringContainsString(MetricaResguardoPdvIds::R_01_PENDIENTES_RECIBIR, $contenido);
        $this->assertStringContainsString('1', $contenido);
        $this->assertStringContainsString(array_values(ColumnasExportacionReportePdv::metricas())[0], $contenido);
        $this->assertSame(ReportePdvExportacion::ESTADO_COMPLETED, $exportacion->estado);
    }

    public function test_exportacion_pdf_encola_job(): void
    {
        Queue::fake();

        $this->crearResguardo($this->sucursalA, ['estado' => ResguardoPdv::ESTADO_PENDIENTE_RECEPCION]);

        $response = $this->actingAs($this->exportador)->postJson(route('punto_venta.reportes.exportaciones.store'), [
            'tipo_reporte' => ReportePdvExportacionTipo::RESGUARDOS,
            'formato' => ReportePdvExportacionFormato::PDF,
            'desde' => '2026-09-01T00:00:00-06:00',
            'hasta' => '2026-09-08T00:00:00-06:00',
        ]);

        $response->assertAccepted()
            ->assertJsonPath('modo', 'asincrono')
            ->assertJsonPath('exportacion.estado', ReportePdvExportacion::ESTADO_PENDING);

        Queue::assertPushed(GenerarExportacionReportePdvJob::class);
    }

    public function test_exportacion_conjunto_csv_pesada_encola_job(): void
    {
        Queue::fake();

        $this->actingAs($this->exportador)->postJson(route('punto_venta.reportes.exportaciones.store'), [
            'tipo_reporte' => ReportePdvExportacionTipo::CONJUNTO,
            'formato' => ReportePdvExportacionFormato::CSV,
            'desde' => '2026-09-01T00:00:00-06:00',
            'hasta' => '2026-09-08T00:00:00-06:00',
        ])->assertAccepted();

        Queue::assertPushed(GenerarExportacionReportePdvJob::class);
    }

    public function test_turnos_operacion_csv_incluye_metricas_turnos(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-07 12:00:00', 'America/Mexico_City'));

        TurnoPdv::factory()->create([
            'sucursal_id' => $this->sucursalA->id,
            'alta_at' => now()->subHour(),
            'estado' => TurnoPdv::ESTADO_EN_COLA,
        ]);

        $this->actingAs($this->exportador)->post(route('punto_venta.reportes.exportaciones.store'), [
            'tipo_reporte' => ReportePdvExportacionTipo::TURNOS_OPERACION,
            'formato' => ReportePdvExportacionFormato::CSV,
            'desde' => '2026-09-07T00:00:00-06:00',
            'hasta' => '2026-09-08T00:00:00-06:00',
            'sucursal_id' => $this->sucursalA->id,
            'alcance' => MetricaTurnoOperacionPdvIds::ALCANCE_GLOBAL,
        ])->assertOk();

        $exportacion = ReportePdvExportacion::query()->firstOrFail();
        $contenido = Storage::disk('local')->get($exportacion->ruta_archivo);
        $this->assertStringContainsString(MetricaTurnoOperacionPdvIds::T_01_ALTAS, $contenido);
    }

    public function test_descarga_no_disponible_para_otro_usuario(): void
    {
        $this->crearResguardo($this->sucursalA, ['estado' => ResguardoPdv::ESTADO_PENDIENTE_RECEPCION]);

        $this->actingAs($this->exportador)->post(route('punto_venta.reportes.exportaciones.store'), [
            'tipo_reporte' => ReportePdvExportacionTipo::RESGUARDOS,
            'formato' => ReportePdvExportacionFormato::CSV,
            'desde' => '2026-09-01T00:00:00-06:00',
            'hasta' => '2026-09-08T00:00:00-06:00',
        ]);

        $exportacion = ReportePdvExportacion::query()
            ->where('user_id', $this->exportador->id)
            ->firstOrFail();

        $this->actingAs($this->otroExportador)
            ->get(route('punto_venta.reportes.exportaciones.descargar', ['exportacion' => $exportacion->id]))
            ->assertNotFound();
    }

    public function test_get_descarga_no_genera_nueva_exportacion(): void
    {
        $this->crearResguardo($this->sucursalA, ['estado' => ResguardoPdv::ESTADO_PENDIENTE_RECEPCION]);

        $this->actingAs($this->exportador)->post(route('punto_venta.reportes.exportaciones.store'), [
            'tipo_reporte' => ReportePdvExportacionTipo::RESGUARDOS,
            'formato' => ReportePdvExportacionFormato::CSV,
            'desde' => '2026-09-01T00:00:00-06:00',
            'hasta' => '2026-09-08T00:00:00-06:00',
        ]);

        $exportacion = ReportePdvExportacion::query()->firstOrFail();

        $this->actingAs($this->exportador)
            ->get(route('punto_venta.reportes.exportaciones.descargar', ['exportacion' => $exportacion->id]))
            ->assertOk();

        $this->assertSame(1, ReportePdvExportacion::query()->count());
    }

    public function test_exportacion_expirada_no_descarga(): void
    {
        $this->crearResguardo($this->sucursalA, ['estado' => ResguardoPdv::ESTADO_PENDIENTE_RECEPCION]);

        $this->actingAs($this->exportador)->post(route('punto_venta.reportes.exportaciones.store'), [
            'tipo_reporte' => ReportePdvExportacionTipo::RESGUARDOS,
            'formato' => ReportePdvExportacionFormato::CSV,
            'desde' => '2026-09-01T00:00:00-06:00',
            'hasta' => '2026-09-08T00:00:00-06:00',
        ]);

        $exportacion = ReportePdvExportacion::query()->firstOrFail();
        $exportacion->update(['expira_at' => now()->subMinute()]);

        $this->actingAs($this->exportador)
            ->get(route('punto_venta.reportes.exportaciones.descargar', ['exportacion' => $exportacion->id]))
            ->assertNotFound();
    }

    public function test_reintento_fallido_reencola_job_con_mismo_id(): void
    {
        Queue::fake();

        $exportacion = ReportePdvExportacion::query()->create([
            'id' => '11111111-1111-1111-1111-111111111111',
            'user_id' => $this->exportador->id,
            'titulo' => 'Reporte fallido',
            'tipo_reporte' => ReportePdvExportacionTipo::RESGUARDOS,
            'formato' => ReportePdvExportacionFormato::CSV,
            'estado' => ReportePdvExportacion::ESTADO_FAILED,
            'filtros' => [
                'tipo_reporte' => ReportePdvExportacionTipo::RESGUARDOS,
                'formato' => ReportePdvExportacionFormato::CSV,
                'desde' => '2026-09-01T00:00:00-06:00',
                'hasta' => '2026-09-08T00:00:00-06:00',
            ],
            'error' => 'fallo simulado',
            'expira_at' => now()->subHour(),
        ]);

        $this->actingAs($this->exportador)
            ->postJson(route('punto_venta.reportes.exportaciones.reintentar', ['exportacion' => $exportacion->id]))
            ->assertAccepted()
            ->assertJsonPath('job_id', $exportacion->id);

        $exportacion->refresh();
        $this->assertSame(ReportePdvExportacion::ESTADO_PENDING, $exportacion->estado);
        $this->assertNull($exportacion->error);
        Queue::assertPushed(GenerarExportacionReportePdvJob::class);
        $this->assertSame(1, ReportePdvExportacion::query()->count());
    }

    public function test_job_fallido_marca_estado_y_permite_reintento(): void
    {
        $exportacion = ReportePdvExportacion::query()->create([
            'id' => '22222222-2222-2222-2222-222222222222',
            'user_id' => $this->exportador->id,
            'titulo' => 'Reporte job',
            'tipo_reporte' => ReportePdvExportacionTipo::RESGUARDOS,
            'formato' => ReportePdvExportacionFormato::CSV,
            'estado' => ReportePdvExportacion::ESTADO_PENDING,
            'filtros' => [
                'tipo_reporte' => ReportePdvExportacionTipo::RESGUARDOS,
                'formato' => ReportePdvExportacionFormato::CSV,
            ],
            'expira_at' => now()->addDay(),
        ]);

        $mock = \Mockery::mock(GenerarCsvExportacionReportePdvService::class);
        $mock->shouldReceive('ejecutar')->andThrow(new \RuntimeException('Error de generación'));
        $this->app->instance(GenerarCsvExportacionReportePdvService::class, $mock);

        try {
            $job = new GenerarExportacionReportePdvJob(
                $exportacion->filtros ?? [],
                $this->exportador,
                $exportacion->id,
            );
            $job->handle(app(\App\Services\PuntoVenta\Reportes\SolicitarExportacionReportePdvService::class));
        } catch (\RuntimeException) {
        }

        $exportacion->refresh();
        $this->assertSame(ReportePdvExportacion::ESTADO_FAILED, $exportacion->estado);
        $this->assertStringContainsString('Error de generación', (string) $exportacion->error);
        $this->assertTrue($exportacion->puedeReintentar());
    }

    public function test_sin_permiso_exportar_rechaza(): void
    {
        $usuario = User::factory()->create();
        $usuario->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_RESGUARDOS_VER,
            AlcancePdv::PERMISO_ALCANCE_GLOBAL,
        ]);
        $usuario->concederAccesoSucursal($this->sucursalA, esPrincipal: true);

        $this->actingAs($usuario)->postJson(route('punto_venta.reportes.exportaciones.store'), [
            'tipo_reporte' => ReportePdvExportacionTipo::RESGUARDOS,
            'formato' => ReportePdvExportacionFormato::CSV,
        ])->assertForbidden();
    }

    public function test_resguardos_sin_alcance_global_rechaza(): void
    {
        $usuario = User::factory()->create();
        $usuario->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_RESGUARDOS_VER,
            PuntoVentaModulo::PERMISO_TURNOS_VER,
            PuntoVentaModulo::PERMISO_REPORTES_EXPORTAR,
        ]);
        $usuario->concederAccesoSucursal($this->sucursalA, esPrincipal: true);

        $this->actingAs($usuario)->postJson(route('punto_venta.reportes.exportaciones.store'), [
            'tipo_reporte' => ReportePdvExportacionTipo::RESGUARDOS,
            'formato' => ReportePdvExportacionFormato::CSV,
        ])->assertForbidden();
    }

    private function crearExportador(Sucursal $sucursal): User
    {
        $usuario = User::factory()->create();
        $usuario->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_RESGUARDOS_VER,
            PuntoVentaModulo::PERMISO_TURNOS_VER,
            AlcancePdv::PERMISO_ALCANCE_GLOBAL,
            PuntoVentaModulo::PERMISO_REPORTES_EXPORTAR,
        ]);
        $usuario->concederAccesoSucursal($sucursal, esPrincipal: true);

        return $usuario;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function crearResguardo(Sucursal $sucursal, array $overrides = []): ResguardoPdv
    {
        return ResguardoPdv::factory()->create(array_merge([
            'sucursal_id' => $sucursal->id,
            'salida_cedis_at' => now(),
        ], $overrides));
    }

    private function activarModulo(): void
    {
        ConfiguracionSistema::query()->updateOrCreate(
            ['clave' => PuntoVentaModulo::CLAVE_FLAG],
            ['valor' => '1']
        );
    }

    private function seedPermisos(): void
    {
        foreach (PuntoVentaModulo::permisosIniciales() as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }
    }
}
