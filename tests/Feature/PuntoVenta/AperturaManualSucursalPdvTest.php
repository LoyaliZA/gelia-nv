<?php

namespace Tests\Feature\PuntoVenta;

use App\Events\PuntoVenta\JornadaAperturaHorario;
use App\Events\PuntoVenta\JornadaAperturaManual;
use App\Models\ConfiguracionSistema;
use App\Models\PuntoVenta\OperacionPdvEvento;
use App\Models\PuntoVenta\SucursalDiaOperacionPdv;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\PuntoVenta\AlcancePdv;
use App\Services\PuntoVenta\Operacion\AperturaHorarioSucursalPdvService;
use App\Services\PuntoVenta\Operacion\AperturaManualSucursalPdvService;
use App\Services\PuntoVenta\Operacion\HorarioCierreOperacionPdvConfig;
use App\Services\PuntoVenta\Operacion\ResolverSucursalDiaOperacionPdv;
use App\Services\PuntoVenta\PuntoVentaModulo;
use Carbon\Carbon;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AperturaManualSucursalPdvTest extends TestCase
{
    use RefreshDatabase;

    private Sucursal $sucursal;

    private User $gerencia;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('Super Admin', 'web');
        $this->withoutMiddleware([
            ValidateCsrfToken::class,
            PreventRequestForgery::class,
        ]);

        foreach (PuntoVentaModulo::permisosIniciales() as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }

        ConfiguracionSistema::query()->updateOrCreate(
            ['clave' => PuntoVentaModulo::CLAVE_FLAG],
            ['valor' => '1']
        );

        $this->sucursal = Sucursal::factory()->create(['nombre' => 'Sucursal Apertura Manual']);
        $this->gerencia = User::factory()->create();
        $this->gerencia->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_TURNOS_VER,
            PuntoVentaModulo::PERMISO_OPERACION_JORNADA_CERRAR_SUCURSAL,
        ]);
        $this->gerencia->concederAccesoSucursal($this->sucursal, esPrincipal: true);
        app(AlcancePdv::class)->establecerSucursalActiva($this->gerencia, $this->sucursal->id);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_abre_jornada_antes_del_horario_y_registra_origen_manual(): void
    {
        Event::fake([JornadaAperturaManual::class]);
        $this->configurarHorario();

        $antes = now('America/Mexico_City')->setTime(7, 15);
        Carbon::setTestNow($antes);
        $dia = app(ResolverSucursalDiaOperacionPdv::class)->obtenerOCrear($this->sucursal->id, $antes);
        $this->assertFalse($dia->acepta_altas);

        $response = $this->actingAs($this->gerencia)->postJson(
            route('punto_venta.operacion.jornada.abrir_sucursal'),
            ['version' => $dia->version],
        );

        $response->assertOk()
            ->assertJsonPath('sucursal_dia.acepta_altas', true);

        $this->assertNotEmpty($response->json('sucursal_dia.apertura_manual_at'));

        $dia->refresh();
        $this->assertTrue($dia->acepta_altas);
        $this->assertNotNull($dia->apertura_manual_at);
        $this->assertSame($this->gerencia->id, $dia->apertura_manual_por_id);
        $this->assertDatabaseHas('pdv_operacion_eventos', [
            'sucursal_id' => $this->sucursal->id,
            'tipo_evento' => AperturaManualSucursalPdvService::TIPO_EVENTO,
        ]);
        Event::assertDispatched(JornadaAperturaManual::class);
    }

    public function test_apertura_manual_es_idempotente_si_ya_esta_abierta(): void
    {
        Event::fake([JornadaAperturaManual::class]);
        $this->configurarHorario();

        $antes = now('America/Mexico_City')->setTime(7, 20);
        Carbon::setTestNow($antes);
        $dia = app(ResolverSucursalDiaOperacionPdv::class)->obtenerOCrear($this->sucursal->id, $antes);

        $this->actingAs($this->gerencia)->postJson(
            route('punto_venta.operacion.jornada.abrir_sucursal'),
            ['version' => $dia->version],
        )->assertOk();

        $dia->refresh();

        $this->actingAs($this->gerencia)->postJson(
            route('punto_venta.operacion.jornada.abrir_sucursal'),
            ['version' => $dia->version],
        )->assertOk()->assertJsonPath('sucursal_dia.acepta_altas', true);

        $this->assertSame(1, OperacionPdvEvento::query()->where('tipo_evento', AperturaManualSucursalPdvService::TIPO_EVENTO)->count());
        Event::assertDispatched(JornadaAperturaManual::class, 1);
    }

    public function test_rechaza_apertura_manual_si_hay_cierre_manual(): void
    {
        $this->configurarHorario();

        $dia = SucursalDiaOperacionPdv::factory()->sinAltas()->create([
            'sucursal_id' => $this->sucursal->id,
            'cierre_manual_at' => now(),
            'cierre_manual_por_id' => $this->gerencia->id,
        ]);

        $this->actingAs($this->gerencia)->postJson(
            route('punto_venta.operacion.jornada.abrir_sucursal'),
            ['version' => $dia->version],
        )->assertUnprocessable()->assertJsonValidationErrors(['sucursal']);
    }

    public function test_apertura_automatica_no_corre_despues_de_inicio_manual(): void
    {
        Event::fake([JornadaAperturaManual::class, JornadaAperturaHorario::class]);
        $this->configurarHorario();

        $antes = now('America/Mexico_City')->setTime(7, 40);
        Carbon::setTestNow($antes);
        $dia = app(ResolverSucursalDiaOperacionPdv::class)->obtenerOCrear($this->sucursal->id, $antes);

        $this->actingAs($this->gerencia)->postJson(
            route('punto_venta.operacion.jornada.abrir_sucursal'),
            ['version' => $dia->version],
        )->assertOk();

        $despues = now('America/Mexico_City')->setTime(8, 10);
        $this->assertFalse(app(AperturaHorarioSucursalPdvService::class)->ejecutar($this->sucursal->id, $despues));
        Event::assertNotDispatched(JornadaAperturaHorario::class);
    }

    public function test_si_el_automatico_ya_abrio_no_marca_inicio_manual(): void
    {
        Event::fake([JornadaAperturaHorario::class, JornadaAperturaManual::class]);
        $this->configurarHorario();

        $antes = now('America/Mexico_City')->setTime(7, 0);
        Carbon::setTestNow($antes);
        $dia = app(ResolverSucursalDiaOperacionPdv::class)->obtenerOCrear($this->sucursal->id, $antes);

        $despues = now('America/Mexico_City')->setTime(8, 5);
        $this->assertTrue(app(AperturaHorarioSucursalPdvService::class)->ejecutar($this->sucursal->id, $despues));
        $dia->refresh();

        $this->actingAs($this->gerencia)->postJson(
            route('punto_venta.operacion.jornada.abrir_sucursal'),
            ['version' => $dia->version],
        )->assertOk()->assertJsonPath('sucursal_dia.acepta_altas', true);

        $dia->refresh();
        $this->assertNull($dia->apertura_manual_at);
        Event::assertNotDispatched(JornadaAperturaManual::class);
    }

    public function test_estado_unificado_expone_origen_manual(): void
    {
        $this->configurarHorario();
        $antes = now('America/Mexico_City')->setTime(7, 10);
        Carbon::setTestNow($antes);
        $dia = app(ResolverSucursalDiaOperacionPdv::class)->obtenerOCrear($this->sucursal->id, $antes);

        $this->actingAs($this->gerencia)->postJson(
            route('punto_venta.operacion.jornada.abrir_sucursal'),
            ['version' => $dia->version],
        )->assertOk();

        $this->actingAs($this->gerencia)
            ->getJson(route('punto_venta.operacion.datos'))
            ->assertOk()
            ->assertJsonPath('sucursal_dia.origen_apertura', 'manual')
            ->assertJsonPath('sucursal_dia.jornada_abierta', true);
    }

    private function configurarHorario(): void
    {
        $config = app(HorarioCierreOperacionPdvConfig::class);
        $normalizado = $config->normalizarCompleto([
            'activo' => true,
            'zona_horaria' => 'America/Mexico_City',
            'hora_apertura' => '08:00',
            'hora_cierre' => '19:00',
            'por_sucursal' => [],
        ]);

        ConfiguracionSistema::query()->updateOrCreate(
            ['clave' => HorarioCierreOperacionPdvConfig::CLAVE],
            [
                'valor' => json_encode($normalizado, JSON_UNESCAPED_UNICODE),
                'tipo' => 'json',
                'grupo' => 'PuntoVenta',
                'descripcion' => 'Horario operativo PDV (apertura y cierre)',
            ]
        );

        Cache::forget(HorarioCierreOperacionPdvConfig::CACHE_KEY);
    }
}
