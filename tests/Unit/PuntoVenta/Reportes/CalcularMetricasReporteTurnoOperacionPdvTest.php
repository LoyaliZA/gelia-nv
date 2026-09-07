<?php

namespace Tests\Unit\PuntoVenta\Reportes;

use App\Models\ConfiguracionSistema;
use App\Models\PuntoVenta\IntervaloOperativoPdv;
use App\Models\PuntoVenta\JornadaPdv;
use App\Models\PuntoVenta\OperacionPdvEvento;
use App\Models\PuntoVenta\SucursalDiaOperacionPdv;
use App\Models\PuntoVenta\TurnoPdv;
use App\Models\PuntoVenta\TurnoPdvAtencion;
use App\Models\PuntoVenta\TurnoPdvEvento;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\PuntoVenta\AlcancePdv;
use App\Services\PuntoVenta\Operacion\HorarioCierreOperacionPdvConfig;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Services\PuntoVenta\Reportes\TurnosOperacion\CalcularMetricasReporteTurnoOperacionPdvService;
use App\Support\PuntoVenta\Operacion\TipoIntervaloOperativoPdv;
use App\Support\PuntoVenta\Reportes\MetricaTurnoOperacionPdvIds;
use App\Support\PuntoVenta\Turnos\MotivosCierreAtencionTurnoPdv;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CalcularMetricasReporteTurnoOperacionPdvTest extends TestCase
{
    use RefreshDatabase;

    private User $analista;

    private User $vendedor;

    private Sucursal $sucursalA;

    private Sucursal $sucursalB;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('Super Admin', 'web');
        $this->activarModulo();
        $this->seedPermisos();
        $this->seedHorarioCierre();

        $this->sucursalA = Sucursal::factory()->create(['nombre' => 'Sucursal Norte']);
        $this->sucursalB = Sucursal::factory()->create(['nombre' => 'Sucursal Sur']);

        $this->analista = User::factory()->create();
        $this->analista->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_TURNOS_VER,
            AlcancePdv::PERMISO_ALCANCE_GLOBAL,
        ]);

        $this->vendedor = User::factory()->create();
        $this->vendedor->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_TURNOS_VER,
        ]);
        $this->vendedor->concederAccesoSucursal($this->sucursalA, esPrincipal: true);
    }

    public function test_cm01_espera_en_cola(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-07 12:00:00', 'America/Mexico_City'));

        $alta = Carbon::parse('2026-09-07 10:00:00', 'America/Mexico_City');
        $asignacion = Carbon::parse('2026-09-07 10:12:00', 'America/Mexico_City');

        $turno = $this->crearTurno($this->sucursalA, ['alta_at' => $alta, 'estado' => TurnoPdv::ESTADO_ASIGNADO]);
        $this->crearAtencion($turno, $this->vendedor, [
            'inicio_at' => $asignacion,
            'atencion_inicio_at' => $asignacion->copy()->addMinutes(2),
            'fin_at' => $asignacion->copy()->addMinutes(10),
            'motivo_cierre' => MotivosCierreAtencionTurnoPdv::VENTA,
        ]);

        $payload = $this->metricas([
            'desde' => '2026-09-07T00:00:00-06:00',
            'hasta' => '2026-09-08T00:00:00-06:00',
            'sucursal_id' => $this->sucursalA->id,
        ]);

        $this->assertSame(720, $payload['metricas'][MetricaTurnoOperacionPdvIds::T_05_ESPERA_COLA]['promedio_segundos']);
        $this->assertArrayNotHasKey('percentiles', $payload['metricas'][MetricaTurnoOperacionPdvIds::T_15_PERCENTILES_ESPERA_COLA]);
    }

    public function test_cm02_no_se_presento_sin_atencion_iniciada(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-07 12:00:00', 'America/Mexico_City'));

        $inicio = Carbon::parse('2026-09-07 10:00:00', 'America/Mexico_City');
        $fin = Carbon::parse('2026-09-07 10:06:00', 'America/Mexico_City');

        $turno = $this->crearTurno($this->sucursalA, [
            'alta_at' => $inicio->copy()->subMinutes(5),
            'estado' => TurnoPdv::ESTADO_CERRADO,
            'cerrado_at' => $fin,
        ]);

        $this->crearAtencion($turno, $this->vendedor, [
            'inicio_at' => $inicio,
            'atencion_inicio_at' => null,
            'fin_at' => $fin,
            'motivo_cierre' => MotivosCierreAtencionTurnoPdv::NO_SE_PRESENTO,
        ]);

        $payload = $this->metricas([
            'desde' => '2026-09-07T00:00:00-06:00',
            'hasta' => '2026-09-08T00:00:00-06:00',
            'sucursal_id' => $this->sucursalA->id,
        ]);

        $this->assertSame(360, $payload['metricas'][MetricaTurnoOperacionPdvIds::T_06_ESPERA_INICIAL]['promedio_segundos']);
        $this->assertSame(0, $payload['metricas'][MetricaTurnoOperacionPdvIds::T_07_DURACION_ATENCION]['conteo']);
        $this->assertSame(100.0, $payload['metricas'][MetricaTurnoOperacionPdvIds::T_09_TASA_ABANDONO]['valor']);
        $this->assertSame(100.0, $payload['metricas'][MetricaTurnoOperacionPdvIds::T_10_TASA_NO_SE_PRESENTO]['valor']);
    }

    public function test_cm03_reatencion_dentro_de_ventana(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-07 12:00:00', 'America/Mexico_City'));

        $turno = $this->crearTurno($this->sucursalA, [
            'alta_at' => Carbon::parse('2026-09-07 09:00:00', 'America/Mexico_City'),
            'estado' => TurnoPdv::ESTADO_CERRADO,
            'reatencion_expira_at' => Carbon::parse('2026-09-07 12:30:00', 'America/Mexico_City'),
            'cerrado_at' => Carbon::parse('2026-09-07 12:00:00', 'America/Mexico_City'),
        ]);

        $atencion1 = $this->crearAtencion($turno, $this->vendedor, [
            'numero_secuencia' => 1,
            'inicio_at' => Carbon::parse('2026-09-07 10:00:00', 'America/Mexico_City'),
            'atencion_inicio_at' => Carbon::parse('2026-09-07 10:02:00', 'America/Mexico_City'),
            'fin_at' => Carbon::parse('2026-09-07 11:00:00', 'America/Mexico_City'),
            'motivo_cierre' => MotivosCierreAtencionTurnoPdv::VENTA,
        ]);

        $atencion2 = $this->crearAtencion($turno, $this->vendedor, [
            'numero_secuencia' => 2,
            'inicio_at' => Carbon::parse('2026-09-07 11:40:00', 'America/Mexico_City'),
            'atencion_inicio_at' => Carbon::parse('2026-09-07 11:41:00', 'America/Mexico_City'),
            'fin_at' => Carbon::parse('2026-09-07 11:50:00', 'America/Mexico_City'),
            'motivo_cierre' => MotivosCierreAtencionTurnoPdv::SIN_VENTA,
        ]);

        TurnoPdvEvento::query()->create([
            'turno_id' => $turno->id,
            'atencion_id' => $atencion2->id,
            'tipo_evento' => TurnoPdvEvento::TIPO_REATENCION,
            'ocurrido_at' => $atencion2->inicio_at,
        ]);

        $payload = $this->metricas([
            'desde' => '2026-09-07T00:00:00-06:00',
            'hasta' => '2026-09-08T00:00:00-06:00',
            'sucursal_id' => $this->sucursalA->id,
        ]);

        $this->assertSame(1, $payload['metricas'][MetricaTurnoOperacionPdvIds::T_11_TASA_REATENCION]['reatenciones']);
        $this->assertSame(2400, $payload['metricas'][MetricaTurnoOperacionPdvIds::T_11_TASA_REATENCION]['tiempo_hasta_reatencion_promedio_segundos']);
        $this->assertNotNull($atencion1->id);
        $this->assertNotNull($atencion2->id);
    }

    public function test_cm05_transferencia_no_cuenta_como_abandono(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-07 15:00:00', 'America/Mexico_City'));

        $otroVendedor = User::factory()->create();
        $otroVendedor->concederAccesoSucursal($this->sucursalA);

        $turno = $this->crearTurno($this->sucursalA, [
            'alta_at' => Carbon::parse('2026-09-07 10:00:00', 'America/Mexico_City'),
            'estado' => TurnoPdv::ESTADO_CERRADO,
            'cerrado_at' => Carbon::parse('2026-09-07 11:00:00', 'America/Mexico_City'),
        ]);

        $this->crearAtencion($turno, $this->vendedor, [
            'numero_secuencia' => 1,
            'inicio_at' => Carbon::parse('2026-09-07 10:05:00', 'America/Mexico_City'),
            'atencion_inicio_at' => Carbon::parse('2026-09-07 10:06:00', 'America/Mexico_City'),
            'fin_at' => Carbon::parse('2026-09-07 10:20:00', 'America/Mexico_City'),
            'motivo_cierre' => MotivosCierreAtencionTurnoPdv::TRANSFERENCIA,
            'es_transferencia' => true,
        ]);

        $this->crearAtencion($turno, $otroVendedor, [
            'numero_secuencia' => 2,
            'inicio_at' => Carbon::parse('2026-09-07 10:21:00', 'America/Mexico_City'),
            'atencion_inicio_at' => Carbon::parse('2026-09-07 10:22:00', 'America/Mexico_City'),
            'fin_at' => Carbon::parse('2026-09-07 10:50:00', 'America/Mexico_City'),
            'motivo_cierre' => MotivosCierreAtencionTurnoPdv::VENTA,
            'es_transferencia' => true,
        ]);

        TurnoPdvEvento::query()->create([
            'turno_id' => $turno->id,
            'tipo_evento' => TurnoPdvEvento::TIPO_TRANSFERIDO,
            'ocurrido_at' => Carbon::parse('2026-09-07 10:20:00', 'America/Mexico_City'),
        ]);

        $payload = $this->metricas([
            'desde' => '2026-09-07T00:00:00-06:00',
            'hasta' => '2026-09-08T00:00:00-06:00',
            'sucursal_id' => $this->sucursalA->id,
        ]);

        $this->assertSame(1, $payload['metricas'][MetricaTurnoOperacionPdvIds::T_12_TRANSFERENCIAS]['valor']);
        $this->assertSame(0.0, $payload['metricas'][MetricaTurnoOperacionPdvIds::T_09_TASA_ABANDONO]['valor']);
    }

    public function test_cm08_cierre_horario_ampliacion_y_altas(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-07 22:00:00', 'America/Mexico_City'));

        $fecha = '2026-09-07';
        $dia = SucursalDiaOperacionPdv::factory()->create([
            'sucursal_id' => $this->sucursalA->id,
            'fecha_operativa' => $fecha,
            'hora_cierre' => '19:00:00',
            'acepta_altas' => false,
            'ampliacion_hasta_at' => Carbon::parse('2026-09-07 21:00:00', 'America/Mexico_City'),
        ]);

        OperacionPdvEvento::query()->create([
            'sucursal_dia_id' => $dia->id,
            'sucursal_id' => $this->sucursalA->id,
            'tipo_evento' => OperacionPdvEvento::TIPO_CIERRE_HORARIO,
            'ocurrido_at' => Carbon::parse('2026-09-07 19:00:00', 'America/Mexico_City'),
            'idempotency_key' => 'test-cierre-'.$dia->id,
        ]);

        $this->crearTurno($this->sucursalA, [
            'alta_at' => Carbon::parse('2026-09-07 20:30:00', 'America/Mexico_City'),
        ]);

        $payload = $this->metricas([
            'desde' => '2026-09-07T00:00:00-06:00',
            'hasta' => '2026-09-08T00:00:00-06:00',
            'sucursal_id' => $this->sucursalA->id,
        ]);

        $this->assertSame(1, $payload['metricas'][MetricaTurnoOperacionPdvIds::O_09_CIERRES_HORARIO]['valor']);
        $this->assertSame(1, $payload['metricas'][MetricaTurnoOperacionPdvIds::O_10_AMPLIACIONES_HORARIO]['valor']);
        $this->assertSame(0, $payload['metricas'][MetricaTurnoOperacionPdvIds::O_11_ALTAS_DESPUES_CIERRE]['valor']);
    }

    public function test_cm09_pausa_abierta_al_corte(): void
    {
        $corte = Carbon::parse('2026-09-07 16:00:00', 'America/Mexico_City');

        $jornada = JornadaPdv::factory()->create([
            'user_id' => $this->vendedor->id,
            'sucursal_id' => $this->sucursalA->id,
            'apertura_at' => Carbon::parse('2026-09-07 08:00:00', 'America/Mexico_City'),
            'cierre_at' => Carbon::parse('2026-09-07 17:00:00', 'America/Mexico_City'),
        ]);

        IntervaloOperativoPdv::factory()->enPausa()->create([
            'jornada_id' => $jornada->id,
            'user_id' => $this->vendedor->id,
            'sucursal_id' => $this->sucursalA->id,
            'inicio_at' => Carbon::parse('2026-09-07 15:00:00', 'America/Mexico_City'),
            'fin_at' => null,
        ]);

        IntervaloOperativoPdv::factory()->enAtencion()->cerrado()->create([
            'jornada_id' => $jornada->id,
            'user_id' => $this->vendedor->id,
            'sucursal_id' => $this->sucursalA->id,
            'inicio_at' => Carbon::parse('2026-09-07 09:00:00', 'America/Mexico_City'),
            'fin_at' => Carbon::parse('2026-09-07 10:00:00', 'America/Mexico_City'),
        ]);

        $payload = $this->metricas([
            'desde' => '2026-09-07T00:00:00-06:00',
            'hasta' => '2026-09-08T00:00:00-06:00',
            'corte_reporte_at' => $corte->toIso8601String(),
            'sucursal_id' => $this->sucursalA->id,
        ]);

        $this->assertSame(3600, $payload['metricas'][MetricaTurnoOperacionPdvIds::O_03_TIEMPO_PAUSA]['valor']);
        $this->assertTrue($payload['metricas'][MetricaTurnoOperacionPdvIds::O_03_TIEMPO_PAUSA]['en_curso']);
        $this->assertSame(11.11, $payload['metricas'][MetricaTurnoOperacionPdvIds::O_06_OCUPACION]['valor']);
    }

    public function test_cm10_percentiles_con_muestra_insuficiente(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-07 18:00:00', 'America/Mexico_City'));

        for ($i = 0; $i < 12; $i++) {
            $alta = Carbon::parse('2026-09-07 09:00:00', 'America/Mexico_City')->addMinutes($i);
            $turno = $this->crearTurno($this->sucursalA, [
                'alta_at' => $alta,
                'estado' => TurnoPdv::ESTADO_ASIGNADO,
            ]);
            $this->crearAtencion($turno, $this->vendedor, [
                'inicio_at' => $alta->copy()->addMinutes(5 + $i),
                'atencion_inicio_at' => $alta->copy()->addMinutes(6 + $i),
                'fin_at' => $alta->copy()->addMinutes(20 + $i),
                'motivo_cierre' => MotivosCierreAtencionTurnoPdv::VENTA,
            ]);
        }

        $payload = $this->metricas([
            'desde' => '2026-09-07T00:00:00-06:00',
            'hasta' => '2026-09-08T00:00:00-06:00',
            'sucursal_id' => $this->sucursalA->id,
        ]);

        $this->assertSame(12, $payload['metricas'][MetricaTurnoOperacionPdvIds::T_15_PERCENTILES_ESPERA_COLA]['conteo']);
        $this->assertArrayNotHasKey('percentiles', $payload['metricas'][MetricaTurnoOperacionPdvIds::T_15_PERCENTILES_ESPERA_COLA]);
        $this->assertArrayHasKey('promedio_segundos', $payload['metricas'][MetricaTurnoOperacionPdvIds::T_15_PERCENTILES_ESPERA_COLA]);
    }

    public function test_aislamiento_por_sucursal_y_alcance_propio(): void
    {
        $this->crearTurno($this->sucursalA, ['alta_at' => now()->subHour()]);
        $this->crearTurno($this->sucursalA, ['alta_at' => now()->subMinutes(30)]);
        $this->crearTurno($this->sucursalB, ['alta_at' => now()->subHour()]);

        $global = $this->metricas(['sucursal_id' => $this->sucursalA->id]);
        $this->assertSame(2, $global['metricas'][MetricaTurnoOperacionPdvIds::T_01_ALTAS]['valor']);

        $piso = $this->metricas([
            'alcance' => MetricaTurnoOperacionPdvIds::ALCANCE_EQUIPO,
            'sucursal_id' => $this->sucursalA->id,
        ], $this->vendedor);
        $this->assertSame(2, $piso['metricas'][MetricaTurnoOperacionPdvIds::T_01_ALTAS]['valor']);

        $this->expectException(AuthorizationException::class);
        $this->metricas(['sucursal_id' => $this->sucursalB->id], $this->vendedor);
    }

    public function test_rechaza_metricas_personales_de_otra_persona_en_alcance_propio(): void
    {
        $otro = User::factory()->create();
        $otro->concederAccesoSucursal($this->sucursalA);

        $this->expectException(AuthorizationException::class);

        $this->metricas([
            'alcance' => MetricaTurnoOperacionPdvIds::ALCANCE_PROPIO,
            'user_id' => $otro->id,
            'sucursal_id' => $this->sucursalA->id,
        ], $this->vendedor);
    }

    public function test_filtro_franja_horaria(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-07 20:00:00', 'America/Mexico_City'));

        $this->crearTurno($this->sucursalA, [
            'alta_at' => Carbon::parse('2026-09-07 10:30:00', 'America/Mexico_City'),
        ]);
        $this->crearTurno($this->sucursalA, [
            'alta_at' => Carbon::parse('2026-09-07 14:30:00', 'America/Mexico_City'),
        ]);

        $payload = $this->metricas([
            'desde' => '2026-09-07T00:00:00-06:00',
            'hasta' => '2026-09-08T00:00:00-06:00',
            'franja_desde' => '10:00',
            'franja_hasta' => '12:00',
            'sucursal_id' => $this->sucursalA->id,
        ]);

        $this->assertSame(1, $payload['metricas'][MetricaTurnoOperacionPdvIds::T_01_ALTAS]['valor']);
    }

    public function test_volumen_representativo_sin_explosion_de_consultas(): void
    {
        DB::enableQueryLog();

        for ($i = 0; $i < 40; $i++) {
            $turno = $this->crearTurno($this->sucursalA, [
                'alta_at' => now()->subMinutes($i + 1),
                'estado' => TurnoPdv::ESTADO_ASIGNADO,
            ]);
            $this->crearAtencion($turno, $this->vendedor, [
                'inicio_at' => now()->subMinutes($i),
                'atencion_inicio_at' => now()->subMinutes($i)->addMinute(),
                'fin_at' => now()->subMinutes(max(1, $i - 5)),
                'motivo_cierre' => MotivosCierreAtencionTurnoPdv::VENTA,
            ]);
        }

        $this->metricas(['sucursal_id' => $this->sucursalA->id]);

        $consultas = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(250, $consultas);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    private function metricas(array $filtros = [], ?User $user = null): array
    {
        return app(CalcularMetricasReporteTurnoOperacionPdvService::class)
            ->ejecutar($user ?? $this->analista, $filtros);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function crearTurno(Sucursal $sucursal, array $overrides = []): TurnoPdv
    {
        return TurnoPdv::factory()->create(array_merge([
            'sucursal_id' => $sucursal->id,
            'servicio' => TurnoPdv::SERVICIO_VENTAS,
            'alta_at' => now()->subHour(),
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function crearAtencion(TurnoPdv $turno, User $vendedor, array $overrides = []): TurnoPdvAtencion
    {
        return TurnoPdvAtencion::factory()->create(array_merge([
            'turno_id' => $turno->id,
            'user_id' => $vendedor->id,
            'numero_secuencia' => 1,
            'inicio_at' => now()->subMinutes(30),
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

    private function seedHorarioCierre(): void
    {
        $config = new HorarioCierreOperacionPdvConfig;
        $config->persistir($config->configuracionInicialPlaneada());
        Cache::forget(HorarioCierreOperacionPdvConfig::CACHE_KEY);
    }
}
