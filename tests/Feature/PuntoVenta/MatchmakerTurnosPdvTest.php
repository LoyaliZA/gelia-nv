<?php

namespace Tests\Feature\PuntoVenta;

use App\Contracts\PuntoVenta\ConsultaPersonaDisponiblePdv;
use App\Events\PuntoVenta\TurnoAsignado;
use App\Events\PuntoVenta\TurnoReatencion;
use App\Jobs\PuntoVenta\Turnos\EjecutarMatchmakerTurnosPdvJob;
use App\Models\ConfiguracionSistema;
use App\Models\PuntoVenta\TurnoPdv;
use App\Models\PuntoVenta\TurnoPdvAtencion;
use App\Models\PuntoVenta\TurnoPdvEvento;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\PuntoVenta\AlcancePdv;
use App\Services\PuntoVenta\Operacion\AbrirJornadaPdvService;
use App\Services\PuntoVenta\Operacion\FinalizarPausaPdvService;
use App\Services\PuntoVenta\Operacion\IniciarPausaPdvService;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Services\PuntoVenta\Turnos\MatchmakerTurnosPdvService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MatchmakerTurnosPdvTest extends TestCase
{
    use RefreshDatabase;

    private Sucursal $sucursal;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('Super Admin', 'web');
        $this->withoutMiddleware([
            ValidateCsrfToken::class,
            PreventRequestForgery::class,
        ]);

        ConfiguracionSistema::query()->updateOrCreate(
            ['clave' => PuntoVentaModulo::CLAVE_FLAG],
            ['valor' => '1']
        );

        foreach (PuntoVentaModulo::permisosIniciales() as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }

        $this->sucursal = Sucursal::factory()->create(['nombre' => 'Sucursal Matchmaker']);
    }

    public function test_asigna_turno_prioritario_antes_que_normal_fifo(): void
    {
        Event::fake([TurnoAsignado::class, TurnoReatencion::class]);

        $ventas = User::factory()->create(['name' => 'Vendedor Uno']);
        $this->simularPersonasDisponibles([$ventas]);

        $normalAntiguo = $this->crearTurnoEnCola('Normal antiguo', altaAt: now()->subMinutes(10));
        $prioritario = $this->crearTurnoEnCola(
            'Prioritario',
            altaAt: now()->subMinutes(5),
            adultoMayor: true,
        );
        $normalReciente = $this->crearTurnoEnCola('Normal reciente', altaAt: now()->subMinutes(2));

        app(MatchmakerTurnosPdvService::class)->ejecutar($this->sucursal->id, 'test.matchmaker');

        $prioritario->refresh();
        $normalAntiguo->refresh();
        $normalReciente->refresh();

        $this->assertSame(TurnoPdv::ESTADO_ASIGNADO, $prioritario->estado);
        $this->assertSame(TurnoPdv::ESTADO_EN_COLA, $normalAntiguo->estado);
        $this->assertSame(TurnoPdv::ESTADO_EN_COLA, $normalReciente->estado);

        $atencion = TurnoPdvAtencion::query()->sole();
        $this->assertSame($prioritario->id, $atencion->turno_id);
        $this->assertSame($ventas->id, $atencion->user_id);

        Event::assertDispatched(TurnoAsignado::class, 1);
    }

    public function test_fifo_dentro_de_prioridad_normal(): void
    {
        Event::fake([TurnoAsignado::class]);

        $ventas = User::factory()->create();
        $this->simularPersonasDisponibles([$ventas]);

        $primero = $this->crearTurnoEnCola('Primero', altaAt: now()->subMinutes(5));
        $segundo = $this->crearTurnoEnCola('Segundo', altaAt: now()->subMinutes(2));

        app(MatchmakerTurnosPdvService::class)->ejecutar($this->sucursal->id, 'test.matchmaker');

        $primero->refresh();
        $segundo->refresh();

        $this->assertSame(TurnoPdv::ESTADO_ASIGNADO, $primero->estado);
        $this->assertSame(TurnoPdv::ESTADO_EN_COLA, $segundo->estado);
    }

    public function test_dos_workers_compiten_sin_doble_asignacion(): void
    {
        Event::fake([TurnoAsignado::class]);

        $ventas = User::factory()->create();
        $this->simularPersonasDisponibles([$ventas]);

        $turno = $this->crearTurnoEnCola('Competencia');

        $asignaciones = 0;

        DB::transaction(function () use (&$asignaciones): void {
            $asignaciones += app(MatchmakerTurnosPdvService::class)
                ->ejecutar($this->sucursal->id, 'worker.a');
        });

        DB::transaction(function () use (&$asignaciones): void {
            $asignaciones += app(MatchmakerTurnosPdvService::class)
                ->ejecutar($this->sucursal->id, 'worker.b');
        });

        $this->assertSame(1, $asignaciones);
        $this->assertSame(1, TurnoPdvAtencion::query()->count());

        $turno->refresh();
        $this->assertSame(TurnoPdv::ESTADO_ASIGNADO, $turno->estado);
        Event::assertDispatched(TurnoAsignado::class, 1);
    }

    public function test_asigna_turno_en_reatencion_y_emite_evento_reatencion(): void
    {
        Event::fake([TurnoReatencion::class, TurnoAsignado::class]);

        $ventas = User::factory()->create();
        $this->simularPersonasDisponibles([$ventas]);

        $turno = TurnoPdv::factory()->create([
            'sucursal_id' => $this->sucursal->id,
            'estado' => TurnoPdv::ESTADO_EN_REATENCION,
            'reatencion_expira_at' => now()->addHour(),
            'atencion_actual_id' => null,
        ]);

        TurnoPdvAtencion::factory()->create([
            'turno_id' => $turno->id,
            'user_id' => User::factory()->create()->id,
            'numero_secuencia' => 1,
            'inicio_at' => now()->subHour(),
            'fin_at' => now()->subMinutes(30),
        ]);

        app(MatchmakerTurnosPdvService::class)->ejecutar($this->sucursal->id, 'test.reatencion');

        $turno->refresh();
        $this->assertSame(TurnoPdv::ESTADO_ASIGNADO, $turno->estado);

        $evento = TurnoPdvEvento::query()
            ->where('turno_id', $turno->id)
            ->where('tipo_evento', TurnoPdvEvento::TIPO_REATENCION)
            ->first();

        $this->assertNotNull($evento);
        $this->assertSame(TurnoPdv::ESTADO_EN_REATENCION, $evento->estado_anterior);

        $atencion = TurnoPdvAtencion::query()
            ->where('turno_id', $turno->id)
            ->whereNull('fin_at')
            ->sole();
        $this->assertSame(2, $atencion->numero_secuencia);

        Event::assertDispatched(TurnoReatencion::class, 1);
        Event::assertNotDispatched(TurnoAsignado::class);
    }

    public function test_sin_persona_disponible_turno_permanece_en_cola(): void
    {
        Event::fake([TurnoAsignado::class]);

        $this->crearTurnoEnCola('Sin vendedor');

        $asignados = app(MatchmakerTurnosPdvService::class)
            ->ejecutar($this->sucursal->id, 'test.sin_disponible');

        $this->assertSame(0, $asignados);
        $this->assertSame(0, TurnoPdvAtencion::query()->count());
        $this->assertSame(
            TurnoPdv::ESTADO_EN_COLA,
            TurnoPdv::query()->value('estado')
        );

        Event::assertNotDispatched(TurnoAsignado::class);
    }

    public function test_reintento_idempotente_no_duplica_asignacion(): void
    {
        Event::fake([TurnoAsignado::class]);

        $ventas = User::factory()->create();
        $this->simularPersonasDisponibles([$ventas]);

        $this->crearTurnoEnCola('Idempotente');

        $matchmaker = app(MatchmakerTurnosPdvService::class);

        $this->assertSame(1, $matchmaker->ejecutar($this->sucursal->id, 'test.reintento'));
        $this->assertSame(0, $matchmaker->ejecutar($this->sucursal->id, 'test.reintento'));

        $this->assertSame(1, TurnoPdvAtencion::query()->count());
        $this->assertSame(
            1,
            TurnoPdvEvento::query()->where('tipo_evento', TurnoPdvEvento::TIPO_ASIGNADO)->count()
        );

        Event::assertDispatched(TurnoAsignado::class, 1);
    }

    public function test_job_ejecuta_matchmaker(): void
    {
        Event::fake([TurnoAsignado::class]);

        $ventas = User::factory()->create();
        $this->simularPersonasDisponibles([$ventas]);
        $this->crearTurnoEnCola('Via job');

        EjecutarMatchmakerTurnosPdvJob::dispatchSync(
            $this->sucursal->id,
            TurnoPdvEvento::TIPO_ALTA,
        );

        $this->assertSame(TurnoPdv::ESTADO_ASIGNADO, TurnoPdv::query()->value('estado'));
        Event::assertDispatched(TurnoAsignado::class, 1);
    }

    public function test_abrir_jornada_asigna_turno_en_cola_con_disponibilidad_real(): void
    {
        Event::fake([TurnoAsignado::class]);

        $ventas = $this->crearVendedorOperacion('Vendedor Jornada');
        $turno = $this->crearTurnoEnCola('Esperando jornada');

        app(AbrirJornadaPdvService::class)->ejecutar($ventas, now());

        $turno->refresh();
        $this->assertSame(TurnoPdv::ESTADO_ASIGNADO, $turno->estado);
        $this->assertSame($ventas->id, TurnoPdvAtencion::query()->value('user_id'));
        Event::assertDispatched(TurnoAsignado::class, 1);
    }

    public function test_finalizar_pausa_asigna_turno_en_cola_con_disponibilidad_real(): void
    {
        Event::fake([TurnoAsignado::class]);

        $ventas = $this->crearVendedorOperacion('Vendedor Pausa');
        app(AbrirJornadaPdvService::class)->ejecutar($ventas, now());
        app(IniciarPausaPdvService::class)->ejecutar($ventas, now());

        $turno = $this->crearTurnoEnCola('Esperando fin de pausa');

        app(FinalizarPausaPdvService::class)->ejecutar($ventas, now());

        $turno->refresh();
        $this->assertSame(TurnoPdv::ESTADO_ASIGNADO, $turno->estado);
        Event::assertDispatched(TurnoAsignado::class, 1);
    }

    public function test_abrir_jornada_dispara_job_matchmaker(): void
    {
        Queue::fake([EjecutarMatchmakerTurnosPdvJob::class]);

        $ventas = $this->crearVendedorOperacion('Vendedor Job');

        app(AbrirJornadaPdvService::class)->ejecutar($ventas, now());

        Queue::assertPushed(EjecutarMatchmakerTurnosPdvJob::class, function (EjecutarMatchmakerTurnosPdvJob $job): bool {
            return $job->sucursalId === $this->sucursal->id
                && $job->origenDisparador === 'jornada.abierta';
        });
    }

    public function test_finalizar_pausa_dispara_job_matchmaker(): void
    {
        $ventas = $this->crearVendedorOperacion('Vendedor Pausa Job');
        app(AbrirJornadaPdvService::class)->ejecutar($ventas, now());
        app(IniciarPausaPdvService::class)->ejecutar($ventas, now());

        Queue::fake([EjecutarMatchmakerTurnosPdvJob::class]);

        app(FinalizarPausaPdvService::class)->ejecutar($ventas, now());

        Queue::assertPushed(EjecutarMatchmakerTurnosPdvJob::class, function (EjecutarMatchmakerTurnosPdvJob $job): bool {
            return $job->sucursalId === $this->sucursal->id
                && $job->origenDisparador === 'pausa.finalizada';
        });
    }

    public function test_get_estado_no_dispara_matchmaker(): void
    {
        Queue::fake([EjecutarMatchmakerTurnosPdvJob::class]);

        $ventas = $this->crearVendedorOperacion('Vendedor Lectura');
        $this->crearTurnoEnCola('Solo lectura');

        $this->actingAs($ventas)->getJson(route('punto_venta.operacion.estado'))->assertOk();

        Queue::assertNotPushed(EjecutarMatchmakerTurnosPdvJob::class);
        $this->assertSame(TurnoPdv::ESTADO_EN_COLA, TurnoPdv::query()->value('estado'));
    }

    /**
     * @param  list<User>  $personas
     */
    private function simularPersonasDisponibles(array $personas): void
    {
        $this->app->instance(
            ConsultaPersonaDisponiblePdv::class,
            new class(Collection::make($personas)) implements ConsultaPersonaDisponiblePdv
            {
                public function __construct(private readonly Collection $personas) {}

                public function primeraDisponible(int $sucursalId, string $servicio): ?User
                {
                    foreach ($this->personas->sortBy('id') as $persona) {
                        if (! $persona instanceof User) {
                            continue;
                        }

                        $ocupada = TurnoPdvAtencion::query()
                            ->where('user_id', $persona->id)
                            ->whereNull('fin_at')
                            ->exists();

                        if (! $ocupada) {
                            return $persona;
                        }
                    }

                    return null;
                }

                public function esDisponible(User $user, int $sucursalId, bool $paraAltaNueva = false): bool
                {
                    return $this->primeraDisponible($sucursalId, 'ventas')?->is($user) ?? false;
                }
            }
        );
    }

    private function crearTurnoEnCola(
        string $nombre,
        ?\DateTimeInterface $altaAt = null,
        bool $adultoMayor = false,
    ): TurnoPdv {
        return TurnoPdv::factory()->create([
            'sucursal_id' => $this->sucursal->id,
            'estado' => TurnoPdv::ESTADO_EN_COLA,
            'snapshot_nombre_llamado' => $nombre,
            'prioridad_adulto_mayor' => $adultoMayor,
            'alta_at' => $altaAt ?? now(),
            'atencion_actual_id' => null,
        ]);
    }

    private function crearVendedorOperacion(string $nombre): User
    {
        $usuario = User::factory()->create(['name' => $nombre]);
        $usuario->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_TURNOS_VER,
            PuntoVentaModulo::PERMISO_TURNOS_CERRAR_ATENCION,
            PuntoVentaModulo::PERMISO_OPERACION_JORNADA_ABRIR,
            PuntoVentaModulo::PERMISO_OPERACION_JORNADA_CERRAR,
            PuntoVentaModulo::PERMISO_OPERACION_PAUSA,
        ]);
        $usuario->concederAccesoSucursal($this->sucursal, esPrincipal: true);
        app(AlcancePdv::class)->establecerSucursalActiva($usuario, $this->sucursal->id);

        return $usuario;
    }
}
