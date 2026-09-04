<?php

namespace Tests\Feature\PuntoVenta;

use App\Contracts\PuntoVenta\ConsultaPersonaDisponiblePdv;
use App\Events\PuntoVenta\AtencionCerrada;
use App\Events\PuntoVenta\AtencionProrroga;
use App\Events\PuntoVenta\TurnoTransferido;
use App\Events\PuntoVenta\TurnoVentanaReatencionVencida;
use App\Jobs\PuntoVenta\Turnos\AlertaProrrogaAtencionTurnoPdvJob;
use App\Jobs\PuntoVenta\Turnos\EjecutarMatchmakerTurnosPdvJob;
use App\Jobs\PuntoVenta\Turnos\VencerVentanaReatencionTurnoPdvJob;
use App\Models\ConfiguracionSistema;
use App\Models\PuntoVenta\TurnoPdv;
use App\Models\PuntoVenta\TurnoPdvAtencion;
use App\Models\PuntoVenta\TurnoPdvEvento;
use App\Models\PuntoVenta\TurnoPdvProrroga;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\PuntoVenta\AlcancePdv;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Services\PuntoVenta\Turnos\MatchmakerTurnosPdvService;
use App\Services\PuntoVenta\Turnos\PlazosTurnosPdvConfig;
use App\Support\PuntoVenta\Turnos\MotivosBajaColaTurnoPdv;
use App\Support\PuntoVenta\Turnos\MotivosCierreAtencionTurnoPdv;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CicloAtencionTurnosPdvTest extends TestCase
{
    use RefreshDatabase;

    private Sucursal $sucursal;

    private User $vendedor;

    private User $recepcion;

    private User $gerencia;

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
        $this->seedPlazos();

        $this->sucursal = Sucursal::factory()->create(['nombre' => 'Sucursal Ciclo']);
        $this->vendedor = User::factory()->create(['name' => 'Vendedor Ciclo']);
        $this->recepcion = User::factory()->create(['name' => 'Recepcion Ciclo']);
        $this->gerencia = User::factory()->create(['name' => 'Gerencia Ciclo']);

        foreach ([$this->vendedor, $this->recepcion, $this->gerencia] as $usuario) {
            $usuario->concederAccesoSucursal($this->sucursal, esPrincipal: true);
            app(AlcancePdv::class)->establecerSucursalActiva($usuario, $this->sucursal->id);
        }

        $this->vendedor->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_TURNOS_CERRAR_ATENCION,
        ]);
        $this->recepcion->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_TURNOS_BAJA_COLA,
        ]);
        $this->gerencia->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_TURNOS_TRANSFERIR,
        ]);
    }

    public function test_camino_feliz_asignado_inicia_cierra_y_queda_en_reatencion(): void
    {
        Event::fake([AtencionCerrada::class]);
        Queue::fake();

        $contexto = $this->crearTurnoAsignado();

        $this->actingAs($this->vendedor)->postJson(
            route('punto_venta.turnos.iniciar_atencion', $contexto['turno']),
            ['version' => $contexto['turno']->version],
        )->assertOk()
            ->assertJsonPath('atencion.atencion_inicio_at', fn ($valor) => $valor !== null);

        $contexto['turno']->refresh();
        $contexto['atencion']->refresh();

        $this->actingAs($this->vendedor)->postJson(
            route('punto_venta.turnos.cerrar_atencion', $contexto['turno']),
            [
                'version' => $contexto['turno']->version,
                'idempotency_key' => 'pdv:cerrar:1',
                'motivo' => MotivosCierreAtencionTurnoPdv::VENTA,
            ],
        )->assertOk()
            ->assertJsonPath('turno.estado', TurnoPdv::ESTADO_EN_REATENCION);

        $contexto['turno']->refresh();
        $this->assertNull($contexto['turno']->atencion_actual_id);
        $this->assertNotNull($contexto['turno']->reatencion_expira_at);

        $evento = TurnoPdvEvento::query()
            ->where('turno_id', $contexto['turno']->id)
            ->where('tipo_evento', TurnoPdvEvento::TIPO_ATENCION_CERRADA)
            ->sole();
        $this->assertSame(TurnoPdv::ESTADO_ASIGNADO, $evento->estado_anterior);
        $this->assertSame(TurnoPdv::ESTADO_EN_REATENCION, $evento->estado_nuevo);

        Event::assertDispatched(AtencionCerrada::class, 1);
        Queue::assertPushed(VencerVentanaReatencionTurnoPdvJob::class);
    }

    public function test_cierre_manual_no_se_presento(): void
    {
        $contexto = $this->crearTurnoAsignado();

        $this->actingAs($this->vendedor)->postJson(
            route('punto_venta.turnos.cerrar_atencion', $contexto['turno']),
            [
                'version' => $contexto['turno']->version,
                'idempotency_key' => 'pdv:cerrar:no-presento',
                'motivo' => MotivosCierreAtencionTurnoPdv::NO_SE_PRESENTO,
            ],
        )->assertOk();

        $contexto['atencion']->refresh();
        $this->assertSame(MotivosCierreAtencionTurnoPdv::NO_SE_PRESENTO, $contexto['atencion']->motivo_cierre);
    }

    public function test_baja_cola_en_en_cola_sin_evento_de_ventas(): void
    {
        Event::fake([AtencionCerrada::class, AtencionProrroga::class]);

        $turno = TurnoPdv::factory()->create([
            'sucursal_id' => $this->sucursal->id,
            'estado' => TurnoPdv::ESTADO_EN_COLA,
            'atencion_actual_id' => null,
        ]);

        $this->actingAs($this->recepcion)->postJson(
            route('punto_venta.turnos.baja_cola', $turno),
            [
                'version' => $turno->version,
                'idempotency_key' => 'pdv:baja:1',
                'motivo' => MotivosBajaColaTurnoPdv::SE_FUE,
            ],
        )->assertOk()
            ->assertJsonPath('turno.estado', TurnoPdv::ESTADO_CERRADO);

        $this->assertSame(
            1,
            TurnoPdvEvento::query()->where('tipo_evento', TurnoPdvEvento::TIPO_BAJA_COLA)->count()
        );

        Event::assertNotDispatched(AtencionCerrada::class);
        Event::assertNotDispatched(AtencionProrroga::class);
    }

    public function test_rechaza_transiciones_invalidas(): void
    {
        $enCola = TurnoPdv::factory()->create([
            'sucursal_id' => $this->sucursal->id,
            'estado' => TurnoPdv::ESTADO_EN_COLA,
        ]);

        $this->actingAs($this->vendedor)->postJson(
            route('punto_venta.turnos.iniciar_atencion', $enCola),
            ['version' => $enCola->version],
        )->assertUnprocessable();

        $this->actingAs($this->vendedor)->postJson(
            route('punto_venta.turnos.cerrar_atencion', $enCola),
            [
                'version' => $enCola->version,
                'idempotency_key' => 'pdv:invalido:cerrar-cola',
                'motivo' => MotivosCierreAtencionTurnoPdv::VENTA,
            ],
        )->assertUnprocessable();

        $this->actingAs($this->recepcion)->postJson(
            route('punto_venta.turnos.baja_cola', $this->crearTurnoAsignado()['turno']),
            [
                'version' => 1,
                'idempotency_key' => 'pdv:invalido:baja-asignado',
                'motivo' => MotivosBajaColaTurnoPdv::SE_FUE,
            ],
        )->assertUnprocessable();
    }

    public function test_transferencia_rechaza_destino_no_disponible(): void
    {
        $contexto = $this->crearTurnoAsignado();
        $ocupado = User::factory()->create();
        $ocupado->concederAccesoSucursal($this->sucursal, esPrincipal: true);

        TurnoPdvAtencion::factory()->create([
            'turno_id' => TurnoPdv::factory()->create(['sucursal_id' => $this->sucursal->id])->id,
            'user_id' => $ocupado->id,
            'inicio_at' => now(),
            'fin_at' => null,
        ]);

        $this->actingAs($this->gerencia)->postJson(
            route('punto_venta.turnos.transferir', $contexto['turno']),
            [
                'version' => $contexto['turno']->version,
                'idempotency_key' => 'pdv:transfer:ocupado',
                'destino_user_id' => $ocupado->id,
            ],
        )->assertUnprocessable();
    }

    public function test_transferencia_crea_nueva_atencion_y_emite_evento(): void
    {
        Event::fake([TurnoTransferido::class]);

        $contexto = $this->crearTurnoAsignado();
        $destino = User::factory()->create();
        $destino->concederAccesoSucursal($this->sucursal, esPrincipal: true);

        $this->actingAs($this->gerencia)->postJson(
            route('punto_venta.turnos.transferir', $contexto['turno']),
            [
                'version' => $contexto['turno']->version,
                'idempotency_key' => 'pdv:transfer:ok',
                'destino_user_id' => $destino->id,
            ],
        )->assertOk()
            ->assertJsonPath('turno.estado', TurnoPdv::ESTADO_ASIGNADO)
            ->assertJsonPath('atencion_nueva.user_id', $destino->id);

        $this->assertSame(2, TurnoPdvAtencion::query()->where('turno_id', $contexto['turno']->id)->count());
        $this->assertSame(
            1,
            TurnoPdvEvento::query()->where('tipo_evento', TurnoPdvEvento::TIPO_TRANSFERIDO)->count()
        );

        Event::assertDispatched(TurnoTransferido::class, 1);
    }

    public function test_reatencion_dentro_de_ventana_y_fuera_por_job(): void
    {
        Event::fake([TurnoVentanaReatencionVencida::class]);

        $otroVendedor = User::factory()->create();
        $this->simularPersonasDisponibles([$otroVendedor]);

        $turno = TurnoPdv::factory()->create([
            'sucursal_id' => $this->sucursal->id,
            'estado' => TurnoPdv::ESTADO_EN_REATENCION,
            'reatencion_expira_at' => now()->addHour(),
            'atencion_actual_id' => null,
        ]);

        TurnoPdvAtencion::factory()->create([
            'turno_id' => $turno->id,
            'user_id' => $this->vendedor->id,
            'numero_secuencia' => 1,
            'inicio_at' => now()->subHours(2),
            'fin_at' => now()->subHour(),
        ]);

        app(MatchmakerTurnosPdvService::class)->ejecutar($this->sucursal->id, 'test.reatencion');

        $turno->refresh();
        $this->assertSame(TurnoPdv::ESTADO_ASIGNADO, $turno->estado);
        $this->assertSame($turno->folio, $turno->fresh()->folio);

        $turno->update([
            'estado' => TurnoPdv::ESTADO_EN_REATENCION,
            'atencion_actual_id' => null,
            'reatencion_expira_at' => now()->subMinute(),
        ]);

        VencerVentanaReatencionTurnoPdvJob::dispatchSync($turno->id);

        $turno->refresh();
        $this->assertSame(TurnoPdv::ESTADO_CERRADO, $turno->estado);
        $this->assertSame(
            1,
            TurnoPdvEvento::query()
                ->where('tipo_evento', TurnoPdvEvento::TIPO_VENTANA_REATENCION_VENCIDA)
                ->count()
        );

        Event::assertDispatched(TurnoVentanaReatencionVencida::class, 1);
    }

    public function test_job_prorroga_registra_evento_tras_iniciar_atencion(): void
    {
        Event::fake([AtencionProrroga::class]);

        $contexto = $this->crearTurnoAsignado(atencionInicio: now()->subMinutes(21));

        AlertaProrrogaAtencionTurnoPdvJob::dispatchSync($contexto['atencion']->id);

        $this->assertSame(1, TurnoPdvProrroga::query()->count());
        $this->assertSame(
            1,
            TurnoPdvEvento::query()->where('tipo_evento', TurnoPdvEvento::TIPO_PRORROGA)->count()
        );

        Event::assertDispatched(AtencionProrroga::class, 1);
    }

    public function test_cierre_dispara_matchmaker(): void
    {
        Queue::fake([EjecutarMatchmakerTurnosPdvJob::class]);

        $contexto = $this->crearTurnoAsignado();

        $this->actingAs($this->vendedor)->postJson(
            route('punto_venta.turnos.cerrar_atencion', $contexto['turno']),
            [
                'version' => $contexto['turno']->version,
                'idempotency_key' => 'pdv:cerrar:matchmaker',
                'motivo' => MotivosCierreAtencionTurnoPdv::SIN_VENTA,
            ],
        )->assertOk();

        Queue::assertPushed(EjecutarMatchmakerTurnosPdvJob::class);
    }

    public function test_version_obsoleta_rechazada(): void
    {
        $contexto = $this->crearTurnoAsignado();

        $this->actingAs($this->vendedor)->postJson(
            route('punto_venta.turnos.cerrar_atencion', $contexto['turno']),
            [
                'version' => $contexto['turno']->version - 1,
                'idempotency_key' => 'pdv:version:obsoleta',
                'motivo' => MotivosCierreAtencionTurnoPdv::VENTA,
            ],
        )->assertUnprocessable()
            ->assertJsonValidationErrors(['version']);
    }

    public function test_cierre_idempotente_no_duplica_evento(): void
    {
        $contexto = $this->crearTurnoAsignado();

        $payload = [
            'version' => $contexto['turno']->version,
            'idempotency_key' => 'pdv:cerrar:idempotente',
            'motivo' => MotivosCierreAtencionTurnoPdv::VENTA,
        ];

        $this->actingAs($this->vendedor)->postJson(
            route('punto_venta.turnos.cerrar_atencion', $contexto['turno']),
            $payload,
        )->assertOk();

        $contexto['turno']->refresh();

        $this->actingAs($this->vendedor)->postJson(
            route('punto_venta.turnos.cerrar_atencion', $contexto['turno']),
            $payload,
        )->assertOk();

        $this->assertSame(
            1,
            TurnoPdvEvento::query()->where('tipo_evento', TurnoPdvEvento::TIPO_ATENCION_CERRADA)->count()
        );
    }

    public function test_solo_quien_atiende_puede_iniciar_o_cerrar(): void
    {
        $contexto = $this->crearTurnoAsignado();
        $otro = User::factory()->create();
        $otro->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_TURNOS_CERRAR_ATENCION,
        ]);
        $otro->concederAccesoSucursal($this->sucursal, esPrincipal: true);
        app(AlcancePdv::class)->establecerSucursalActiva($otro, $this->sucursal->id);

        $this->actingAs($otro)->postJson(
            route('punto_venta.turnos.iniciar_atencion', $contexto['turno']),
            ['version' => $contexto['turno']->version],
        )->assertUnprocessable();

        $this->actingAs($otro)->postJson(
            route('punto_venta.turnos.cerrar_atencion', $contexto['turno']),
            [
                'version' => $contexto['turno']->version,
                'idempotency_key' => 'pdv:otro-vendedor',
                'motivo' => MotivosCierreAtencionTurnoPdv::VENTA,
            ],
        )->assertUnprocessable();
    }

    /**
     * @return array{turno: TurnoPdv, atencion: TurnoPdvAtencion}
     */
    private function crearTurnoAsignado(?\DateTimeInterface $atencionInicio = null): array
    {
        $turno = TurnoPdv::factory()->create([
            'sucursal_id' => $this->sucursal->id,
            'estado' => TurnoPdv::ESTADO_ASIGNADO,
        ]);

        $atencion = TurnoPdvAtencion::factory()->create([
            'turno_id' => $turno->id,
            'user_id' => $this->vendedor->id,
            'numero_secuencia' => 1,
            'inicio_at' => now()->subMinutes(10),
            'atencion_inicio_at' => $atencionInicio,
            'fin_at' => null,
        ]);

        $turno->update(['atencion_actual_id' => $atencion->id]);

        return [
            'turno' => $turno->fresh(),
            'atencion' => $atencion->fresh(),
        ];
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
            }
        );
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
        $config = new PlazosTurnosPdvConfig;
        ConfiguracionSistema::query()->updateOrCreate(
            ['clave' => PlazosTurnosPdvConfig::CLAVE],
            [
                'valor' => json_encode($config->configuracionInicialAprobada(), JSON_UNESCAPED_UNICODE),
                'tipo' => 'json',
                'grupo' => 'PuntoVenta',
            ]
        );
    }
}
