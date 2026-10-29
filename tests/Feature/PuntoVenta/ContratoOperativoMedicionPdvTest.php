<?php

namespace Tests\Feature\PuntoVenta;

use App\Events\PuntoVenta\AtencionCerrada;
use App\Events\PuntoVenta\JornadaAbierta;
use App\Events\PuntoVenta\JornadaCerrada;
use App\Events\PuntoVenta\PausaFinalizada;
use App\Events\PuntoVenta\PausaIniciada;
use App\Events\PuntoVenta\TurnoAsignado;
use App\Jobs\PuntoVenta\Turnos\EjecutarMatchmakerTurnosPdvJob;
use App\Models\ConfiguracionSistema;
use App\Models\PuntoVenta\EsperaDisponibilidadPdv;
use App\Models\PuntoVenta\JornadaPdv;
use App\Models\PuntoVenta\SucursalDiaOperacionPdv;
use App\Models\PuntoVenta\TurnoPdv;
use App\Models\PuntoVenta\TurnoPdvEvento;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\PuntoVenta\AlcancePdv;
use App\Services\PuntoVenta\Operacion\AbrirJornadaPdvService;
use App\Services\PuntoVenta\Operacion\CerrarJornadaPdvService;
use App\Services\PuntoVenta\Operacion\ConsultaPersonaDisponiblePdvService;
use App\Services\PuntoVenta\Operacion\FinalizarPausaPdvService;
use App\Services\PuntoVenta\Operacion\IniciarPausaPdvService;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Services\PuntoVenta\Turnos\CerrarAtencionTurnoPdvService;
use App\Services\PuntoVenta\Turnos\MatchmakerTurnosPdvService;
use App\Services\PuntoVenta\Turnos\PlazosTurnosPdvConfig;
use App\Support\PuntoVenta\Operacion\EstadoJornadaPdv;
use App\Support\PuntoVenta\Turnos\MotivosCierreAtencionTurnoPdv;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ContratoOperativoMedicionPdvTest extends TestCase
{
    use RefreshDatabase;

    private Sucursal $sucursal;

    private User $empleado;

    private User $gerente;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-09-07 10:00:00', 'America/Mexico_City'));
        Role::findOrCreate('Super Admin', 'web');
        ConfiguracionSistema::query()->updateOrCreate(['clave' => PuntoVentaModulo::CLAVE_FLAG], ['valor' => '1']);
        foreach (PuntoVentaModulo::permisosIniciales() as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }
        $plazos = app(PlazosTurnosPdvConfig::class);
        $plazos->persistir($plazos->configuracionInicialAprobada());
        Event::fake([JornadaAbierta::class, JornadaCerrada::class, AtencionCerrada::class,
            TurnoAsignado::class, PausaIniciada::class, PausaFinalizada::class]);
        Queue::fake();
        $this->sucursal = Sucursal::factory()->create();
        SucursalDiaOperacionPdv::factory()->create([
            'sucursal_id' => $this->sucursal->id, 'fecha_operativa' => now()->toDateString(), 'acepta_altas' => true,
        ]);
        $this->empleado = $this->persona();
        $this->gerente = $this->persona();
        $this->gerente->givePermissionTo(PuntoVentaModulo::PERMISO_OPERACION_EQUIPO_GESTIONAR);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @return array<string, array{int}> */
    public static function enfriamientos(): array
    {
        return ['diez_segundos' => [10], 'sin_enfriamiento' => [0]];
    }

    #[DataProvider('enfriamientos')]
    public function test_enfriamiento_gerencial_corresponde_al_empleado_y_conserva_actor(int $segundos): void
    {
        $plazos = app(PlazosTurnosPdvConfig::class);
        $plazos->persistir(array_merge($plazos->configuracionInicialAprobada(), ['cooldown_segundos' => $segundos]));
        $turno = $this->asignarAlEmpleado();
        $this->cerrarComoGerente($turno);
        $jornadaEmpleado = $this->jornada($this->empleado);
        if ($segundos > 0) {
            $this->assertNotNull($jornadaEmpleado->disponible_desde);
            $this->assertTrue($jornadaEmpleado->disponible_desde->equalTo(now()->addSeconds($segundos)));
            Queue::assertPushed(EjecutarMatchmakerTurnosPdvJob::class, fn ($job) => $job->origenDisparador === 'cooldown'
                && $job->uniqueSuffix === 'cooldown:'.$this->empleado->id.':'.now()->addSeconds($segundos)->getTimestamp());
        } else {
            $this->assertNull($jornadaEmpleado->disponible_desde);
            Queue::assertNotPushed(EjecutarMatchmakerTurnosPdvJob::class);
        }
        $this->assertNull($this->jornada($this->gerente)->disponible_desde);
        $espera = EsperaDisponibilidadPdv::query()->sole();
        $this->assertSame(EsperaDisponibilidadPdv::ORIGEN_ATENCION_CERRADA, $espera->origen);
        $this->assertSame($segundos, $espera->cooldown_segundos);
        $this->assertSame($this->gerente->id, $espera->actor_id);
        $this->assertSame($this->empleado->id, $espera->user_id);
        $this->assertNull($espera->disponible_desde_anterior);
        if ($segundos > 0) {
            $this->assertTrue($espera->disponible_desde_nuevo->equalTo($jornadaEmpleado->disponible_desde));
        } else {
            $this->assertNull($espera->disponible_desde_nuevo);
        }
        $evento = TurnoPdvEvento::query()->where('tipo_evento', TurnoPdvEvento::TIPO_ATENCION_CERRADA)->sole();
        $this->assertSame($this->gerente->id, $evento->actor_id);
        $this->assertSame($this->empleado->id, $turno->atenciones()->sole()->user_id);
    }

    public function test_enfriamiento_gerencial_protege_asignaciones_de_ambos_usuarios_hasta_el_limite(): void
    {
        $this->cerrarComoGerente($this->asignarAlEmpleado());
        $consulta = app(ConsultaPersonaDisponiblePdvService::class);
        $this->assertFalse($consulta->esDisponible($this->empleado, $this->sucursal->id));
        $this->assertTrue($consulta->esDisponible($this->gerente, $this->sucursal->id));
        $primero = $this->nuevoTurno();
        $this->asignar();
        $this->assertSame($this->gerente->id, $primero->fresh()->atencionActual->user_id);

        $segundo = $this->nuevoTurno();
        $this->travel(9)->seconds();
        $this->asignar();
        $this->assertSame(TurnoPdv::ESTADO_EN_COLA, $segundo->fresh()->estado);
        $this->travel(1)->seconds();
        $this->asignar();
        $this->assertSame($this->empleado->id, $segundo->fresh()->atencionActual->user_id);
    }

    public function test_reserva_bloquea_asignacion_sin_inventar_inicio_efectivo(): void
    {
        $turno = $this->asignarAlEmpleado();
        $this->assertNull($turno->atencionActual->atencion_inicio_at);
        $this->assertFalse(app(ConsultaPersonaDisponiblePdvService::class)->esDisponible($this->empleado, $this->sucursal->id));
        $this->assertTrue(app(ConsultaPersonaDisponiblePdvService::class)->esDisponible($this->gerente, $this->sucursal->id));
    }

    public function test_pausa_real_bloquea_y_su_final_aplica_la_espera_configurada(): void
    {
        app(IniciarPausaPdvService::class)->ejecutar($this->empleado, now());
        $consulta = app(ConsultaPersonaDisponiblePdvService::class);
        $this->assertFalse($consulta->esDisponible($this->empleado, $this->sucursal->id));

        $fin = now()->copy()->addMinute();
        app(FinalizarPausaPdvService::class)->ejecutar($this->empleado, $fin);

        $this->assertFalse($consulta->esDisponible($this->empleado, $this->sucursal->id));
        $marca = $fin->copy()->addSeconds(10);
        $this->assertTrue($this->jornada($this->empleado)->disponible_desde->equalTo($marca));

        $espera = EsperaDisponibilidadPdv::query()->sole();
        $this->assertSame(EsperaDisponibilidadPdv::ORIGEN_PAUSA_FINALIZADA, $espera->origen);
        $this->assertSame(10, $espera->cooldown_segundos);
        $this->assertSame($this->empleado->id, $espera->actor_id);
        $this->assertSame($this->empleado->id, $espera->user_id);
        $this->assertNull($espera->disponible_desde_anterior);
        $this->assertTrue($espera->disponible_desde_nuevo->equalTo($marca));
        Queue::assertPushed(EjecutarMatchmakerTurnosPdvJob::class, fn ($job) => $job->origenDisparador === 'cooldown'
            && $job->uniqueSuffix === 'cooldown:'.$this->empleado->id.':'.$marca->getTimestamp());

        $this->travelTo($marca);
        $this->assertTrue($consulta->esDisponible($this->empleado, $this->sucursal->id));
    }

    public function test_fin_de_pausa_sin_cooldown_queda_elegible_y_registra_cero_segundos(): void
    {
        $plazos = app(PlazosTurnosPdvConfig::class);
        $plazos->persistir(array_merge($plazos->configuracionInicialAprobada(), ['cooldown_segundos' => 0]));

        app(IniciarPausaPdvService::class)->ejecutar($this->empleado, now());
        app(FinalizarPausaPdvService::class)->ejecutar($this->empleado, now());

        $this->assertNull($this->jornada($this->empleado)->disponible_desde);
        $this->assertTrue(app(ConsultaPersonaDisponiblePdvService::class)->esDisponible($this->empleado, $this->sucursal->id));
        $espera = EsperaDisponibilidadPdv::query()->sole();
        $this->assertSame(0, $espera->cooldown_segundos);
        $this->assertNull($espera->disponible_desde_nuevo);
        $this->assertSame(EsperaDisponibilidadPdv::ORIGEN_PAUSA_FINALIZADA, $espera->origen);
        Queue::assertNotPushed(EjecutarMatchmakerTurnosPdvJob::class, fn ($job) => $job->origenDisparador === 'cooldown');
    }

    public function test_pausa_con_reserva_abierta_se_rechaza(): void
    {
        $this->asignarAlEmpleado();
        $this->expectException(ValidationException::class);
        app(IniciarPausaPdvService::class)->ejecutar($this->empleado, now());
    }

    public function test_cierre_pendiente_no_habilita_disponibilidad(): void
    {
        $this->asignarAlEmpleado();
        $jornada = $this->jornada($this->empleado);
        app(CerrarJornadaPdvService::class)->ejecutar($this->empleado, $jornada->version, now());
        $this->assertSame(EstadoJornadaPdv::CerradaConAtencion, $jornada->fresh()->estado);
        $this->assertFalse(app(ConsultaPersonaDisponiblePdvService::class)->esDisponible($this->empleado, $this->sucursal->id));
    }

    public function test_reintento_de_cierre_no_duplica_evento_ni_extiende_enfriamiento(): void
    {
        $turno = $this->asignarAlEmpleado();
        $primero = $this->cerrarComoGerente($turno);
        $limite = $this->jornada($this->empleado)->disponible_desde;
        $this->travel(5)->seconds();
        $segundo = $this->cerrarComoGerente($turno);
        $this->assertSame($primero['evento']->id, $segundo['evento']->id);
        $this->assertSame(1, TurnoPdvEvento::query()->where('tipo_evento', TurnoPdvEvento::TIPO_ATENCION_CERRADA)->count());
        $this->assertSame(1, EsperaDisponibilidadPdv::query()->count());
        $this->assertNotNull($limite);
        $this->assertTrue($limite->equalTo($this->jornada($this->empleado)->disponible_desde));
    }

    private function persona(): User
    {
        $persona = User::factory()->create();
        $persona->concederAccesoSucursal($this->sucursal, esPrincipal: true);
        app(AlcancePdv::class)->establecerSucursalActiva($persona, $this->sucursal->id);
        $persona->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER, PuntoVentaModulo::PERMISO_TURNOS_ATENDER,
            PuntoVentaModulo::PERMISO_TURNOS_CERRAR_ATENCION, PuntoVentaModulo::PERMISO_OPERACION_JORNADA_ABRIR,
            PuntoVentaModulo::PERMISO_OPERACION_JORNADA_CERRAR, PuntoVentaModulo::PERMISO_OPERACION_PAUSA,
        ]);
        app(AbrirJornadaPdvService::class)->ejecutar($persona, now()->subHour());

        return $persona;
    }

    private function nuevoTurno(): TurnoPdv
    {
        return TurnoPdv::factory()->create([
            'sucursal_id' => $this->sucursal->id, 'estado' => TurnoPdv::ESTADO_EN_COLA, 'alta_at' => now(),
        ]);
    }

    private function asignarAlEmpleado(): TurnoPdv
    {
        $turno = $this->nuevoTurno();
        $this->asignar();
        $turno->refresh();
        $this->assertSame($this->empleado->id, $turno->atencionActual->user_id);

        return $turno;
    }

    private function asignar(): void
    {
        app(MatchmakerTurnosPdvService::class)->ejecutar($this->sucursal->id, 'test.a32');
    }

    private function cerrarComoGerente(TurnoPdv $turno): array
    {
        return app(CerrarAtencionTurnoPdvService::class)->ejecutar(
            $turno, $this->gerente, $turno->version, 'a32:cerrar:'.$turno->id,
            MotivosCierreAtencionTurnoPdv::NO_SE_PRESENTO, null, now(),
        );
    }

    private function jornada(User $persona): JornadaPdv
    {
        return JornadaPdv::query()->where('user_id', $persona->id)->sole();
    }
}
