<?php

namespace Tests\Feature\PuntoVenta;

use App\Events\PuntoVenta\PausaFinalizada;
use App\Events\PuntoVenta\PausaIniciada;
use App\Models\ConfiguracionSistema;
use App\Models\PuntoVenta\IntervaloOperativoPdv;
use App\Models\PuntoVenta\JornadaPdv;
use App\Models\PuntoVenta\TurnoPdvAtencion;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\PuntoVenta\AlcancePdv;
use App\Services\PuntoVenta\Operacion\AbrirJornadaPdvService;
use App\Services\PuntoVenta\Operacion\ConsultaPersonaDisponiblePdvService;
use App\Services\PuntoVenta\Operacion\FinalizarPausaPdvService;
use App\Services\PuntoVenta\Operacion\IniciarPausaPdvService;
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

class PausaOperacionPdvTest extends TestCase
{
    use RefreshDatabase;

    private Sucursal $sucursal;

    private User $ventas;

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

        $this->sucursal = Sucursal::factory()->create(['nombre' => 'Sucursal Pausa']);
        $this->ventas = $this->crearVendedor('Vendedor Pausa');
    }

    public function test_iniciar_pausa_valida_cierra_disponible_abre_en_pausa_y_emite_evento(): void
    {
        Event::fake([PausaIniciada::class]);

        $this->abrirJornada();

        $response = $this->actingAs($this->ventas)->postJson(route('punto_venta.operacion.pausa.iniciar'));

        $response->assertOk()
            ->assertJsonPath('intervalo.tipo', TipoIntervaloOperativoPdv::EnPausa->value)
            ->assertJsonPath('reintento', false);

        $intervalos = IntervaloOperativoPdv::query()->orderBy('id')->get();
        $this->assertCount(2, $intervalos);
        $this->assertSame(TipoIntervaloOperativoPdv::Disponible, $intervalos[0]->tipo);
        $this->assertNotNull($intervalos[0]->fin_at);
        $this->assertSame(TipoIntervaloOperativoPdv::EnPausa, $intervalos[1]->tipo);
        $this->assertNull($intervalos[1]->fin_at);

        Event::assertDispatched(PausaIniciada::class);
    }

    public function test_rechaza_pausa_con_atencion_abierta(): void
    {
        $this->abrirJornada();

        TurnoPdvAtencion::factory()->create([
            'user_id' => $this->ventas->id,
            'fin_at' => null,
        ]);

        $this->actingAs($this->ventas)
            ->postJson(route('punto_venta.operacion.pausa.iniciar'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['atencion']);
    }

    public function test_rechaza_pausa_sin_jornada_abierta(): void
    {
        $this->actingAs($this->ventas)
            ->postJson(route('punto_venta.operacion.pausa.iniciar'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['jornada']);
    }

    public function test_rechaza_pausa_con_jornada_cerrada_con_atencion(): void
    {
        $this->abrirJornada();
        $jornada = JornadaPdv::query()->sole();
        $jornada->update(['estado' => EstadoJornadaPdv::CerradaConAtencion]);

        $this->actingAs($this->ventas)
            ->postJson(route('punto_venta.operacion.pausa.iniciar'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['jornada']);
    }

    public function test_doble_inicio_pausa_es_idempotente(): void
    {
        Event::fake([PausaIniciada::class]);

        $this->abrirJornada();

        $this->actingAs($this->ventas)->postJson(route('punto_venta.operacion.pausa.iniciar'))->assertOk();
        $segunda = $this->actingAs($this->ventas)->postJson(route('punto_venta.operacion.pausa.iniciar'));

        $segunda->assertOk()->assertJsonPath('reintento', true);
        $this->assertSame(1, IntervaloOperativoPdv::query()->whereNull('fin_at')->count());
        Event::assertDispatched(PausaIniciada::class, 1);
    }

    public function test_finalizar_pausa_vuelve_a_disponible_y_emite_evento(): void
    {
        Event::fake([PausaIniciada::class, PausaFinalizada::class]);

        $this->abrirJornada();
        $this->actingAs($this->ventas)->postJson(route('punto_venta.operacion.pausa.iniciar'))->assertOk();

        $response = $this->actingAs($this->ventas)->postJson(route('punto_venta.operacion.pausa.finalizar'));

        $response->assertOk()
            ->assertJsonPath('intervalo.tipo', TipoIntervaloOperativoPdv::Disponible->value)
            ->assertJsonPath('reintento', false);

        $abierto = IntervaloOperativoPdv::query()->whereNull('fin_at')->sole();
        $this->assertSame(TipoIntervaloOperativoPdv::Disponible, $abierto->tipo);

        Event::assertDispatched(PausaFinalizada::class);
    }

    public function test_doble_fin_pausa_es_idempotente(): void
    {
        Event::fake([PausaFinalizada::class]);

        $this->abrirJornada();
        $this->actingAs($this->ventas)->postJson(route('punto_venta.operacion.pausa.iniciar'))->assertOk();
        $this->actingAs($this->ventas)->postJson(route('punto_venta.operacion.pausa.finalizar'))->assertOk();

        $segunda = $this->actingAs($this->ventas)->postJson(route('punto_venta.operacion.pausa.finalizar'));

        $segunda->assertOk()->assertJsonPath('reintento', true);
        Event::assertDispatched(PausaFinalizada::class, 1);
    }

    public function test_rechaza_finalizar_pausa_sin_pausa_activa(): void
    {
        $this->abrirJornada();

        $this->actingAs($this->ventas)
            ->postJson(route('punto_venta.operacion.pausa.finalizar'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['pausa']);
    }

    public function test_rechaza_finalizar_pausa_con_jornada_cerrada(): void
    {
        $this->abrirJornada();
        $this->actingAs($this->ventas)->postJson(route('punto_venta.operacion.pausa.iniciar'))->assertOk();

        JornadaPdv::query()->update(['estado' => EstadoJornadaPdv::Cerrada]);

        $this->actingAs($this->ventas)
            ->postJson(route('punto_venta.operacion.pausa.finalizar'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['jornada']);
    }

    public function test_tras_finalizar_pausa_persona_vuelve_a_estar_disponible(): void
    {
        $servicio = app(ConsultaPersonaDisponiblePdvService::class);
        $this->darPermisosAtencion($this->ventas);
        $this->abrirJornada();

        $this->assertTrue($servicio->esDisponible($this->ventas, $this->sucursal->id));

        $this->actingAs($this->ventas)->postJson(route('punto_venta.operacion.pausa.iniciar'))->assertOk();
        $this->assertFalse($servicio->esDisponible($this->ventas, $this->sucursal->id));

        $this->actingAs($this->ventas)->postJson(route('punto_venta.operacion.pausa.finalizar'))->assertOk();
        $this->assertTrue($servicio->esDisponible($this->ventas, $this->sucursal->id));
    }

    public function test_dos_inicios_concurrentes_no_duplican_pausa_activa(): void
    {
        Event::fake([PausaIniciada::class]);

        $this->abrirJornada();
        $inicios = 0;

        DB::transaction(function () use (&$inicios): void {
            app(IniciarPausaPdvService::class)->ejecutar($this->ventas, now());
            $inicios++;
        });

        DB::transaction(function () use (&$inicios): void {
            app(IniciarPausaPdvService::class)->ejecutar($this->ventas, now());
            $inicios++;
        });

        $this->assertSame(2, $inicios);
        $this->assertSame(1, IntervaloOperativoPdv::query()->whereNull('fin_at')->count());
        $this->assertSame(
            TipoIntervaloOperativoPdv::EnPausa,
            IntervaloOperativoPdv::query()->whereNull('fin_at')->value('tipo'),
        );
    }

    public function test_dos_fines_concurrentes_no_duplican_intervalo_disponible(): void
    {
        Event::fake([PausaFinalizada::class]);

        $this->abrirJornada();
        app(IniciarPausaPdvService::class)->ejecutar($this->ventas, now());
        $fines = 0;

        DB::transaction(function () use (&$fines): void {
            app(FinalizarPausaPdvService::class)->ejecutar($this->ventas, now());
            $fines++;
        });

        DB::transaction(function () use (&$fines): void {
            app(FinalizarPausaPdvService::class)->ejecutar($this->ventas, now());
            $fines++;
        });

        $this->assertSame(2, $fines);
        $this->assertSame(1, IntervaloOperativoPdv::query()->whereNull('fin_at')->count());
        $this->assertSame(
            TipoIntervaloOperativoPdv::Disponible,
            IntervaloOperativoPdv::query()->whereNull('fin_at')->value('tipo'),
        );
    }

    public function test_bloquea_pausa_sin_sucursal_activa(): void
    {
        $sinSucursal = User::factory()->create();
        $sinSucursal->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_OPERACION_PAUSA,
        ]);

        $this->actingAs($sinSucursal)
            ->postJson(route('punto_venta.operacion.pausa.iniciar'))
            ->assertForbidden();
    }

    private function abrirJornada(): void
    {
        app(AbrirJornadaPdvService::class)->ejecutar($this->ventas, now());
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
            PuntoVentaModulo::PERMISO_OPERACION_PAUSA,
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
