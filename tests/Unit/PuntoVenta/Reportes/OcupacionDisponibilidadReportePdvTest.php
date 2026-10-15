<?php

namespace Tests\Unit\PuntoVenta\Reportes;

use App\Events\PuntoVenta\JornadaAbierta;
use App\Events\PuntoVenta\PausaFinalizada;
use App\Events\PuntoVenta\PausaIniciada;
use App\Models\ConfiguracionSistema;
use App\Models\PuntoVenta\IntervaloOperativoPdv;
use App\Models\PuntoVenta\JornadaPdv;
use App\Models\PuntoVenta\MotivoPausaPdv;
use App\Models\PuntoVenta\TurnoPdv;
use App\Models\PuntoVenta\TurnoPdvAtencion;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\PuntoVenta\AlcancePdv;
use App\Services\PuntoVenta\Operacion\AbrirJornadaPdvService;
use App\Services\PuntoVenta\Operacion\FinalizarPausaPdvService;
use App\Services\PuntoVenta\Operacion\HorarioCierreOperacionPdvConfig;
use App\Services\PuntoVenta\Operacion\IniciarPausaPdvService;
use App\Services\PuntoVenta\Operacion\ResolverEstadoVendedorOperacionPdvService;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Services\PuntoVenta\Reportes\TurnosOperacion\CalcularMetricasReporteTurnoOperacionPdvService;
use App\Services\PuntoVenta\Turnos\AplicarInicioAtencionTurnoPdvService;
use App\Services\PuntoVenta\Turnos\PlazosTurnosPdvConfig;
use App\Support\PuntoVenta\Operacion\EstadoJornadaPdv;
use App\Support\PuntoVenta\Operacion\EstadoVendedorOperacionPdv;
use App\Support\PuntoVenta\Operacion\TipoIntervaloOperativoPdv as Tipo;
use App\Support\PuntoVenta\Reportes\MetricaTurnoOperacionPdvIds as M;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OcupacionDisponibilidadReportePdvTest extends TestCase
{
    use RefreshDatabase;

    private User $analista;

    private Sucursal $sucursal;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-09-07 18:00:00', 'America/Mexico_City'));
        Role::findOrCreate('Super Admin', 'web');
        ConfiguracionSistema::query()->updateOrCreate(['clave' => PuntoVentaModulo::CLAVE_FLAG], ['valor' => '1']);
        foreach (PuntoVentaModulo::permisosIniciales() as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }
        $horario = app(HorarioCierreOperacionPdvConfig::class);
        $horario->persistir($horario->configuracionInicialPlaneada());
        $plazos = app(PlazosTurnosPdvConfig::class);
        $plazos->persistir($plazos->configuracionInicialAprobada());
        $this->sucursal = Sucursal::factory()->create();
        $this->analista = User::factory()->create();
        $this->analista->concederAccesoSucursal($this->sucursal, esPrincipal: true);
        $this->analista->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_TURNOS_VER,
            AlcancePdv::PERMISO_ALCANCE_GLOBAL,
        ]);
        Event::fake([JornadaAbierta::class, PausaIniciada::class, PausaFinalizada::class]);
        Queue::fake();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_sin_registros_no_inventa_proporciones(): void
    {
        $m = $this->metricas();
        foreach ([M::O_06_OCUPACION, M::O_07_DISPONIBILIDAD] as $id) {
            $this->assertSame(0, $m[$id]['tiempo_jornada_segundos']);
            $this->assertNull($m[$id]['valor']);
        }
        $this->assertSame(0, $m[M::O_05_TIEMPO_ATENCION_INTERVALOS]['valor']);
    }

    public function test_jornada_cerrada_con_intervalos_compatibles_no_exige_complementariedad(): void
    {
        $j = $this->jornada();
        $this->intervalo($j, Tipo::Disponible, '08:00', '10:00');
        $this->intervalo($j, Tipo::EnAtencion, '10:00', '11:00');
        $this->intervalo($j, Tipo::EnPausa, '11:00', '12:00');
        $m = $this->metricas();
        $this->assertSame(14400, $m[M::O_06_OCUPACION]['tiempo_jornada_segundos']);
        $this->assertSame(25.0, $m[M::O_06_OCUPACION]['valor']);
        $this->assertSame(50.0, $m[M::O_07_DISPONIBILIDAD]['valor']);
        $this->assertSame(3600, $m[M::O_03_TIEMPO_PAUSA]['valor']);
    }

    public function test_jornada_abierta_y_denominador_cero_conservan_nulo(): void
    {
        $j = $this->jornada(['cierre_at' => null, 'estado' => EstadoJornadaPdv::Abierta]);
        $this->intervalo($j, Tipo::Disponible, '08:00', null);
        $m = $this->metricas();
        $this->assertSame(14400, $m[M::O_04_TIEMPO_DISPONIBLE]['valor']);
        $this->assertTrue($m[M::O_04_TIEMPO_DISPONIBLE]['en_curso']);
        foreach ([M::O_06_OCUPACION, M::O_07_DISPONIBILIDAD] as $id) {
            $this->assertNull($m[$id]['valor']);
            $this->assertTrue($m[$id]['en_curso']);
        }
    }

    public function test_jornada_sin_duracion_positiva_no_divide_por_cero(): void
    {
        $this->jornada(['cierre_at' => $this->fecha('08:00')]);
        $m = $this->metricas();
        $this->assertSame(0, $m[M::O_02_DURACION_JORNADA]['conteo']);
        $this->assertNull($m[M::O_06_OCUPACION]['valor']);
        $this->assertNull($m[M::O_07_DISPONIBILIDAD]['valor']);
    }

    /** @return array<string, array{Tipo, string}> */
    public static function proporciones(): array
    {
        return [
            'ocupacion' => [Tipo::EnAtencion, M::O_06_OCUPACION],
            'disponibilidad' => [Tipo::Disponible, M::O_07_DISPONIBILIDAD],
        ];
    }

    #[DataProvider('proporciones')]
    public function test_pendiente_mezcla_de_jornadas_abiertas_y_cerradas_supera_cien(Tipo $tipo, string $id): void
    {
        $cerrada = $this->jornada(['cierre_at' => $this->fecha('09:00')]);
        $this->intervalo($cerrada, $tipo, '08:00', '09:00');
        $abierta = $this->jornada([
            'user_id' => User::factory()->create()->id,
            'estado' => EstadoJornadaPdv::Abierta,
            'cierre_at' => null,
        ]);
        $this->intervalo($abierta, $tipo, '08:00', null);
        $m = $this->metricas();
        $this->assertSame(3600, $m[$id]['tiempo_jornada_segundos']);
        $this->assertSame(500.0, $m[$id]['valor']);
        $this->markTestIncomplete('A3: 500 % reproducido; requiere autorizar una base temporal compatible, sin limitar artificialmente el porcentaje.');
    }

    public function test_pendiente_denominador_completo_frente_a_numerador_recortado(): void
    {
        $j = $this->jornada();
        $this->intervalo($j, Tipo::Disponible, '08:00', '12:00');
        $m = $this->metricas(['hasta' => $this->fecha('10:00')->toIso8601String()]);
        $this->assertSame(7200, $m[M::O_07_DISPONIBILIDAD]['tiempo_disponible_segundos']);
        $this->assertSame(14400, $m[M::O_07_DISPONIBILIDAD]['tiempo_jornada_segundos']);
        $this->assertSame(50.0, $m[M::O_07_DISPONIBILIDAD]['valor']);
        $this->markTestIncomplete('A3: jornada completa y acumulado recortado usan bases distintas; no se altera el promedio completo de O-02.');
    }

    public function test_pendiente_jornada_iniciada_antes_del_periodo_no_integra_denominador(): void
    {
        $j = $this->jornada([
            'apertura_at' => $this->fecha('2026-09-06 23:00'),
            'cierre_at' => $this->fecha('02:00'),
        ]);
        $this->intervalo($j, Tipo::Disponible, '2026-09-06 23:00', '02:00');
        $m = $this->metricas();
        $this->assertSame(7200, $m[M::O_04_TIEMPO_DISPONIBLE]['valor']);
        $this->assertSame(0, $m[M::O_07_DISPONIBILIDAD]['tiempo_jornada_segundos']);
        $this->assertNull($m[M::O_07_DISPONIBILIDAD]['valor']);
        $this->markTestIncomplete('A3: cohorte por apertura excluye una jornada que sí aporta intervalos; requiere redefinir la población de la proporción.');
    }

    public function test_pendiente_inicio_real_de_atencion_no_genera_intervalo_en_atencion(): void
    {
        $abierta = app(AbrirJornadaPdvService::class)->abrirParaUsuario(
            $this->analista->id, $this->sucursal->id, $this->fecha('08:00'), $this->analista->id,
        );
        $atencion = $this->atencion('09:00', null);
        $atencion->update(['atencion_inicio_at' => null]);
        $atencion = app(AplicarInicioAtencionTurnoPdvService::class)->ejecutar($atencion, $this->fecha('09:00'));
        $estado = app(ResolverEstadoVendedorOperacionPdvService::class)->resolver(
            $this->sucursal->id, $abierta['jornada'], $abierta['intervalo'], true, null, $this->fecha('12:00'),
        );
        $this->assertNotNull($atencion->atencion_inicio_at);
        $this->assertSame(EstadoVendedorOperacionPdv::Atendiendo, $estado);
        $this->assertSame(0, IntervaloOperativoPdv::query()->where('tipo', Tipo::EnAtencion->value)->count());
        $m = $this->metricas();
        $this->assertSame(1, $m[M::T_07_DURACION_ATENCION]['en_curso']);
        $this->assertSame(0, $m[M::O_05_TIEMPO_ATENCION_INTERVALOS]['valor']);
        $this->assertSame(14400, $m[M::O_04_TIEMPO_DISPONIBLE]['valor']);
        $this->markTestIncomplete('A3: atención real acreditada sin intervalo en_atencion; sustituir la fuente o instrumentar requiere autorización.');
    }

    public function test_pendiente_atenciones_consecutivas_coexisten_con_disponibilidad_continua(): void
    {
        $j = $this->jornada();
        $this->intervalo($j, Tipo::Disponible, '08:00', '12:00');
        $this->atencion('09:00', '10:00');
        $this->atencion('10:00', '11:00');
        $m = $this->metricas();
        $this->assertSame(2, $m[M::T_07_DURACION_ATENCION]['conteo']);
        $this->assertSame(3600, $m[M::T_07_DURACION_ATENCION]['promedio_segundos']);
        $this->assertSame(0.0, $m[M::O_06_OCUPACION]['valor']);
        $this->assertSame(100.0, $m[M::O_07_DISPONIBILIDAD]['valor']);
        $this->markTestIncomplete('A3: 0 % de ocupación y 100 % disponible con dos atenciones efectivas; etiquetas y fuentes requieren decisión.');
    }

    public function test_pendiente_solapamiento_atencion_pausa_no_es_tiempo_aditivo(): void
    {
        $j = $this->jornada();
        $pausa = $this->intervalo($j, Tipo::EnPausa, '09:00', '11:00');
        $atencion = $this->atencion('10:00', '12:00');
        $this->intervalo($j, Tipo::EnAtencion, '10:00', '12:00');
        $m = $this->metricas();
        $this->assertSame(7200, $m[M::O_03_TIEMPO_PAUSA]['valor']);
        $this->assertSame(7200, $m[M::O_05_TIEMPO_ATENCION_INTERVALOS]['valor']);
        $this->assertSame(3600, (int) $atencion->atencion_inicio_at->diffInSeconds($pausa->fin_at));
        $this->markTestIncomplete('A3: hay una hora simultánea; los acumulados independientes no pueden sumarse como actividad efectiva sin regla de solapamientos.');
    }

    public function test_pendiente_intervalos_superpuestos_del_mismo_usuario_superan_cien(): void
    {
        $j = $this->jornada();
        $this->intervalo($j, Tipo::EnAtencion, '08:00', '12:00');
        $this->intervalo($j, Tipo::EnAtencion, '09:00', '12:00');
        $m = $this->metricas();
        $this->assertSame(25200, $m[M::O_05_TIEMPO_ATENCION_INTERVALOS]['valor']);
        $this->assertSame(175.0, $m[M::O_06_OCUPACION]['valor']);
        $this->markTestIncomplete('A3: intervalos sintéticos cerrados superpuestos reproducen doble conteo; no se presume que ese dato exista en producción.');
    }

    public function test_pausa_registrada_por_servicio_conserva_motivo_y_tiempos(): void
    {
        app(AbrirJornadaPdvService::class)->abrirParaUsuario(
            $this->analista->id, $this->sucursal->id, $this->fecha('08:00'), $this->analista->id,
        );
        $motivo = MotivoPausaPdv::query()->create([
            'slug' => 'a3-prueba', 'nombre' => 'Motivo de prueba', 'activo' => true,
            'requiere_detalle' => false, 'orden' => 1,
        ]);
        $pausa = app(IniciarPausaPdvService::class)->iniciarParaUsuario(
            $this->analista->id, $this->sucursal->id, $this->fecha('09:00'), $this->analista->id,
            $motivo->id, null, true,
        );
        app(FinalizarPausaPdvService::class)->finalizarParaUsuario(
            $this->analista->id, $this->sucursal->id, $this->fecha('10:00'), $this->analista->id,
        );
        $registro = $pausa['intervalo']->fresh();
        $this->assertSame($motivo->id, $registro->motivo_pausa_id);
        $this->assertSame($this->analista->id, $registro->pausa_finalizada_por_id);
        $m = $this->metricas();
        $this->assertSame(3600, $m[M::O_03_TIEMPO_PAUSA]['valor']);
        $this->assertSame(10800, $m[M::O_04_TIEMPO_DISPONIBLE]['valor']);
    }

    public function test_pausa_abierta_al_corte_conserva_acumulado_despues_de_finalizar(): void
    {
        $j = $this->jornada(['cierre_at' => null, 'estado' => EstadoJornadaPdv::Abierta]);
        $p = $this->intervalo($j, Tipo::EnPausa, '10:00', null);
        $antes = $this->metricas();
        $this->assertSame(7200, $antes[M::O_03_TIEMPO_PAUSA]['valor']);
        $this->assertTrue($antes[M::O_03_TIEMPO_PAUSA]['en_curso']);
        $p->update(['fin_at' => $this->fecha('14:00')]);
        $j->update(['cierre_at' => $this->fecha('17:00')]);
        $despues = $this->metricas();
        foreach ([M::O_03_TIEMPO_PAUSA, M::O_06_OCUPACION, M::O_07_DISPONIBILIDAD] as $id) {
            $this->assertSame($antes[$id], $despues[$id]);
        }
    }

    public function test_intervalos_sin_interseccion_no_contribuyen(): void
    {
        $j = $this->jornada();
        $this->intervalo($j, Tipo::EnPausa, '05:00', '06:00');
        $this->intervalo($j, Tipo::EnPausa, '13:00', '14:00');
        $m = $this->metricas(['desde' => $this->fecha('08:00')->toIso8601String()]);
        $this->assertSame(0, $m[M::O_03_TIEMPO_PAUSA]['valor']);
        $this->assertFalse($m[M::O_03_TIEMPO_PAUSA]['en_curso']);
    }

    public function test_intervalo_que_cruza_medianoche_se_recorta_sin_cambiar_promedio(): void
    {
        $j = $this->jornada(['apertura_at' => $this->fecha('2026-09-06 22:00'), 'cierre_at' => $this->fecha('02:00')]);
        $this->intervalo($j, Tipo::EnPausa, '2026-09-06 23:00', '01:00');
        $m = $this->metricas();
        $this->assertSame(3600, $m[M::O_03_TIEMPO_PAUSA]['valor']);
        $m = $this->metricas(['desde' => $this->fecha('2026-09-06 00:00')->toIso8601String()]);
        $this->assertSame(14400, $m[M::O_02_DURACION_JORNADA]['promedio_segundos']);
    }

    public function test_filtros_usuario_y_sucursal_acotan_ambas_fuentes(): void
    {
        $otro = User::factory()->create();
        $otraSucursal = Sucursal::factory()->create();
        $j = $this->jornada();
        $this->intervalo($j, Tipo::Disponible, '08:00', '10:00');
        $j = $this->jornada(['user_id' => $otro->id]);
        $this->intervalo($j, Tipo::Disponible, '08:00', '12:00');
        $j = $this->jornada(['sucursal_id' => $otraSucursal->id]);
        $this->intervalo($j, Tipo::Disponible, '08:00', '12:00');
        $this->assertSame(50.0, $this->metricas(['user_id' => $this->analista->id])[M::O_07_DISPONIBILIDAD]['valor']);
        $this->assertSame(100.0, $this->metricas(['user_id' => $otro->id])[M::O_07_DISPONIBILIDAD]['valor']);
        $this->assertSame(75.0, $this->metricas()[M::O_07_DISPONIBILIDAD]['valor']);
        $this->assertSame(50.0, $this->metricas(['alcance' => M::ALCANCE_PROPIO])[M::O_07_DISPONIBILIDAD]['valor']);
    }

    public function test_pendiente_franja_atribuye_jornada_e_intervalo_por_inicios_distintos(): void
    {
        $j = $this->jornada();
        $this->intervalo($j, Tipo::Disponible, '10:00', '12:00');
        $m = $this->metricas(['franja_desde' => '10:00', 'franja_hasta' => '12:00']);
        $this->assertSame(7200, $m[M::O_04_TIEMPO_DISPONIBLE]['valor']);
        $this->assertSame(0, $m[M::O_07_DISPONIBILIDAD]['tiempo_jornada_segundos']);
        $this->assertNull($m[M::O_07_DISPONIBILIDAD]['valor']);
        $this->markTestIncomplete('A3: filtro por inicio de jornada e inicio de intervalo produce poblaciones distintas; requiere contrato por franja.');
    }

    public function test_pendiente_reactivacion_real_sobrescribe_historia_de_jornada_e_intervalo(): void
    {
        $j = $this->jornada();
        $i = $this->intervalo($j, Tipo::Disponible, '08:00', '12:00');
        $filtros = ['corte_reporte_at' => $this->fecha('13:00')->toIso8601String()];
        $antes = $this->metricas($filtros);
        app(AbrirJornadaPdvService::class)->reactivarParaUsuario(
            $this->analista->id, $this->sucursal->id, $this->fecha('14:00'), $this->analista->id,
        );
        $this->assertNull($j->fresh()->cierre_at);
        $this->assertTrue($i->fresh()->inicio_at->equalTo($this->fecha('14:00')));
        $despues = $this->metricas($filtros);
        $this->assertSame(14400, $antes[M::O_04_TIEMPO_DISPONIBLE]['valor']);
        $this->assertSame(0, $despues[M::O_04_TIEMPO_DISPONIBLE]['valor']);
        $this->assertSame(100.0, $antes[M::O_07_DISPONIBILIDAD]['valor']);
        $this->assertNull($despues[M::O_07_DISPONIBILIDAD]['valor']);
        $this->markTestIncomplete('A3: reactivación posterior altera filas previas; el recorte A2 no recupera los extremos sobrescritos. No modificar operación sin aprobación.');
    }

    private function fecha(string $valor): Carbon
    {
        return Carbon::parse(str_contains($valor, '-') ? $valor : '2026-09-07 '.$valor, 'America/Mexico_City');
    }

    private function jornada(array $extra = []): JornadaPdv
    {
        return JornadaPdv::factory()->create(array_merge([
            'user_id' => $this->analista->id, 'sucursal_id' => $this->sucursal->id,
            'estado' => EstadoJornadaPdv::Cerrada,
            'apertura_at' => $this->fecha('08:00'), 'cierre_at' => $this->fecha('12:00'),
        ], $extra));
    }

    private function intervalo(JornadaPdv $j, Tipo $tipo, string $inicio, ?string $fin): IntervaloOperativoPdv
    {
        return IntervaloOperativoPdv::factory()->create([
            'jornada_id' => $j->id, 'user_id' => $j->user_id, 'sucursal_id' => $j->sucursal_id,
            'tipo' => $tipo, 'inicio_at' => $this->fecha($inicio), 'fin_at' => $fin === null ? null : $this->fecha($fin),
        ]);
    }

    private function atencion(string $inicio, ?string $fin): TurnoPdvAtencion
    {
        $turno = TurnoPdv::factory()->create(['sucursal_id' => $this->sucursal->id, 'alta_at' => $this->fecha($inicio)]);

        return TurnoPdvAtencion::factory()->create([
            'turno_id' => $turno->id, 'user_id' => $this->analista->id,
            'inicio_at' => $this->fecha($inicio), 'atencion_inicio_at' => $this->fecha($inicio),
            'fin_at' => $fin === null ? null : $this->fecha($fin),
        ]);
    }

    private function metricas(array $extra = []): array
    {
        $payload = app(CalcularMetricasReporteTurnoOperacionPdvService::class)->ejecutar($this->analista, array_merge([
            'desde' => '2026-09-07T00:00:00-06:00', 'hasta' => '2026-09-08T00:00:00-06:00',
            'corte_reporte_at' => '2026-09-07T12:00:00-06:00', 'sucursal_id' => $this->sucursal->id,
        ], $extra));

        return $payload['metricas'];
    }
}
