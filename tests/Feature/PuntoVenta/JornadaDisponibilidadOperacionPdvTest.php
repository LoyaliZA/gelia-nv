<?php

namespace Tests\Feature\PuntoVenta;

use App\Contracts\PuntoVenta\ConsultaPersonaDisponiblePdv;
use App\Events\PuntoVenta\JornadaAbierta;
use App\Events\PuntoVenta\JornadaAmpliada;
use App\Events\PuntoVenta\JornadaCerrada;
use App\Events\PuntoVenta\JornadaCierreManual;
use App\Models\ConfiguracionSistema;
use App\Models\PuntoVenta\IntervaloOperativoPdv;
use App\Models\PuntoVenta\JornadaPdv;
use App\Models\PuntoVenta\SucursalDiaOperacionPdv;
use App\Models\PuntoVenta\TurnoPdv;
use App\Models\PuntoVenta\TurnoPdvAtencion;
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

    public function test_consulta_estado_no_muta_jornada_ni_intervalos(): void
    {
        $this->actingAs($this->ventas)->postJson(route('punto_venta.operacion.jornada.abrir'))->assertOk();

        $jornadasAntes = JornadaPdv::query()->count();
        $intervalosAntes = IntervaloOperativoPdv::query()->count();

        $response = $this->actingAs($this->ventas)->getJson(route('punto_venta.operacion.estado'));

        $response->assertOk()
            ->assertJsonPath('jornada.estado', EstadoJornadaPdv::Abierta->value)
            ->assertJsonPath('actividad', TipoIntervaloOperativoPdv::Disponible->value)
            ->assertJsonPath('sucursal_dia.acepta_altas', true);

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

        app(AbrirJornadaPdvService::class)->ejecutar($this->ventas, now());

        $this->assertTrue($servicio->esDisponible($this->ventas, $this->sucursal->id));
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

        $this->assertTrue($servicio->esDisponible($this->ventas, $this->sucursal->id, false));
        $this->assertFalse($servicio->esDisponible($this->ventas, $this->sucursal->id, true));
    }

    public function test_primera_disponible_desempata_por_user_id_asc(): void
    {
        $primero = $this->crearVendedor('Primero');
        $segundo = $this->crearVendedor('Segundo');

        foreach ([$primero, $segundo] as $vendedor) {
            app(AbrirJornadaPdvService::class)->ejecutar($vendedor, now());
        }

        $servicio = app(ConsultaPersonaDisponiblePdv::class);
        $elegido = $servicio->primeraDisponible($this->sucursal->id, TurnoPdv::SERVICIO_VENTAS);

        $this->assertInstanceOf(User::class, $elegido);
        $this->assertSame(min($primero->id, $segundo->id), $elegido->id);
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

    private function crearVendedor(string $nombre): User
    {
        $usuario = User::factory()->create(['name' => $nombre]);
        $usuario->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_TURNOS_VER,
            PuntoVentaModulo::PERMISO_TURNOS_CERRAR_ATENCION,
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
            PuntoVentaModulo::PERMISO_TURNOS_VER,
            PuntoVentaModulo::PERMISO_TURNOS_CERRAR_ATENCION,
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
