<?php

namespace Tests\Unit\PuntoVenta\Reportes;

use App\Models\ConfiguracionSistema;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvEvento;
use App\Models\PuntoVenta\ResguardoPdvIncidencia;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\PuntoVenta\AlcancePdv;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Services\PuntoVenta\Reportes\Resguardos\CalcularMetricasReporteResguardoPdvService;
use App\Services\PuntoVenta\Resguardos\PlazosCustodiaResguardoPdvConfig;
use App\Support\PuntoVenta\Reportes\MetricaResguardoPdvIds;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CalcularMetricasReporteResguardoPdvTest extends TestCase
{
    use RefreshDatabase;

    private User $analista;

    private Sucursal $sucursalA;

    private Sucursal $sucursalB;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('Super Admin', 'web');
        $this->activarModulo();
        $this->seedPermisos();
        $this->seedPlazos();

        $this->sucursalA = Sucursal::factory()->create(['nombre' => 'Sucursal Norte']);
        $this->sucursalB = Sucursal::factory()->create(['nombre' => 'Sucursal Sur']);

        $this->analista = User::factory()->create();
        $this->analista->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_RESGUARDOS_VER,
            AlcancePdv::PERMISO_ALCANCE_GLOBAL,
        ]);
    }

    public function test_cm06_recepcion_parcial_y_tiempo_a_recepcion(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-07 12:00:00', 'America/Mexico_City'));

        $salida = Carbon::parse('2026-09-01 08:00:00', 'America/Mexico_City');
        $recepcion = Carbon::parse('2026-09-03 14:00:00', 'America/Mexico_City');

        $resguardo = $this->crearResguardo($this->sucursalA, [
            'estado' => ResguardoPdv::ESTADO_EN_CUSTODIA,
            'salida_cedis_at' => $salida,
            'recepcion_fisica_at' => $recepcion,
        ]);

        ResguardoPdvEvento::query()->create([
            'resguardo_id' => $resguardo->id,
            'tipo_evento' => ResguardoPdvEvento::TIPO_RECEPCION_PARCIAL,
            'ocurrido_at' => $recepcion,
        ]);

        $payload = $this->metricas([
            'desde' => '2026-09-01T00:00:00-06:00',
            'hasta' => '2026-09-08T00:00:00-06:00',
            'sucursal_id' => $this->sucursalA->id,
        ]);

        $segundosEsperados = $salida->diffInSeconds($recepcion);

        $this->assertSame(1, $payload['metricas'][MetricaResguardoPdvIds::R_02_EN_CUSTODIA]['valor']);
        $this->assertSame(1, $payload['metricas'][MetricaResguardoPdvIds::R_11_RECEPCIONES_RANGO]['valor']);
        $this->assertEquals($segundosEsperados, $payload['metricas'][MetricaResguardoPdvIds::R_07_TIEMPO_RECEPCION]['promedio_segundos']);
        $this->assertArrayNotHasKey('percentiles', $payload['metricas'][MetricaResguardoPdvIds::R_07_TIEMPO_RECEPCION]);
    }

    public function test_aislamiento_por_sucursal_en_conteos_y_desglose(): void
    {
        $this->crearResguardo($this->sucursalA, ['estado' => ResguardoPdv::ESTADO_PENDIENTE_RECEPCION]);
        $this->crearResguardo($this->sucursalA, ['estado' => ResguardoPdv::ESTADO_EN_CUSTODIA, 'recepcion_fisica_at' => now()->subDay()]);
        $this->crearResguardo($this->sucursalB, ['estado' => ResguardoPdv::ESTADO_PENDIENTE_RECEPCION]);
        $this->crearResguardo($this->sucursalB, ['estado' => ResguardoPdv::ESTADO_PENDIENTE_RECEPCION]);

        $filtrado = $this->metricas(['sucursal_id' => $this->sucursalA->id]);
        $this->assertSame(1, $filtrado['metricas'][MetricaResguardoPdvIds::R_01_PENDIENTES_RECIBIR]['valor']);
        $this->assertSame(1, $filtrado['metricas'][MetricaResguardoPdvIds::R_02_EN_CUSTODIA]['valor']);
        $this->assertSame([], $filtrado['por_sucursal']);

        $global = $this->metricas();
        $this->assertSame(3, $global['metricas'][MetricaResguardoPdvIds::R_01_PENDIENTES_RECIBIR]['valor']);
        $this->assertSame(1, $global['metricas'][MetricaResguardoPdvIds::R_02_EN_CUSTODIA]['valor']);
        $this->assertSame(1, $global['por_sucursal'][(string) $this->sucursalA->id][MetricaResguardoPdvIds::R_01_PENDIENTES_RECIBIR]['valor']);
        $this->assertSame(2, $global['por_sucursal'][(string) $this->sucursalB->id][MetricaResguardoPdvIds::R_01_PENDIENTES_RECIBIR]['valor']);
    }

    public function test_rango_exclusivo_en_hasta_y_eventos(): void
    {
        $resguardo = $this->crearResguardo($this->sucursalA, [
            'estado' => ResguardoPdv::ESTADO_EN_CUSTODIA,
            'recepcion_fisica_at' => Carbon::parse('2026-09-03 10:00:00'),
        ]);

        $enLimite = Carbon::parse('2026-09-05 09:00:00');
        $fueraLimite = Carbon::parse('2026-09-05 10:00:00');

        ResguardoPdvEvento::query()->create([
            'resguardo_id' => $resguardo->id,
            'tipo_evento' => ResguardoPdvEvento::TIPO_RECEPCION_COMPLETA,
            'ocurrido_at' => $enLimite,
        ]);
        ResguardoPdvEvento::query()->create([
            'resguardo_id' => $resguardo->id,
            'tipo_evento' => ResguardoPdvEvento::TIPO_DEVOLUCION_CONFIRMADA,
            'ocurrido_at' => $fueraLimite,
        ]);

        $payload = $this->metricas([
            'desde' => '2026-09-05T00:00:00',
            'hasta' => '2026-09-05T10:00:00',
            'sucursal_id' => $this->sucursalA->id,
        ]);

        $this->assertSame(1, $payload['metricas'][MetricaResguardoPdvIds::R_11_RECEPCIONES_RANGO]['valor']);
        $this->assertSame(0, $payload['metricas'][MetricaResguardoPdvIds::R_12_DEVOLUCIONES_RANGO]['valor']);
    }

    public function test_tasa_entrega_y_tasa_incidencia(): void
    {
        $entregado = $this->crearResguardo($this->sucursalA, [
            'estado' => ResguardoPdv::ESTADO_ENTREGADO,
            'recepcion_fisica_at' => now()->subDays(3),
            'entrega_completada_at' => now()->subDay(),
        ]);
        $devuelto = $this->crearResguardo($this->sucursalA, [
            'estado' => ResguardoPdv::ESTADO_DEVUELTO,
            'recepcion_fisica_at' => now()->subDays(4),
            'devolucion_confirmada_at' => now()->subHours(12),
        ]);

        ResguardoPdvEvento::query()->create([
            'resguardo_id' => $entregado->id,
            'tipo_evento' => ResguardoPdvEvento::TIPO_ENTREGA_TITULAR,
            'ocurrido_at' => $entregado->entrega_completada_at,
        ]);
        ResguardoPdvEvento::query()->create([
            'resguardo_id' => $devuelto->id,
            'tipo_evento' => ResguardoPdvEvento::TIPO_DEVOLUCION_CONFIRMADA,
            'ocurrido_at' => $devuelto->devolucion_confirmada_at,
        ]);

        ResguardoPdvIncidencia::query()->create([
            'resguardo_id' => $entregado->id,
            'tipo' => ResguardoPdvIncidencia::TIPO_DANO,
            'estado' => ResguardoPdvIncidencia::ESTADO_ABIERTA,
            'descripcion' => 'Daño en empaque',
            'reportado_por_id' => $this->analista->id,
            'reportado_at' => now()->subHours(6),
            'version' => 1,
        ]);

        $payload = $this->metricas([
            'desde' => now()->subWeek()->toIso8601String(),
            'hasta' => now()->addDay()->toIso8601String(),
            'sucursal_id' => $this->sucursalA->id,
        ]);

        $this->assertSame(50.0, $payload['metricas'][MetricaResguardoPdvIds::R_09_TASA_ENTREGA]['valor']);
        $this->assertSame(1, $payload['metricas'][MetricaResguardoPdvIds::R_09_TASA_ENTREGA]['entregados']);
        $this->assertSame(1, $payload['metricas'][MetricaResguardoPdvIds::R_09_TASA_ENTREGA]['devueltos']);
        $this->assertSame(50.0, $payload['metricas'][MetricaResguardoPdvIds::R_10_TASA_INCIDENCIA]['valor']);
        $this->assertSame(1, $payload['metricas'][MetricaResguardoPdvIds::R_03_INCIDENCIAS_ABIERTAS]['valor']);
    }

    public function test_rechaza_sucursal_no_autorizada(): void
    {
        $this->expectException(AuthorizationException::class);

        $this->metricas(['sucursal_id' => $this->sucursalB->id + 999]);
    }

    public function test_rechaza_usuario_sin_alcance_global(): void
    {
        $piso = User::factory()->create();
        $piso->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_RESGUARDOS_VER,
        ]);
        $piso->concederAccesoSucursal($this->sucursalA, esPrincipal: true);

        $this->expectException(AuthorizationException::class);

        app(CalcularMetricasReporteResguardoPdvService::class)->ejecutar($piso, []);
    }

    public function test_volumen_representativo_sin_explosion_de_consultas(): void
    {
        DB::enableQueryLog();

        for ($i = 0; $i < 80; $i++) {
            $this->crearResguardo($this->sucursalA, [
                'estado' => $i % 2 === 0
                    ? ResguardoPdv::ESTADO_PENDIENTE_RECEPCION
                    : ResguardoPdv::ESTADO_EN_CUSTODIA,
                'recepcion_fisica_at' => $i % 2 === 0 ? null : now()->subDays($i % 5 + 1),
                'salida_cedis_at' => now()->subDays($i % 7 + 1),
            ]);
        }

        $this->metricas(['sucursal_id' => $this->sucursalA->id]);

        $consultas = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(200, $consultas);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    private function metricas(array $filtros = []): array
    {
        return app(CalcularMetricasReporteResguardoPdvService::class)->ejecutar($this->analista, $filtros);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function crearResguardo(Sucursal $sucursal, array $overrides = []): ResguardoPdv
    {
        return ResguardoPdv::factory()->create(array_merge([
            'sucursal_id' => $sucursal->id,
            'salida_cedis_at' => now()->subDays(2),
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

    private function seedPlazos(): void
    {
        $config = new PlazosCustodiaResguardoPdvConfig;
        $config->guardar(array_merge($config->configuracionInicialAprobada(), [
            'zona_horaria' => 'America/Mexico_City',
        ]));
        Cache::forget(PlazosCustodiaResguardoPdvConfig::CACHE_KEY);
    }
}
