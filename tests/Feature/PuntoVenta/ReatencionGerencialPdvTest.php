<?php

namespace Tests\Feature\PuntoVenta;

use App\Events\PuntoVenta\TurnoAsignado;
use App\Events\PuntoVenta\TurnoReatencion;
use App\Models\ConfiguracionSistema;
use App\Models\PuntoVenta\MotivoPausaPdv;
use App\Models\PuntoVenta\TurnoPdv;
use App\Models\PuntoVenta\TurnoPdvAtencion;
use App\Models\PuntoVenta\TurnoPdvEvento;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\PuntoVenta\AlcancePdv;
use App\Services\PuntoVenta\Operacion\GestionarEquipoOperativoPdvService;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Services\PuntoVenta\Turnos\MatchmakerTurnosPdvService;
use App\Services\PuntoVenta\Turnos\PlazosTurnosPdvConfig;
use App\Support\PuntoVenta\Turnos\MotivosCierreAtencionTurnoPdv;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReatencionGerencialPdvTest extends TestCase
{
    use RefreshDatabase;

    private Sucursal $sucursal;

    private User $vendedorAnterior;

    private User $vendedorDestino;

    private User $gerente;

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

        $this->sucursal = Sucursal::factory()->create(['nombre' => 'Sucursal Reatención']);
        $this->vendedorAnterior = $this->crearVendedor('Vendedor Anterior');
        $this->vendedorDestino = $this->crearVendedor('Vendedor Destino');
        $this->gerente = $this->crearGerente('Gerente Reatención');
    }

    public function test_cerrar_atencion_crea_elegibilidad_en_bandeja(): void
    {
        $contexto = $this->crearTurnoAsignado($this->vendedorAnterior);

        $this->actingAs($this->vendedorAnterior)->postJson(
            route('punto_venta.turnos.cerrar_atencion', $contexto['turno']),
            [
                'version' => $contexto['turno']->version,
                'idempotency_key' => 'pdv:cerrar:reatencion-bandeja',
                'motivo' => MotivosCierreAtencionTurnoPdv::VENTA,
            ],
        )->assertOk()
            ->assertJsonPath('turno.estado', TurnoPdv::ESTADO_EN_REATENCION);

        $this->activarVendedor($this->vendedorDestino);

        $response = $this->actingAs($this->gerente)
            ->getJson(route('punto_venta.operacion.vendedores.datos'));

        $response->assertOk()
            ->assertJsonCount(1, 'reatencion')
            ->assertJsonPath('reatencion.0.folio', $contexto['turno']->folio)
            ->assertJsonPath('reatencion.0.vendedor_anterior.id', $this->vendedorAnterior->id);

        $candidatos = collect($response->json('reatencion.0.candidatos'))->pluck('id');
        $this->assertTrue($candidatos->contains($this->vendedorDestino->id));
        $this->assertFalse($candidatos->contains($this->vendedorAnterior->id));
    }

    public function test_matchmaker_no_toma_re_atenciones(): void
    {
        Event::fake([TurnoReatencion::class, TurnoAsignado::class]);

        $this->activarVendedor($this->vendedorDestino);

        $turno = $this->crearTurnoEnReatencion($this->vendedorAnterior);

        $asignados = app(MatchmakerTurnosPdvService::class)
            ->ejecutar($this->sucursal->id, 'test.sin-reatencion');

        $this->assertSame(0, $asignados);
        $turno->refresh();
        $this->assertSame(TurnoPdv::ESTADO_EN_REATENCION, $turno->estado);
        Event::assertNotDispatched(TurnoReatencion::class);
    }

    public function test_gerente_asigna_a_otro_vendedor_disponible(): void
    {
        Event::fake([TurnoReatencion::class]);

        $this->activarVendedor($this->vendedorDestino);
        $turno = $this->crearTurnoEnReatencion($this->vendedorAnterior);

        $this->actingAs($this->gerente)->postJson(
            route('punto_venta.turnos.asignar_reatencion', $turno),
            [
                'version' => $turno->version,
                'destino_user_id' => $this->vendedorDestino->id,
                'idempotency_key' => 'pdv:reatencion:asignar-1',
            ],
        )->assertOk()
            ->assertJsonPath('turno.estado', TurnoPdv::ESTADO_ASIGNADO)
            ->assertJsonPath('atencion.user_id', $this->vendedorDestino->id)
            ->assertJsonCount(0, 'reatencion');

        $this->assertSame(
            1,
            TurnoPdvEvento::query()
                ->where('turno_id', $turno->id)
                ->where('tipo_evento', TurnoPdvEvento::TIPO_REATENCION)
                ->count()
        );

        Event::assertDispatched(TurnoReatencion::class, 1);
    }

    public function test_vendedor_anterior_pausado_inactivo_u_ocupado_queda_excluido(): void
    {
        $this->activarVendedor($this->vendedorAnterior);
        $this->activarVendedor($this->vendedorDestino);

        $turno = $this->crearTurnoEnReatencion($this->vendedorAnterior);

        $motivo = MotivoPausaPdv::query()->where('slug', 'comida')->firstOrFail();
        app(GestionarEquipoOperativoPdvService::class)->iniciarPausa(
            $this->gerente,
            $this->vendedorDestino,
            $motivo->id,
            null,
            now(),
            'pdv:pausa:test-reatencion',
        );

        $candidatos = collect(
            $this->actingAs($this->gerente)
                ->getJson(route('punto_venta.operacion.vendedores.datos'))
                ->json('reatencion.0.candidatos')
        )->pluck('id');

        $this->assertFalse($candidatos->contains($this->vendedorAnterior->id));
        $this->assertFalse($candidatos->contains($this->vendedorDestino->id));

        $this->actingAs($this->gerente)->postJson(
            route('punto_venta.turnos.asignar_reatencion', $turno),
            [
                'version' => $turno->version,
                'destino_user_id' => $this->vendedorDestino->id,
                'idempotency_key' => 'pdv:reatencion:pausado',
            ],
        )->assertUnprocessable()
            ->assertJsonValidationErrors(['destino_user_id']);
    }

    public function test_vencida_no_se_puede_asignar(): void
    {
        $this->activarVendedor($this->vendedorDestino);

        $turno = TurnoPdv::factory()->create([
            'sucursal_id' => $this->sucursal->id,
            'estado' => TurnoPdv::ESTADO_EN_REATENCION,
            'reatencion_expira_at' => now()->subMinute(),
            'atencion_actual_id' => null,
        ]);

        TurnoPdvAtencion::factory()->create([
            'turno_id' => $turno->id,
            'user_id' => $this->vendedorAnterior->id,
            'numero_secuencia' => 1,
            'inicio_at' => now()->subHours(2),
            'fin_at' => now()->subHour(),
        ]);

        $this->actingAs($this->gerente)
            ->getJson(route('punto_venta.operacion.vendedores.datos'))
            ->assertOk()
            ->assertJsonCount(0, 'reatencion');

        $this->actingAs($this->gerente)->postJson(
            route('punto_venta.turnos.asignar_reatencion', $turno),
            [
                'version' => $turno->version,
                'destino_user_id' => $this->vendedorDestino->id,
                'idempotency_key' => 'pdv:reatencion:vencida',
            ],
        )->assertUnprocessable()
            ->assertJsonValidationErrors(['turno']);
    }

    public function test_dos_gerentes_no_duplican_atencion(): void
    {
        Event::fake([TurnoReatencion::class]);

        $this->activarVendedor($this->vendedorDestino);
        $turno = $this->crearTurnoEnReatencion($this->vendedorAnterior);

        $payload = [
            'version' => $turno->version,
            'destino_user_id' => $this->vendedorDestino->id,
            'idempotency_key' => 'pdv:reatencion:concurrente',
        ];

        $this->actingAs($this->gerente)->postJson(
            route('punto_venta.turnos.asignar_reatencion', $turno),
            $payload,
        )->assertOk();

        $turno->refresh();

        $this->actingAs($this->gerente)->postJson(
            route('punto_venta.turnos.asignar_reatencion', $turno),
            $payload,
        )->assertOk();

        $this->assertSame(
            1,
            TurnoPdvAtencion::query()
                ->where('turno_id', $turno->id)
                ->whereNull('fin_at')
                ->count()
        );

        Event::assertDispatched(TurnoReatencion::class, 1);
    }

    public function test_dos_asignaciones_simultaneas_producen_una_sola_atencion(): void
    {
        Event::fake([TurnoReatencion::class]);

        $this->activarVendedor($this->vendedorDestino);
        $turno = $this->crearTurnoEnReatencion($this->vendedorAnterior);

        DB::transaction(function () use ($turno): void {
            $this->actingAs($this->gerente)->postJson(
                route('punto_venta.turnos.asignar_reatencion', $turno),
                [
                    'version' => $turno->version,
                    'destino_user_id' => $this->vendedorDestino->id,
                    'idempotency_key' => 'pdv:reatencion:worker-a',
                ],
            )->assertOk();
        });

        $turno->refresh();

        $this->actingAs($this->gerente)->postJson(
            route('punto_venta.turnos.asignar_reatencion', $turno),
            [
                'version' => $turno->version,
                'destino_user_id' => $this->vendedorDestino->id,
                'idempotency_key' => 'pdv:reatencion:worker-b',
            ],
        )->assertUnprocessable();

        $this->assertSame(
            1,
            TurnoPdvAtencion::query()
                ->where('turno_id', $turno->id)
                ->whereNull('fin_at')
                ->count()
        );
    }

    public function test_turnos_normales_continuan_asignandose_automaticamente(): void
    {
        Event::fake([TurnoAsignado::class]);

        $this->activarVendedor($this->vendedorDestino);
        $this->crearTurnoEnReatencion($this->vendedorAnterior);

        $normal = TurnoPdv::factory()->create([
            'sucursal_id' => $this->sucursal->id,
            'estado' => TurnoPdv::ESTADO_EN_COLA,
            'atencion_actual_id' => null,
            'alta_at' => now(),
        ]);

        $asignados = app(MatchmakerTurnosPdvService::class)
            ->ejecutar($this->sucursal->id, 'test.normal');

        $this->assertSame(1, $asignados);
        $normal->refresh();
        $this->assertSame(TurnoPdv::ESTADO_ASIGNADO, $normal->estado);
        Event::assertDispatched(TurnoAsignado::class, 1);
    }

    public function test_sin_permiso_reatencion_asignar_rechaza_asignacion(): void
    {
        $gerenteSinPermiso = User::factory()->create(['name' => 'Gerente sin permiso']);
        $gerenteSinPermiso->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_OPERACION_EQUIPO_VER,
        ]);
        $gerenteSinPermiso->concederAccesoSucursal($this->sucursal, esPrincipal: true);
        app(AlcancePdv::class)->establecerSucursalActiva($gerenteSinPermiso, $this->sucursal->id);

        $this->activarVendedor($this->vendedorDestino);
        $turno = $this->crearTurnoEnReatencion($this->vendedorAnterior);

        $this->actingAs($gerenteSinPermiso)->postJson(
            route('punto_venta.turnos.asignar_reatencion', $turno),
            [
                'version' => $turno->version,
                'destino_user_id' => $this->vendedorDestino->id,
                'idempotency_key' => 'pdv:reatencion:sin-permiso',
            ],
        )->assertForbidden();
    }

    public function test_no_permite_asignar_al_vendedor_anterior(): void
    {
        $this->activarVendedor($this->vendedorAnterior);
        $turno = $this->crearTurnoEnReatencion($this->vendedorAnterior);

        $this->actingAs($this->gerente)->postJson(
            route('punto_venta.turnos.asignar_reatencion', $turno),
            [
                'version' => $turno->version,
                'destino_user_id' => $this->vendedorAnterior->id,
                'idempotency_key' => 'pdv:reatencion:mismo-vendedor',
            ],
        )->assertUnprocessable()
            ->assertJsonValidationErrors(['destino_user_id']);
    }

    private function activarVendedor(User $vendedor): void
    {
        app(GestionarEquipoOperativoPdvService::class)->activar($this->gerente, $vendedor, now());
    }

    /**
     * @return array{turno: TurnoPdv, atencion: TurnoPdvAtencion}
     */
    private function crearTurnoAsignado(User $vendedor): array
    {
        $turno = TurnoPdv::factory()->create([
            'sucursal_id' => $this->sucursal->id,
            'estado' => TurnoPdv::ESTADO_ASIGNADO,
        ]);

        $atencion = TurnoPdvAtencion::factory()->create([
            'turno_id' => $turno->id,
            'user_id' => $vendedor->id,
            'numero_secuencia' => 1,
            'inicio_at' => now()->subMinutes(10),
            'fin_at' => null,
        ]);

        $turno->update(['atencion_actual_id' => $atencion->id]);

        return [
            'turno' => $turno->fresh(),
            'atencion' => $atencion->fresh(),
        ];
    }

    private function crearTurnoEnReatencion(User $vendedorAnterior): TurnoPdv
    {
        $turno = TurnoPdv::factory()->create([
            'sucursal_id' => $this->sucursal->id,
            'estado' => TurnoPdv::ESTADO_EN_REATENCION,
            'reatencion_expira_at' => now()->addHour(),
            'atencion_actual_id' => null,
        ]);

        TurnoPdvAtencion::factory()->create([
            'turno_id' => $turno->id,
            'user_id' => $vendedorAnterior->id,
            'numero_secuencia' => 1,
            'inicio_at' => now()->subHours(2),
            'fin_at' => now()->subHour(),
            'motivo_cierre' => MotivosCierreAtencionTurnoPdv::VENTA,
        ]);

        return $turno->fresh();
    }

    private function crearVendedor(string $nombre): User
    {
        $user = User::factory()->create(['name' => $nombre]);
        $user->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_TURNOS_VER,
            PuntoVentaModulo::PERMISO_TURNOS_CERRAR_ATENCION,
            PuntoVentaModulo::PERMISO_TURNOS_ATENDER,
        ]);
        $user->concederAccesoSucursal($this->sucursal, esPrincipal: true);
        app(AlcancePdv::class)->establecerSucursalActiva($user, $this->sucursal->id);

        return $user;
    }

    private function crearGerente(string $nombre): User
    {
        $user = User::factory()->create(['name' => $nombre]);
        $user->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_OPERACION_EQUIPO_VER,
            PuntoVentaModulo::PERMISO_OPERACION_EQUIPO_GESTIONAR,
            PuntoVentaModulo::PERMISO_TURNOS_REATENCION_ASIGNAR,
        ]);
        $user->concederAccesoSucursal($this->sucursal, esPrincipal: true);
        app(AlcancePdv::class)->establecerSucursalActiva($user, $this->sucursal->id);

        return $user;
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
