<?php

namespace Tests\Feature\PuntoVenta;

use App\Contracts\PuntoVenta\ConsultaPersonaDisponiblePdv;
use App\Events\PuntoVenta\JornadaAbierta;
use App\Events\PuntoVenta\JornadaAmpliada;
use App\Events\PuntoVenta\JornadaCerrada;
use App\Events\PuntoVenta\JornadaCierreManual;
use App\Events\PuntoVenta\JornadaReaperturaManual;
use App\Models\ConfiguracionSistema;
use App\Models\PuntoVenta\IntervaloOperativoPdv;
use App\Models\PuntoVenta\JornadaPdv;
use App\Models\PuntoVenta\SucursalDiaOperacionPdv;
use App\Models\PuntoVenta\TurnoPdv;
use App\Models\PuntoVenta\TurnoPdvAtencion;
use App\Services\PuntoVenta\Operacion\AperturaManualSucursalPdvService;
use App\Services\PuntoVenta\Turnos\CerrarAtencionTurnoPdvService;
use App\Services\PuntoVenta\Turnos\MatchmakerTurnosPdvService;
use App\Services\PuntoVenta\Turnos\PlazosTurnosPdvConfig;
use App\Support\PuntoVenta\Turnos\MotivosCierreAtencionTurnoPdv;
use Illuminate\Support\Facades\Cache;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\PuntoVenta\AlcancePdv;
use App\Services\PuntoVenta\Operacion\AbrirJornadaPdvService;
use App\Services\PuntoVenta\Operacion\ConsultaPersonaDisponiblePdvService;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Support\PuntoVenta\Operacion\EstadoJornadaPdv;
use App\Support\PuntoVenta\Operacion\TipoIntervaloOperativoPdv;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class JornadaDisponibilidadOperacionPdvTest extends TestCase
{
    use RefreshDatabase;

    private Sucursal $sucursal;

    private User $ventas;

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

        $this->sucursal = Sucursal::factory()->create(['nombre' => 'Sucursal Operación']);
        $this->ventas = $this->crearVendedor('Vendedor Uno');
        $this->gerencia = $this->crearGerencia('Gerente Uno');
    }

    public function test_abrir_jornada_crea_registro_intervalo_disponible_y_evento(): void
    {
        Event::fake([JornadaAbierta::class]);

        $response = $this->actingAs($this->ventas)->postJson(route('punto_venta.operacion.jornada.abrir'));

        $response->assertOk()
            ->assertJsonPath('jornada.estado', EstadoJornadaPdv::Abierta->value)
            ->assertJsonPath('intervalo.tipo', TipoIntervaloOperativoPdv::Disponible->value)
            ->assertJsonPath('reintento', false);

        $this->assertSame(1, JornadaPdv::query()->count());
        $this->assertSame(1, IntervaloOperativoPdv::query()->count());

        Event::assertDispatched(JornadaAbierta::class);
    }

    public function test_doble_apertura_es_idempotente(): void
    {
        Event::fake([JornadaAbierta::class]);

        $this->actingAs($this->ventas)->postJson(route('punto_venta.operacion.jornada.abrir'))->assertOk();
        $segunda = $this->actingAs($this->ventas)->postJson(route('punto_venta.operacion.jornada.abrir'));

        $segunda->assertOk()->assertJsonPath('reintento', true);
        $this->assertSame(1, JornadaPdv::query()->count());
        Event::assertDispatched(JornadaAbierta::class, 1);
    }

    public function test_cerrar_jornada_sin_atencion_pasa_a_cerrada(): void
    {
        Event::fake([JornadaAbierta::class, JornadaCerrada::class]);

        $this->actingAs($this->ventas)->postJson(route('punto_venta.operacion.jornada.abrir'))->assertOk();
        $jornada = JornadaPdv::query()->sole();

        $response = $this->actingAs($this->ventas)->postJson(route('punto_venta.operacion.jornada.cerrar'), [
            'version' => $jornada->version,
        ]);

        $response->assertOk()
            ->assertJsonPath('estado_destino', EstadoJornadaPdv::Cerrada->value);

        $jornada->refresh();
        $this->assertSame(EstadoJornadaPdv::Cerrada, $jornada->estado);
        $this->assertNotNull($jornada->cierre_at);
        $this->assertNull(
            IntervaloOperativoPdv::query()->whereNull('fin_at')->first()
        );

        Event::assertDispatched(JornadaCerrada::class);
    }

    public function test_cerrar_jornada_con_atencion_abierta_pasa_a_cerrada_con_atencion(): void
    {
        Event::fake([JornadaAbierta::class, JornadaCerrada::class]);

        $this->actingAs($this->ventas)->postJson(route('punto_venta.operacion.jornada.abrir'))->assertOk();
        $jornada = JornadaPdv::query()->sole();

        $turno = TurnoPdv::factory()->create([
            'sucursal_id' => $this->sucursal->id,
            'estado' => TurnoPdv::ESTADO_ASIGNADO,
        ]);

        TurnoPdvAtencion::factory()->create([
            'turno_id' => $turno->id,
            'user_id' => $this->ventas->id,
            'fin_at' => null,
        ]);

        IntervaloOperativoPdv::query()
            ->where('jornada_id', $jornada->id)
            ->update([
                'tipo' => TipoIntervaloOperativoPdv::EnAtencion,
            ]);

        $response = $this->actingAs($this->ventas)->postJson(route('punto_venta.operacion.jornada.cerrar'), [
            'version' => $jornada->version,
        ]);

        $response->assertOk()
            ->assertJsonPath('estado_destino', EstadoJornadaPdv::CerradaConAtencion->value);

        $jornada->refresh();
        $this->assertSame(EstadoJornadaPdv::CerradaConAtencion, $jornada->estado);
    }

    public function test_cierre_manual_invalida_cierre_automatico_del_dia(): void
    {
        Event::fake([JornadaCierreManual::class]);

        $dia = SucursalDiaOperacionPdv::factory()->create([
            'sucursal_id' => $this->sucursal->id,
            'acepta_altas' => true,
            'cierre_automatico_invalidado' => false,
        ]);

        $response = $this->actingAs($this->gerencia)->postJson(
            route('punto_venta.operacion.jornada.cerrar_sucursal'),
            ['version' => $dia->version],
        );

        $response->assertOk()
            ->assertJsonPath('sucursal_dia.acepta_altas', false)
            ->assertJsonPath('sucursal_dia.cierre_automatico_invalidado', true);

        Event::assertDispatched(JornadaCierreManual::class);
    }

    public function test_ampliacion_restaura_altas_e_invalida_cierre_automatico(): void
    {
        Event::fake([JornadaAmpliada::class]);

        $dia = SucursalDiaOperacionPdv::factory()->sinAltas()->create([
            'sucursal_id' => $this->sucursal->id,
            'cierre_automatico_invalidado' => true,
            'cierre_manual_at' => now()->subHour(),
        ]);

        $response = $this->actingAs($this->gerencia)->postJson(
            route('punto_venta.operacion.jornada.ampliar'),
            [
                'version' => $dia->version,
                'ampliacion_hasta_at' => now()->addHours(2)->toIso8601String(),
            ],
        );

        $response->assertOk()
            ->assertJsonPath('sucursal_dia.acepta_altas', true)
            ->assertJsonPath('sucursal_dia.cierre_automatico_invalidado', true);

        Event::assertDispatched(JornadaAmpliada::class);
    }

    public function test_cierre_manual_y_reapertura_restauran_altas(): void
    {
        Event::fake([JornadaCierreManual::class, JornadaReaperturaManual::class]);

        $dia = SucursalDiaOperacionPdv::factory()->create([
            'sucursal_id' => $this->sucursal->id,
            'acepta_altas' => true,
            'cierre_automatico_invalidado' => false,
        ]);

        $this->actingAs($this->gerencia)->postJson(
            route('punto_venta.operacion.jornada.cerrar_sucursal'),
            ['version' => $dia->version],
        )->assertOk()->assertJsonPath('sucursal_dia.acepta_altas', false);

        $dia->refresh();

        $this->actingAs($this->gerencia)->postJson(
            route('punto_venta.operacion.jornada.reabrir_sucursal'),
            ['version' => $dia->version],
        )->assertOk()->assertJsonPath('sucursal_dia.acepta_altas', true);

        Event::assertDispatched(JornadaCierreManual::class);
        Event::assertDispatched(JornadaReaperturaManual::class);
    }

    public function test_consulta_estado_no_muta_jornada_ni_intervalos(): void
    {
        $this->actingAs($this->ventas)->postJson(route('punto_venta.operacion.jornada.abrir'))->assertOk();

        $jornadasAntes = JornadaPdv::query()->count();
        $intervalosAntes = IntervaloOperativoPdv::query()->count();

        $response = $this->actingAs($this->ventas)->getJson(route('punto_venta.operacion.estado'));

        $response->assertOk()
            ->assertJsonPath('jornada.estado', EstadoJornadaPdv::Abierta->value)
            ->assertJsonPath('actividad', TipoIntervaloOperativoPdv::Disponible->value)
            ->assertJsonPath('sucursal_dia.acepta_altas', false);

        $this->assertSame($jornadasAntes, JornadaPdv::query()->count());
        $this->assertSame($intervalosAntes, IntervaloOperativoPdv::query()->count());
    }

    public function test_version_obsoleta_rechaza_cierre(): void
    {
        $this->actingAs($this->ventas)->postJson(route('punto_venta.operacion.jornada.abrir'))->assertOk();
        $jornada = JornadaPdv::query()->sole();

        $response = $this->actingAs($this->ventas)->postJson(route('punto_venta.operacion.jornada.cerrar'), [
            'version' => $jornada->version - 1,
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['version']);
    }

    public function test_bloquea_mutacion_sin_sucursal_activa(): void
    {
        $sinSucursal = User::factory()->create();
        $sinSucursal->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_OPERACION_JORNADA_ABRIR,
        ]);

        $this->actingAs($sinSucursal)
            ->postJson(route('punto_venta.operacion.jornada.abrir'))
            ->assertForbidden();
    }

    public function test_persona_disponible_requiere_jornada_abierta(): void
    {
        $servicio = app(ConsultaPersonaDisponiblePdvService::class);
        $this->darPermisosAtencion($this->ventas);

        $this->assertFalse($servicio->esDisponible($this->ventas, $this->sucursal->id));

        $this->abrirSucursal();
        app(AbrirJornadaPdvService::class)->ejecutar($this->ventas, now());

        $this->assertTrue($servicio->esDisponible($this->ventas, $this->sucursal->id));
    }

    public function test_persona_disponible_no_requiere_cerrar_atencion(): void
    {
        $vendedor = User::factory()->create(['name' => 'Solo atender']);
        $vendedor->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_TURNOS_ATENDER,
            PuntoVentaModulo::PERMISO_OPERACION_JORNADA_ABRIR,
        ]);
        $vendedor->concederAccesoSucursal($this->sucursal, esPrincipal: true);
        app(AlcancePdv::class)->establecerSucursalActiva($vendedor, $this->sucursal->id);
        $this->abrirSucursal();
        app(AbrirJornadaPdvService::class)->ejecutar($vendedor, now());

        $servicio = app(ConsultaPersonaDisponiblePdvService::class);

        $this->assertTrue($servicio->esDisponible($vendedor, $this->sucursal->id));
    }

    public function test_persona_no_disponible_con_pausa_vigente(): void
    {
        $servicio = app(ConsultaPersonaDisponiblePdvService::class);
        $this->darPermisosAtencion($this->ventas);
        app(AbrirJornadaPdvService::class)->ejecutar($this->ventas, now());
        $jornada = JornadaPdv::query()->sole();

        IntervaloOperativoPdv::query()
            ->where('jornada_id', $jornada->id)
            ->whereNull('fin_at')
            ->update(['tipo' => TipoIntervaloOperativoPdv::EnPausa]);

        $this->assertFalse($servicio->esDisponible($this->ventas, $this->sucursal->id));
    }

    public function test_persona_no_disponible_con_atencion_abierta(): void
    {
        $servicio = app(ConsultaPersonaDisponiblePdvService::class);
        $this->darPermisosAtencion($this->ventas);
        app(AbrirJornadaPdvService::class)->ejecutar($this->ventas, now());

        TurnoPdvAtencion::factory()->create([
            'user_id' => $this->ventas->id,
            'fin_at' => null,
        ]);

        $this->assertFalse($servicio->esDisponible($this->ventas, $this->sucursal->id));
    }

    public function test_para_alta_nueva_exige_sucursal_acepta_altas(): void
    {
        $servicio = app(ConsultaPersonaDisponiblePdvService::class);
        $this->darPermisosAtencion($this->ventas);
        app(AbrirJornadaPdvService::class)->ejecutar($this->ventas, now());

        SucursalDiaOperacionPdv::factory()->sinAltas()->create([
            'sucursal_id' => $this->sucursal->id,
        ]);

        $this->assertFalse($servicio->esDisponible($this->ventas, $this->sucursal->id, false));
        $this->assertFalse($servicio->esDisponible($this->ventas, $this->sucursal->id, true));
    }

    public function test_primera_ronda_reparte_al_azar_entre_quienes_no_han_atendido(): void
    {
        $this->abrirSucursal();
        $primero = $this->crearVendedor('Primero');
        $segundo = $this->crearVendedor('Segundo');

        foreach ([$primero, $segundo] as $vendedor) {
            app(AbrirJornadaPdvService::class)->ejecutar($vendedor, now());
        }

        $servicio = app(ConsultaPersonaDisponiblePdv::class);
        $elegidos = [];
        for ($i = 0; $i < 40; $i++) {
            $elegido = $servicio->primeraDisponible($this->sucursal->id, TurnoPdv::SERVICIO_VENTAS);
            $this->assertInstanceOf(User::class, $elegido);
            $elegidos[$elegido->id] = true;
        }

        $this->assertArrayHasKey($primero->id, $elegidos);
        $this->assertArrayHasKey($segundo->id, $elegidos);
    }

    public function test_quien_no_ha_atendido_tiene_prioridad_sobre_quien_ya_cerro(): void
    {
        $this->abrirSucursal();
        $sinTurno = $this->crearVendedor('Sin turno');
        $yaAtendio = $this->crearVendedor('Ya atendió');

        foreach ([$sinTurno, $yaAtendio] as $vendedor) {
            app(AbrirJornadaPdvService::class)->ejecutar($vendedor, now());
        }

        $this->registrarCierre($yaAtendio, now()->subMinutes(30));

        $elegido = app(ConsultaPersonaDisponiblePdv::class)
            ->primeraDisponible($this->sucursal->id, TurnoPdv::SERVICIO_VENTAS);

        $this->assertSame($sinTurno->id, $elegido?->id);
    }

    public function test_cola_entrega_al_que_quedo_libre_antes_aunque_tenga_id_mayor(): void
    {
        $this->abrirSucursal();
        $this->seedPlazos(0);
        $temprano = $this->crearVendedor('Cerró antes');
        $tarde = $this->crearVendedor('Cerró después');
        $menor = $temprano->id < $tarde->id ? $temprano : $tarde;
        $mayor = $temprano->id < $tarde->id ? $tarde : $temprano;

        foreach ([$menor, $mayor] as $vendedor) {
            app(AbrirJornadaPdvService::class)->ejecutar($vendedor, now());
        }

        $this->registrarCierre($mayor, now()->subMinutes(20));
        $this->registrarCierre($menor, now()->subMinutes(5));

        $elegido = app(ConsultaPersonaDisponiblePdv::class)
            ->primeraDisponible($this->sucursal->id, TurnoPdv::SERVICIO_VENTAS);

        $this->assertSame($mayor->id, $elegido?->id);
    }

    public function test_dos_aperturas_concurrentes_no_duplican_jornada_activa(): void
    {
        Event::fake([JornadaAbierta::class]);

        $aperturas = 0;

        DB::transaction(function () use (&$aperturas): void {
            app(AbrirJornadaPdvService::class)->ejecutar($this->ventas, now());
            $aperturas++;
        });

        DB::transaction(function () use (&$aperturas): void {
            app(AbrirJornadaPdvService::class)->ejecutar($this->ventas, now());
            $aperturas++;
        });

        $this->assertSame(2, $aperturas);
        $this->assertSame(1, JornadaPdv::query()->where('estado', EstadoJornadaPdv::Abierta)->count());
    }

    public function test_jornada_de_ayer_no_recibe_turnos_y_se_cierra_al_abrir_sucursal(): void
    {
        $vendedor = $this->crearVendedor('Ayer');
        $this->abrirSucursal();
        app(AbrirJornadaPdvService::class)->ejecutar($vendedor, now()->subDay());
        $jornada = JornadaPdv::query()->where('user_id', $vendedor->id)->sole();
        $jornada->apertura_at = now()->subDay();
        $jornada->save();

        $servicio = app(ConsultaPersonaDisponiblePdvService::class);
        $this->assertFalse($servicio->esDisponible($vendedor, $this->sucursal->id));

        $dia = SucursalDiaOperacionPdv::query()->where('sucursal_id', $this->sucursal->id)->sole();
        app(AperturaManualSucursalPdvService::class)->ejecutar($this->gerencia, (int) $dia->version, now());

        $this->assertSame(EstadoJornadaPdv::Cerrada, $jornada->fresh()->estado);
    }

    public function test_cooldown_rota_al_siguiente_vendedor_y_cero_asigna_en_el_acto(): void
    {
        $this->seedPlazos(10);
        $this->abrirSucursal();

        $primero = $this->crearVendedor('Primero cooldown');
        $segundo = $this->crearVendedor('Segundo cooldown');
        app(AbrirJornadaPdvService::class)->ejecutar($primero, now());
        app(AbrirJornadaPdvService::class)->ejecutar($segundo, now());

        $turnoUno = TurnoPdv::factory()->create([
            'sucursal_id' => $this->sucursal->id,
            'estado' => TurnoPdv::ESTADO_EN_COLA,
            'alta_at' => now()->subMinutes(2),
        ]);
        $turnoDos = TurnoPdv::factory()->create([
            'sucursal_id' => $this->sucursal->id,
            'estado' => TurnoPdv::ESTADO_EN_COLA,
            'alta_at' => now()->subMinute(),
        ]);

        app(MatchmakerTurnosPdvService::class)->ejecutar($this->sucursal->id, 'test.cooldown');

        $turnoUno->refresh();
        $primeroAsignado = User::query()->findOrFail($turnoUno->atencionActual->user_id);
        $segundoAsignado = $primeroAsignado->id === $primero->id ? $segundo : $primero;
        $this->assertSame(TurnoPdv::ESTADO_ASIGNADO, $turnoUno->estado);

        app(CerrarAtencionTurnoPdvService::class)->ejecutar(
            $turnoUno->fresh(),
            $primeroAsignado,
            (int) $turnoUno->fresh()->version,
            'pdv:cerrar:cooldown',
            MotivosCierreAtencionTurnoPdv::VENTA,
            null,
            now(),
        );

        app(MatchmakerTurnosPdvService::class)->ejecutar($this->sucursal->id, 'test.cooldown.cierre');

        $turnoDos->refresh();
        $this->assertSame(TurnoPdv::ESTADO_ASIGNADO, $turnoDos->estado);
        $this->assertSame($segundoAsignado->id, $turnoDos->atencionActual->user_id);
        $this->assertFalse(app(ConsultaPersonaDisponiblePdvService::class)->esDisponible($primeroAsignado, $this->sucursal->id));

        $this->seedPlazos(0);
        $turnoTres = TurnoPdv::factory()->create([
            'sucursal_id' => $this->sucursal->id,
            'estado' => TurnoPdv::ESTADO_EN_COLA,
            'alta_at' => now(),
        ]);

        app(CerrarAtencionTurnoPdvService::class)->ejecutar(
            $turnoDos->fresh(),
            $segundoAsignado,
            (int) $turnoDos->fresh()->version,
            'pdv:cerrar:cooldown-cero',
            MotivosCierreAtencionTurnoPdv::VENTA,
            null,
            now(),
        );

        app(MatchmakerTurnosPdvService::class)->ejecutar($this->sucursal->id, 'test.cooldown.cero');

        $turnoTres->refresh();
        $this->assertSame(TurnoPdv::ESTADO_ASIGNADO, $turnoTres->estado);
        $this->assertSame($segundoAsignado->id, $turnoTres->atencionActual->user_id);

        $this->travel(11)->seconds();
        $this->assertTrue(app(ConsultaPersonaDisponiblePdvService::class)->esDisponible($primeroAsignado, $this->sucursal->id));
    }

    private function registrarCierre(User $vendedor, \Carbon\CarbonInterface $finAt): void
    {
        $turno = TurnoPdv::factory()->create([
            'sucursal_id' => $this->sucursal->id,
            'estado' => TurnoPdv::ESTADO_EN_REATENCION,
            'alta_at' => $finAt->copy()->subMinutes(10),
        ]);

        TurnoPdvAtencion::factory()->create([
            'turno_id' => $turno->id,
            'user_id' => $vendedor->id,
            'inicio_at' => $finAt->copy()->subMinutes(10),
            'fin_at' => $finAt,
        ]);
    }

    private function seedPlazos(int $cooldownSegundos): void
    {
        $config = new PlazosTurnosPdvConfig;
        $plazos = array_merge($config->configuracionInicialAprobada(), [
            'cooldown_segundos' => $cooldownSegundos,
        ]);
        ConfiguracionSistema::query()->updateOrCreate(
            ['clave' => PlazosTurnosPdvConfig::CLAVE],
            [
                'valor' => json_encode($plazos, JSON_UNESCAPED_UNICODE),
                'tipo' => 'json',
                'grupo' => 'PuntoVenta',
            ]
        );
        Cache::forget(PlazosTurnosPdvConfig::CACHE_KEY);
    }

    private function abrirSucursal(): void
    {
        SucursalDiaOperacionPdv::factory()->create([
            'sucursal_id' => $this->sucursal->id,
            'fecha_operativa' => now()->toDateString(),
            'acepta_altas' => true,
        ]);
    }

    private function crearVendedor(string $nombre): User
    {
        $usuario = User::factory()->create(['name' => $nombre]);
        $usuario->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_TURNOS_VER,
            PuntoVentaModulo::PERMISO_TURNOS_CERRAR_ATENCION,
            PuntoVentaModulo::PERMISO_TURNOS_ATENDER,
            PuntoVentaModulo::PERMISO_OPERACION_JORNADA_ABRIR,
            PuntoVentaModulo::PERMISO_OPERACION_JORNADA_CERRAR,
        ]);
        $usuario->concederAccesoSucursal($this->sucursal, esPrincipal: true);
        app(AlcancePdv::class)->establecerSucursalActiva($usuario, $this->sucursal->id);

        return $usuario;
    }

    private function crearGerencia(string $nombre): User
    {
        $usuario = User::factory()->create(['name' => $nombre]);
        $usuario->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_TURNOS_VER,
            PuntoVentaModulo::PERMISO_OPERACION_JORNADA_CERRAR_SUCURSAL,
            PuntoVentaModulo::PERMISO_OPERACION_JORNADA_AMPLIAR,
        ]);
        $usuario->concederAccesoSucursal($this->sucursal, esPrincipal: true);
        app(AlcancePdv::class)->establecerSucursalActiva($usuario, $this->sucursal->id);

        return $usuario;
    }

    private function darPermisosAtencion(User $usuario): void
    {
        $usuario->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_TURNOS_VER,
            PuntoVentaModulo::PERMISO_TURNOS_ATENDER,
        ]);
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
