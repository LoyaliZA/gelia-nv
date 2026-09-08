<?php

namespace Tests\Feature\PuntoVenta;

use App\Events\PuntoVenta\JornadaAperturaHorario;
use App\Jobs\PuntoVenta\Operacion\AperturaHorarioSucursalPdvJob;
use App\Models\ConfiguracionSistema;
use App\Models\PuntoVenta\OperacionPdvEvento;
use App\Models\PuntoVenta\SucursalDiaOperacionPdv;
use App\Models\PuntoVenta\TurnoPdv;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\PuntoVenta\AlcancePdv;
use App\Services\PuntoVenta\Operacion\AperturaHorarioSucursalPdvService;
use App\Services\PuntoVenta\Operacion\EvaluarAperturaHorarioOperacionPdvService;
use App\Services\PuntoVenta\Operacion\HorarioCierreOperacionPdvConfig;
use App\Services\PuntoVenta\Operacion\ResolverSucursalDiaOperacionPdv;
use App\Services\PuntoVenta\PuntoVentaModulo;
use Carbon\Carbon;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schedule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AperturaHorarioOperacionPdvTest extends TestCase
{
    use RefreshDatabase;

    private Sucursal $sucursal;

    private User $recepcion;

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

        $this->sucursal = Sucursal::factory()->create(['nombre' => 'Sucursal Apertura']);

        $this->recepcion = User::factory()->create();
        $this->recepcion->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_TURNOS_ALTA,
        ]);
        $this->recepcion->concederAccesoSucursal($this->sucursal, esPrincipal: true);
        app(AlcancePdv::class)->establecerSucursalActiva($this->recepcion, $this->sucursal->id);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_dia_creado_antes_de_apertura_no_acepta_altas(): void
    {
        $this->configurarHorario([
            'hora_apertura' => '08:00',
            'hora_cierre' => '19:00',
            'zona_horaria' => 'America/Mexico_City',
        ]);

        $antes = now('America/Mexico_City')->setTime(7, 30);
        Carbon::setTestNow($antes);

        $dia = app(ResolverSucursalDiaOperacionPdv::class)->obtenerOCrear($this->sucursal->id, $antes);

        $this->assertFalse($dia->acepta_altas);
    }

    public function test_no_dispara_apertura_antes_del_horario_configurado(): void
    {
        $this->configurarHorario([
            'hora_apertura' => '08:00',
            'hora_cierre' => '19:00',
            'zona_horaria' => 'America/Mexico_City',
        ]);

        $antes = now('America/Mexico_City')->setTime(7, 45);
        $servicio = app(AperturaHorarioSucursalPdvService::class);

        $this->assertFalse($servicio->ejecutar($this->sucursal->id, $antes));
        $this->assertDatabaseMissing('pdv_operacion_eventos', [
            'sucursal_id' => $this->sucursal->id,
            'tipo_evento' => AperturaHorarioSucursalPdvService::TIPO_EVENTO,
        ]);
    }

    public function test_dispara_apertura_en_horario_y_emite_evento(): void
    {
        Event::fake([JornadaAperturaHorario::class]);
        $this->configurarHorario([
            'hora_apertura' => '08:00',
            'hora_cierre' => '19:00',
            'zona_horaria' => 'America/Mexico_City',
        ]);

        $antes = now('America/Mexico_City')->setTime(7, 0);
        Carbon::setTestNow($antes);
        $dia = app(ResolverSucursalDiaOperacionPdv::class)->obtenerOCrear($this->sucursal->id, $antes);
        $this->assertFalse($dia->acepta_altas);

        $despues = now('America/Mexico_City')->setTime(8, 5);
        $servicio = app(AperturaHorarioSucursalPdvService::class);

        $this->assertTrue($servicio->ejecutar($this->sucursal->id, $despues));

        $dia->refresh();
        $this->assertTrue($dia->acepta_altas);
        $this->assertDatabaseHas('pdv_operacion_eventos', [
            'sucursal_id' => $this->sucursal->id,
            'tipo_evento' => AperturaHorarioSucursalPdvService::TIPO_EVENTO,
        ]);

        Event::assertDispatched(JornadaAperturaHorario::class);
    }

    public function test_ejecucion_repetida_es_idempotente(): void
    {
        Event::fake([JornadaAperturaHorario::class]);
        $this->configurarHorario([
            'hora_apertura' => '08:00',
            'hora_cierre' => '19:00',
            'zona_horaria' => 'America/Mexico_City',
        ]);

        SucursalDiaOperacionPdv::factory()->create([
            'sucursal_id' => $this->sucursal->id,
            'fecha_operativa' => now('America/Mexico_City')->toDateString(),
            'acepta_altas' => false,
        ]);

        $despues = now('America/Mexico_City')->setTime(8, 10);
        $servicio = app(AperturaHorarioSucursalPdvService::class);

        $this->assertTrue($servicio->ejecutar($this->sucursal->id, $despues));
        $this->assertFalse($servicio->ejecutar($this->sucursal->id, $despues));
        $this->assertSame(1, OperacionPdvEvento::query()->count());
        Event::assertDispatched(JornadaAperturaHorario::class, 1);
    }

    public function test_rechaza_alta_antes_de_hora_apertura(): void
    {
        $this->configurarHorario([
            'hora_apertura' => '08:00',
            'hora_cierre' => '19:00',
            'zona_horaria' => 'America/Mexico_City',
        ]);

        $antes = now('America/Mexico_City')->setTime(7, 15);
        Carbon::setTestNow($antes);
        app(ResolverSucursalDiaOperacionPdv::class)->obtenerOCrear($this->sucursal->id, $antes);

        $response = $this->actingAs($this->recepcion)->postJson(
            route('punto_venta.turnos.store'),
            [
                'idempotency_key' => 'pdv:turno:antes-apertura',
                'nombre_llamado' => 'Cliente temprano',
            ],
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['sucursal']);

        $mensaje = collect($response->json('errors.sucursal'))->first();
        $this->assertStringContainsString('08:00', (string) $mensaje);
    }

    public function test_alta_despues_de_apertura_sin_vendedores_activos_crea_turno_en_cola(): void
    {
        $this->configurarHorario([
            'hora_apertura' => '08:00',
            'hora_cierre' => '19:00',
            'zona_horaria' => 'America/Mexico_City',
        ]);

        $despues = now('America/Mexico_City')->setTime(9, 0);
        Carbon::setTestNow($despues);
        app(ResolverSucursalDiaOperacionPdv::class)->obtenerOCrear($this->sucursal->id, $despues);

        $response = $this->actingAs($this->recepcion)->postJson(
            route('punto_venta.turnos.store'),
            [
                'idempotency_key' => 'pdv:turno:despues-apertura',
                'nombre_llamado' => 'Cliente en horario',
            ],
        );

        $response->assertCreated()
            ->assertJsonPath('turno.estado', TurnoPdv::ESTADO_EN_COLA);
    }

    public function test_comando_programado_y_evaluador_encolan_jobs(): void
    {
        Queue::fake();
        $this->configurarHorario([
            'hora_apertura' => '08:00',
            'hora_cierre' => '19:00',
            'zona_horaria' => 'America/Mexico_City',
        ]);

        Artisan::call('pdv:evaluar-apertura-horario-operacion');

        Queue::assertPushed(AperturaHorarioSucursalPdvJob::class);

        $eventos = collect(Schedule::events())
            ->map(fn ($evento) => $evento->command ?? $evento->description ?? '')
            ->filter(fn (string $comando) => str_contains($comando, 'pdv:evaluar-apertura-horario-operacion'));

        $this->assertNotEmpty($eventos);
    }

    public function test_configuracion_sin_hora_apertura_no_evalua(): void
    {
        $this->configurarHorario([
            'hora_apertura' => null,
            'hora_cierre' => '19:00',
            'zona_horaria' => 'America/Mexico_City',
        ]);

        $evaluador = app(EvaluarAperturaHorarioOperacionPdvService::class);
        $resultado = $evaluador->ejecutar();

        $this->assertGreaterThan(0, $resultado['evaluadas']);
        $this->assertSame(0, $resultado['encoladas']);
        $this->assertGreaterThan(0, $resultado['omitidas']);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function configurarHorario(array $datos): void
    {
        $config = app(HorarioCierreOperacionPdvConfig::class);
        $normalizado = $config->normalizarCompleto(array_merge([
            'activo' => true,
            'zona_horaria' => 'America/Mexico_City',
            'hora_apertura' => null,
            'hora_cierre' => '19:00',
            'por_sucursal' => [],
        ], $datos));

        ConfiguracionSistema::query()->updateOrCreate(
            ['clave' => HorarioCierreOperacionPdvConfig::CLAVE],
            [
                'valor' => json_encode($normalizado, JSON_UNESCAPED_UNICODE),
                'tipo' => 'json',
                'grupo' => 'PuntoVenta',
                'descripcion' => 'Horario operativo PDV (apertura y cierre)',
            ]
        );

        \Illuminate\Support\Facades\Cache::forget(HorarioCierreOperacionPdvConfig::CACHE_KEY);
    }
}
