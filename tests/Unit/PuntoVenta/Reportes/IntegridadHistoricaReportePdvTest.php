<?php

namespace Tests\Unit\PuntoVenta\Reportes;

use App\Models\ConfiguracionSistema;
use App\Models\PuntoVenta\IntervaloOperativoPdv;
use App\Models\PuntoVenta\JornadaPdv;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvBulto;
use App\Models\PuntoVenta\ResguardoPdvEntrega;
use App\Models\PuntoVenta\ResguardoPdvEvento;
use App\Models\PuntoVenta\TurnoPdv;
use App\Models\PuntoVenta\TurnoPdvAtencion;
use App\Models\PuntoVenta\TurnoPdvEvento;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\PuntoVenta\AlcancePdv;
use App\Services\PuntoVenta\Operacion\HorarioCierreOperacionPdvConfig;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Services\PuntoVenta\Reportes\Resguardos\CalcularMetricasReporteResguardoPdvService;
use App\Services\PuntoVenta\Reportes\TurnosOperacion\CalcularMetricasReporteTurnoOperacionPdvService;
use App\Services\PuntoVenta\Resguardos\PlazosCustodiaResguardoPdvConfig;
use App\Support\PuntoVenta\Operacion\TipoIntervaloOperativoPdv;
use App\Support\PuntoVenta\Reportes\MetricaResguardoPdvIds as R;
use App\Support\PuntoVenta\Reportes\MetricaTurnoOperacionPdvIds as T;
use App\Support\PuntoVenta\Turnos\MotivosCierreAtencionTurnoPdv;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class IntegridadHistoricaReportePdvTest extends TestCase
{
    use RefreshDatabase;

    private User $analista;

    private Sucursal $sucursal;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-09 20:00:00', 'America/Mexico_City'));
        Role::findOrCreate('Super Admin', 'web');
        ConfiguracionSistema::query()->updateOrCreate(['clave' => PuntoVentaModulo::CLAVE_FLAG], ['valor' => '1']);
        foreach (PuntoVentaModulo::permisosIniciales() as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }
        $horario = app(HorarioCierreOperacionPdvConfig::class);
        $horario->persistir($horario->configuracionInicialPlaneada());
        $plazos = app(PlazosCustodiaResguardoPdvConfig::class);
        $plazos->guardar(array_merge($plazos->configuracionInicialAprobada(), ['zona_horaria' => 'America/Mexico_City']));
        $this->sucursal = Sucursal::factory()->create();
        $this->analista = User::factory()->create();
        $this->analista->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_RESGUARDOS_VER,
            PuntoVentaModulo::PERMISO_TURNOS_VER,
            AlcancePdv::PERMISO_ALCANCE_GLOBAL,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @return iterable<string, array{TipoIntervaloOperativoPdv, string, string, int}> */
    public static function intervalos(): iterable
    {
        foreach ([
            'pausa' => [TipoIntervaloOperativoPdv::EnPausa, T::O_03_TIEMPO_PAUSA],
            'disponible' => [TipoIntervaloOperativoPdv::Disponible, T::O_04_TIEMPO_DISPONIBLE],
            'atencion' => [TipoIntervaloOperativoPdv::EnAtencion, T::O_05_TIEMPO_ATENCION_INTERVALOS],
        ] as $nombre => [$tipo, $metrica]) {
            yield $nombre.'_corte' => [$tipo, $metrica, '2026-09-08T00:00:00-06:00', 7200];
            yield $nombre.'_hasta' => [$tipo, $metrica, '2026-09-07T11:00:00-06:00', 3600];
        }
    }

    #[DataProvider('intervalos')]
    public function test_a2_acumulados_estables_tras_cierre_posterior(TipoIntervaloOperativoPdv $tipo, string $metrica, string $hasta, int $segundos): void
    {
        $jornada = $this->jornada();
        $intervalo = IntervaloOperativoPdv::factory()->create([
            'jornada_id' => $jornada->id,
            'tipo' => $tipo,
            'inicio_at' => $this->fecha('2026-09-07 10:00:00'),
            'fin_at' => null,
        ]);
        $filtros = $this->filtros(['hasta' => $hasta]);
        $antes = $this->turnos($filtros)['metricas'][$metrica];
        $this->assertSame($segundos, $antes['valor']);
        $this->assertTrue($antes['en_curso']);

        $intervalo->update(['fin_at' => $this->fecha('2026-09-07 17:00:00')]);
        $despues = $this->turnos($filtros)['metricas'][$metrica];
        $this->assertSame($antes, $despues);
    }

    public function test_a2_acumulado_incluye_solapamiento_y_excluye_futuro(): void
    {
        $jornada = $this->jornada();
        foreach ([['08:00:00', '11:00:00'], ['13:00:00', '14:00:00']] as [$inicio, $fin]) {
            IntervaloOperativoPdv::factory()->enPausa()->create([
                'jornada_id' => $jornada->id,
                'inicio_at' => $this->fecha('2026-09-07 '.$inicio),
                'fin_at' => $this->fecha('2026-09-07 '.$fin),
            ]);
            $payload = $this->turnos($this->filtros(['desde' => '2026-09-07T10:00:00-06:00']));
            $this->assertSame(3600, $payload['metricas'][T::O_03_TIEMPO_PAUSA]['valor']);
            $this->assertFalse($payload['metricas'][T::O_03_TIEMPO_PAUSA]['en_curso']);
        }
    }

    public function test_a2_jornada_abierta_al_corte_no_cambia_al_cerrar_despues(): void
    {
        $jornada = $this->jornada();
        $antes = $this->turnos($this->filtros())['metricas'][T::O_02_DURACION_JORNADA];
        $this->assertSame(1, $antes['en_curso']);
        $this->assertSame(0, $antes['conteo']);

        $jornada->update(['cierre_at' => $this->fecha('2026-09-07 17:00:00')]);
        $despues = $this->turnos($this->filtros())['metricas'][T::O_02_DURACION_JORNADA];
        $this->assertSame($antes, $despues);
    }

    public function test_a2_atencion_abierta_al_corte_no_incorpora_su_fin_futuro(): void
    {
        $turno = $this->turno();
        $atencion = TurnoPdvAtencion::factory()->create([
            'turno_id' => $turno->id,
            'user_id' => $this->analista->id,
            'inicio_at' => $this->fecha('2026-09-07 09:00:00'),
            'atencion_inicio_at' => $this->fecha('2026-09-07 10:00:00'),
            'fin_at' => null,
        ]);
        $antes = $this->turnos($this->filtros())['metricas'][T::T_07_DURACION_ATENCION];
        $this->assertSame(1, $antes['en_curso']);
        $this->assertSame(0, $antes['conteo']);
        $atencion->update([
            'fin_at' => $this->fecha('2026-09-07 14:00:00'),
            'motivo_cierre' => MotivosCierreAtencionTurnoPdv::VENTA,
        ]);
        $turno->update(['estado' => TurnoPdv::ESTADO_CERRADO, 'cerrado_at' => $atencion->fin_at]);
        $despues = $this->turnos($this->filtros())['metricas'];
        $this->assertSame($antes, $despues[T::T_07_DURACION_ATENCION]);
        $this->assertSame(0, $despues[T::T_03_TURNOS_CERRADOS]['valor']);
        $this->assertSame(0, $despues[T::T_08_CICLO_TOTAL]['conteo']);
    }

    public function test_a2_eventos_respetan_corte_inclusivo_hasta_exclusivo_y_zona(): void
    {
        foreach (['11:00:00', '12:00:00', '12:00:01'] as $hora) {
            $turno = $this->turno(['alta_at' => $this->fecha('2026-09-07 '.$hora)]);
            TurnoPdvEvento::query()->create([
                'turno_id' => $turno->id,
                'tipo_evento' => TurnoPdvEvento::TIPO_ASIGNADO,
                'ocurrido_at' => $turno->alta_at,
            ]);
        }
        // 18:00 UTC equivale a 12:00 en México; el corte incluye ese instante.
        $filtros = $this->filtros(['corte_reporte_at' => '2026-09-07T18:00:00Z']);
        $payload = $this->turnos($filtros)['metricas'];
        $this->assertSame(2, $payload[T::T_01_ALTAS]['valor']);
        $this->assertSame(2, $payload[T::T_02_ASIGNACIONES]['valor']);
        $filtros['hasta'] = '2026-09-07T12:00:00-06:00';
        $payload = $this->turnos($filtros)['metricas'];
        $this->assertSame(1, $payload[T::T_01_ALTAS]['valor']);
        $this->assertSame(1, $payload[T::T_02_ASIGNACIONES]['valor']);
    }

    public function test_a2_promedios_conservan_duracion_completa(): void
    {
        $jornada = $this->jornada();
        $jornada->update(['cierre_at' => $this->fecha('2026-09-07 11:00:00')]);
        $turno = $this->turno();
        $turno->update(['estado' => TurnoPdv::ESTADO_CERRADO, 'cerrado_at' => $this->fecha('2026-09-07 11:00:00')]);
        TurnoPdvAtencion::factory()->create([
            'turno_id' => $turno->id,
            'user_id' => $this->analista->id,
            'inicio_at' => $this->fecha('2026-09-07 09:00:00'),
            'atencion_inicio_at' => $this->fecha('2026-09-07 09:00:00'),
            'fin_at' => $this->fecha('2026-09-07 11:00:00'),
        ]);
        $payload = $this->turnos($this->filtros(['desde' => '2026-09-07T10:00:00-06:00']))['metricas'];
        $this->assertSame(7200, $payload[T::T_07_DURACION_ATENCION]['promedio_segundos']);
        $this->assertSame(10800, $payload[T::T_08_CICLO_TOTAL]['promedio_segundos']);
        // Jornadas siguen atribuyéndose por apertura, no por cierre ni solapamiento.
        $this->assertSame(0, $payload[T::O_02_DURACION_JORNADA]['conteo']);
        $payload = $this->turnos($this->filtros())['metricas'];
        $this->assertSame(10800, $payload[T::O_02_DURACION_JORNADA]['promedio_segundos']);
    }

    /** @return array<string, array{string, string, string}> */
    public static function salidasResguardo(): array
    {
        return [
            'entrega' => [ResguardoPdv::ESTADO_ENTREGADO, 'entrega_completada_at', ResguardoPdvEvento::TIPO_ENTREGA_TITULAR],
            'devolucion' => [ResguardoPdv::ESTADO_DEVUELTO, 'devolucion_confirmada_at', ResguardoPdvEvento::TIPO_DEVOLUCION_CONFIRMADA],
        ];
    }

    #[DataProvider('salidasResguardo')]
    public function test_a2_pendiente_reconstruccion_de_estado_resguardo(string $estado, string $campo, string $evento): void
    {
        $resguardo = $this->resguardo();
        $antes = $this->resguardos($this->filtros())['metricas'];
        $this->assertSame(1, $antes[R::R_02_EN_CUSTODIA]['valor']);
        $this->assertSame(1, $antes[R::R_05_PROXIMOS_VENCER]['valor']);
        $salida = $this->fecha('2026-09-07 14:00:00');
        $resguardo->update(['estado' => $estado, $campo => $salida]);
        ResguardoPdvEvento::query()->create([
            'resguardo_id' => $resguardo->id,
            'tipo_evento' => $evento,
            'estado_anterior' => ResguardoPdv::ESTADO_EN_CUSTODIA,
            'estado_nuevo' => $estado,
            'ocurrido_at' => $salida,
        ]);
        $despues = $this->resguardos($this->filtros())['metricas'];
        $this->assertSame(0, $despues[R::R_02_EN_CUSTODIA]['valor']);
        $this->assertSame(0, $despues[R::R_05_PROXIMOS_VENCER]['valor']);
        $this->assertSame(1, $despues[R::R_08_TIEMPO_CUSTODIA]['conteo']);
        $this->markTestIncomplete('A2: deriva histórica reproducida; requiere contrato de reconstrucción de estados. No valida integridad.');
    }

    public function test_a2_pendiente_evento_de_entrega_parcial_futuro(): void
    {
        $resguardo = $this->resguardo();
        $resguardo->update(['cantidad_bultos_esperada' => 2]);
        $bultos = ResguardoPdvBulto::factory()->count(2)->create([
            'resguardo_id' => $resguardo->id,
            'estado' => ResguardoPdvBulto::ESTADO_EN_CUSTODIA,
            'custodia_at' => $resguardo->custodia_confirmada_at,
        ]);
        $antes = $this->resguardos($this->filtros())['metricas'];
        $entregadoAt = $this->fecha('2026-09-07 14:00:00');
        $entrega = ResguardoPdvEntrega::query()->create([
            'resguardo_id' => $resguardo->id,
            'relacion' => ResguardoPdvEntrega::RELACION_TITULAR,
            'nombre_quien_retira' => 'Cliente de prueba',
            'entregado_por_id' => $this->analista->id,
            'entregado_at' => $entregadoAt,
        ]);
        $entrega->bultos()->attach($bultos[0]->id);
        $bultos[0]->update(['estado' => ResguardoPdvBulto::ESTADO_ENTREGADO, 'entrega_at' => $entregadoAt]);
        ResguardoPdvEvento::query()->create([
            'resguardo_id' => $resguardo->id,
            'tipo_evento' => ResguardoPdvEvento::TIPO_ENTREGA_PARCIAL,
            'estado_anterior' => ResguardoPdv::ESTADO_EN_CUSTODIA,
            'estado_nuevo' => ResguardoPdv::ESTADO_EN_CUSTODIA,
            'ocurrido_at' => $entregadoAt,
            'snapshot_json' => ['entrega_id' => $entrega->id, 'cantidad_entregada' => 1, 'parcial' => true],
        ]);
        $despues = $this->resguardos($this->filtros())['metricas'];
        $this->assertSame(1, $despues[R::R_02_EN_CUSTODIA]['valor']);
        $this->assertSame(0, $antes[R::R_10_TASA_INCIDENCIA]['activos']);
        $this->assertSame(1, $despues[R::R_10_TASA_INCIDENCIA]['activos']);
        $this->markTestIncomplete('A2: un evento posterior al corte altera activos; falta resolver temporalidad de resguardos de forma coherente.');
    }

    public function test_a2_primera_asignacion_posterior_al_corte_no_cambia_la_espera(): void
    {
        $turno = $this->turno();
        $antes = $this->turnos($this->filtros())['metricas'][T::T_05_ESPERA_COLA];
        TurnoPdvAtencion::factory()->create([
            'turno_id' => $turno->id,
            'user_id' => $this->analista->id,
            'inicio_at' => $this->fecha('2026-09-07 14:00:00'),
        ]);
        $despues = $this->turnos($this->filtros())['metricas'][T::T_05_ESPERA_COLA];
        $this->assertSame(0, $antes['conteo']);
        $this->assertSame($antes, $despues);
    }

    public function test_a2_inicio_de_atencion_futuro_no_altera_la_espera_al_corte(): void
    {
        $turno = $this->turno();
        $atencion = TurnoPdvAtencion::factory()->create([
            'turno_id' => $turno->id,
            'user_id' => $this->analista->id,
            'inicio_at' => $this->fecha('2026-09-07 09:00:00'),
            'atencion_inicio_at' => null,
            'fin_at' => null,
        ]);
        $antes = $this->turnos($this->filtros())['metricas'][T::T_06_ESPERA_INICIAL];
        $this->assertSame(10800, $antes['promedio_segundos']);
        $this->assertSame(1, $antes['en_curso']);
        $atencion->update(['atencion_inicio_at' => $this->fecha('2026-09-07 14:00:00')]);
        $despues = $this->turnos($this->filtros())['metricas'][T::T_06_ESPERA_INICIAL];
        $this->assertSame($antes, $despues);
    }

    public function test_a2_baja_y_no_presentacion_futuras_no_cambian_las_tasas(): void
    {
        $turno = $this->turno();
        $baja = $this->turno();
        $atencion = TurnoPdvAtencion::factory()->create([
            'turno_id' => $turno->id,
            'user_id' => $this->analista->id,
            'inicio_at' => $this->fecha('2026-09-07 09:00:00'),
            'motivo_cierre' => null,
            'fin_at' => null,
        ]);
        $antes = $this->turnos($this->filtros())['metricas'];
        $atencion->update([
            'motivo_cierre' => MotivosCierreAtencionTurnoPdv::NO_SE_PRESENTO,
            'fin_at' => $this->fecha('2026-09-07 14:00:00'),
        ]);
        $baja->update(['baja_at' => $this->fecha('2026-09-07 14:00:00')]);
        $despues = $this->turnos($this->filtros())['metricas'];
        $this->assertSame(0, $antes[T::T_09_TASA_ABANDONO]['abandonos']);
        $this->assertSame(0, $antes[T::T_10_TASA_NO_SE_PRESENTO]['no_se_presento']);
        $this->assertSame($antes[T::T_09_TASA_ABANDONO], $despues[T::T_09_TASA_ABANDONO]);
        $this->assertSame($antes[T::T_10_TASA_NO_SE_PRESENTO], $despues[T::T_10_TASA_NO_SE_PRESENTO]);
    }

    private function fecha(string $valor): Carbon
    {
        return Carbon::parse($valor, 'America/Mexico_City');
    }

    private function filtros(array $extra = []): array
    {
        return array_merge([
            'desde' => '2026-09-07T00:00:00-06:00',
            'hasta' => '2026-09-08T00:00:00-06:00',
            'corte_reporte_at' => '2026-09-07T12:00:00-06:00',
            'sucursal_id' => $this->sucursal->id,
        ], $extra);
    }

    private function jornada(): JornadaPdv
    {
        return JornadaPdv::factory()->create([
            'user_id' => $this->analista->id,
            'sucursal_id' => $this->sucursal->id,
            'apertura_at' => $this->fecha('2026-09-07 08:00:00'),
            'cierre_at' => null,
        ]);
    }

    private function turno(array $extra = []): TurnoPdv
    {
        return TurnoPdv::factory()->create(array_merge([
            'sucursal_id' => $this->sucursal->id,
            'alta_at' => $this->fecha('2026-09-07 08:00:00'),
        ], $extra));
    }

    private function resguardo(): ResguardoPdv
    {
        return ResguardoPdv::factory()->create([
            'sucursal_id' => $this->sucursal->id,
            'estado' => ResguardoPdv::ESTADO_EN_CUSTODIA,
            'salida_cedis_at' => $this->fecha('2026-08-18 09:00:00'),
            'recepcion_fisica_at' => $this->fecha('2026-08-19 09:00:00'),
            'custodia_confirmada_at' => $this->fecha('2026-08-20 09:00:00'),
        ]);
    }

    private function turnos(array $filtros): array
    {
        return app(CalcularMetricasReporteTurnoOperacionPdvService::class)->ejecutar($this->analista, $filtros);
    }

    private function resguardos(array $filtros): array
    {
        return app(CalcularMetricasReporteResguardoPdvService::class)->ejecutar($this->analista, $filtros);
    }
}
