<?php

namespace Tests\Feature\PuntoVenta;

use App\Events\PuntoVenta\JornadaCierreHorario;
use App\Jobs\PuntoVenta\Operacion\CierreHorarioSucursalPdvJob;
use App\Models\ConfiguracionSistema;
use App\Models\PuntoVenta\JornadaPdv;
use App\Models\PuntoVenta\OperacionPdvEvento;
use App\Models\PuntoVenta\SucursalDiaOperacionPdv;
use App\Models\PuntoVenta\TurnoPdv;
use App\Models\PuntoVenta\TurnoPdvAtencion;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\PuntoVenta\Operacion\CierreHorarioSucursalPdvService;
use App\Services\PuntoVenta\Operacion\EvaluarCierreHorarioOperacionPdvService;
use App\Services\PuntoVenta\Operacion\HorarioCierreOperacionPdvConfig;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Support\PuntoVenta\Operacion\EstadoJornadaPdv;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schedule;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CierreHorarioOperacionPdvTest extends TestCase
{
    use RefreshDatabase;

    private Sucursal $sucursal;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (PuntoVentaModulo::permisosIniciales() as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }

        ConfiguracionSistema::query()->updateOrCreate(
            ['clave' => PuntoVentaModulo::CLAVE_FLAG],
            ['valor' => '1']
        );

        $this->sucursal = Sucursal::factory()->create(['nombre' => 'Sucursal Horario']);
    }

    public function test_no_dispara_antes_del_horario_configurado(): void
    {
        $this->configurarHorario(['hora_cierre' => '19:00', 'zona_horaria' => 'America/Mexico_City']);

        $ahora = now('America/Mexico_City')->setTime(18, 30);
        $servicio = app(CierreHorarioSucursalPdvService::class);

        $this->assertFalse($servicio->ejecutar($this->sucursal->id, $ahora));
        $this->assertDatabaseMissing('pdv_operacion_eventos', [
            'sucursal_id' => $this->sucursal->id,
            'tipo_evento' => OperacionPdvEvento::TIPO_CIERRE_HORARIO,
        ]);
    }

    public function test_dispara_en_horario_configurado_y_emite_evento(): void
    {
        Event::fake([JornadaCierreHorario::class]);
        $this->configurarHorario(['hora_cierre' => '19:00', 'zona_horaria' => 'America/Mexico_City']);

        $ahora = now('America/Mexico_City')->setTime(19, 5);
        $servicio = app(CierreHorarioSucursalPdvService::class);

        $this->assertTrue($servicio->ejecutar($this->sucursal->id, $ahora));

        $dia = SucursalDiaOperacionPdv::query()
            ->where('sucursal_id', $this->sucursal->id)
            ->first();

        $this->assertNotNull($dia);
        $this->assertFalse($dia->acepta_altas);
        $this->assertSame('19:00:00', $dia->hora_cierre);
        $this->assertDatabaseHas('pdv_operacion_eventos', [
            'sucursal_id' => $this->sucursal->id,
            'tipo_evento' => OperacionPdvEvento::TIPO_CIERRE_HORARIO,
        ]);

        Event::assertDispatched(JornadaCierreHorario::class);
    }

    public function test_cierre_manual_impide_cierre_automatico(): void
    {
        Event::fake([JornadaCierreHorario::class]);
        $this->configurarHorario(['hora_cierre' => '19:00', 'zona_horaria' => 'America/Mexico_City']);

        $gerencia = User::factory()->create();
        $dia = SucursalDiaOperacionPdv::factory()->sinAltas()->create([
            'sucursal_id' => $this->sucursal->id,
            'fecha_operativa' => now('America/Mexico_City')->toDateString(),
            'cierre_manual_at' => now('America/Mexico_City')->setTime(18, 0),
            'cierre_manual_por_id' => $gerencia->id,
            'cierre_automatico_invalidado' => true,
        ]);

        $ahora = now('America/Mexico_City')->setTime(19, 30);
        $servicio = app(CierreHorarioSucursalPdvService::class);

        $this->assertFalse($servicio->ejecutar($this->sucursal->id, $ahora));
        $this->assertSame(0, OperacionPdvEvento::query()->count());
        Event::assertNotDispatched(JornadaCierreHorario::class);
        $this->assertSame($dia->version, $dia->fresh()->version);
    }

    public function test_ampliacion_vigente_impide_cierre_hasta_que_vence(): void
    {
        $this->configurarHorario(['hora_cierre' => '19:00', 'zona_horaria' => 'America/Mexico_City']);
        $fecha = now('America/Mexico_City')->toDateString();

        SucursalDiaOperacionPdv::factory()->create([
            'sucursal_id' => $this->sucursal->id,
            'fecha_operativa' => $fecha,
            'acepta_altas' => true,
            'ampliacion_hasta_at' => now('America/Mexico_City')->setTime(21, 0),
            'cierre_automatico_invalidado' => true,
        ]);

        $servicio = app(CierreHorarioSucursalPdvService::class);
        $this->assertFalse($servicio->ejecutar($this->sucursal->id, now('America/Mexico_City')->setTime(19, 30)));
        $this->assertTrue($servicio->ejecutar($this->sucursal->id, now('America/Mexico_City')->setTime(21, 1)));
    }

    public function test_ejecucion_repetida_es_idempotente(): void
    {
        Event::fake([JornadaCierreHorario::class]);
        $this->configurarHorario(['hora_cierre' => '19:00', 'zona_horaria' => 'America/Mexico_City']);

        $ahora = now('America/Mexico_City')->setTime(19, 10);
        $servicio = app(CierreHorarioSucursalPdvService::class);

        $this->assertTrue($servicio->ejecutar($this->sucursal->id, $ahora));
        $this->assertFalse($servicio->ejecutar($this->sucursal->id, $ahora));
        $this->assertSame(1, OperacionPdvEvento::query()->count());
        Event::assertDispatched(JornadaCierreHorario::class, 1);
    }

    public function test_reintento_de_job_en_cola_no_duplica_evento(): void
    {
        Event::fake([JornadaCierreHorario::class]);
        $this->configurarHorario(['hora_cierre' => '19:00', 'zona_horaria' => 'America/Mexico_City']);

        $ahora = now('America/Mexico_City')->setTime(19, 15)->toIso8601String();

        CierreHorarioSucursalPdvJob::dispatchSync($this->sucursal->id, $ahora);
        CierreHorarioSucursalPdvJob::dispatchSync($this->sucursal->id, $ahora);

        $this->assertSame(1, OperacionPdvEvento::query()->count());
        Event::assertDispatched(JornadaCierreHorario::class, 1);
    }

    public function test_sucursal_ya_sin_altas_no_genera_efecto_adicional(): void
    {
        Event::fake([JornadaCierreHorario::class]);
        $this->configurarHorario(['hora_cierre' => '19:00', 'zona_horaria' => 'America/Mexico_City']);

        SucursalDiaOperacionPdv::factory()->sinAltas()->create([
            'sucursal_id' => $this->sucursal->id,
            'fecha_operativa' => now('America/Mexico_City')->toDateString(),
            'cierre_manual_at' => null,
        ]);

        $servicio = app(CierreHorarioSucursalPdvService::class);
        $this->assertFalse($servicio->ejecutar($this->sucursal->id, now('America/Mexico_City')->setTime(19, 30)));
        $this->assertSame(0, OperacionPdvEvento::query()->count());
        Event::assertNotDispatched(JornadaCierreHorario::class);
    }

    public function test_sobrescritura_por_sucursal_y_zona_horaria(): void
    {
        $otra = Sucursal::factory()->create();
        $this->configurarHorario([
            'hora_cierre' => '19:00',
            'zona_horaria' => 'America/Mexico_City',
            'por_sucursal' => [
                (string) $this->sucursal->id => [
                    'hora_cierre' => '20:00',
                    'zona_horaria' => 'America/Tijuana',
                ],
            ],
        ]);

        $servicio = app(CierreHorarioSucursalPdvService::class);

        $antesTijuana = now('America/Tijuana')->setTime(19, 30);
        $this->assertFalse($servicio->ejecutar($this->sucursal->id, $antesTijuana));

        $despuesTijuana = now('America/Tijuana')->setTime(20, 5);
        $this->assertTrue($servicio->ejecutar($this->sucursal->id, $despuesTijuana));

        $this->configurarHorario([
            'hora_cierre' => '19:00',
            'zona_horaria' => 'America/Mexico_City',
            'por_sucursal' => [],
        ]);

        $this->assertFalse($servicio->ejecutar($otra->id, now('America/Mexico_City')->setTime(18, 30)));
        $this->assertTrue($servicio->ejecutar($otra->id, now('America/Mexico_City')->setTime(19, 5)));
    }

    public function test_configuracion_ausente_bloquea_funcion_sin_inventar_hora(): void
    {
        ConfiguracionSistema::query()
            ->where('clave', HorarioCierreOperacionPdvConfig::CLAVE)
            ->delete();
        Cache::forget(HorarioCierreOperacionPdvConfig::CACHE_KEY);

        $servicio = app(CierreHorarioSucursalPdvService::class);

        $this->assertFalse($servicio->ejecutar($this->sucursal->id, now()->setTime(23, 0)));
        $this->assertSame(0, OperacionPdvEvento::query()->count());

        $evaluador = app(EvaluarCierreHorarioOperacionPdvService::class);
        $this->assertSame(
            ['evaluadas' => 0, 'encoladas' => 0, 'omitidas' => 0],
            $evaluador->ejecutar(),
        );
    }

    public function test_cierre_horario_cierra_jornada_libre_y_retira_la_cola_del_dia(): void
    {
        $this->configurarHorario(['hora_cierre' => '19:00', 'zona_horaria' => 'America/Mexico_City']);

        $libre = User::factory()->create();
        $ocupado = User::factory()->create();
        $ahora = now('America/Mexico_City')->setTime(19, 1);

        $jornadaLibre = JornadaPdv::factory()->create([
            'user_id' => $libre->id,
            'sucursal_id' => $this->sucursal->id,
            'estado' => EstadoJornadaPdv::Abierta,
            'apertura_at' => $ahora->copy()->subHour(),
        ]);
        $jornadaOcupada = JornadaPdv::factory()->create([
            'user_id' => $ocupado->id,
            'sucursal_id' => $this->sucursal->id,
            'estado' => EstadoJornadaPdv::Abierta,
            'apertura_at' => $ahora->copy()->subHour(),
        ]);

        $turno = TurnoPdv::factory()->create([
            'sucursal_id' => $this->sucursal->id,
            'estado' => TurnoPdv::ESTADO_EN_COLA,
        ]);
        $turnoAsignado = TurnoPdv::factory()->create([
            'sucursal_id' => $this->sucursal->id,
            'estado' => TurnoPdv::ESTADO_ASIGNADO,
        ]);
        TurnoPdvAtencion::factory()->create([
            'turno_id' => $turnoAsignado->id,
            'user_id' => $ocupado->id,
            'fin_at' => null,
        ]);

        app(CierreHorarioSucursalPdvService::class)->ejecutar($this->sucursal->id, $ahora);

        $this->assertSame(EstadoJornadaPdv::Cerrada, $jornadaLibre->fresh()->estado);
        $this->assertSame(EstadoJornadaPdv::CerradaConAtencion, $jornadaOcupada->fresh()->estado);
        $this->assertSame(TurnoPdv::ESTADO_CERRADO, $turno->fresh()->estado);
        $this->assertSame(TurnoPdv::ESTADO_ASIGNADO, $turnoAsignado->fresh()->estado);
    }

    public function test_comando_programado_y_evaluador_encolan_jobs(): void
    {
        Queue::fake();
        $this->configurarHorario(['hora_cierre' => '19:00', 'zona_horaria' => 'America/Mexico_City']);

        Artisan::call('pdv:evaluar-cierre-horario-operacion');

        Queue::assertPushed(CierreHorarioSucursalPdvJob::class);

        $eventos = collect(Schedule::events())
            ->map(fn ($evento) => $evento->command ?? $evento->description ?? '')
            ->filter(fn (string $comando) => str_contains($comando, 'pdv:evaluar-cierre-horario-operacion'));

        $this->assertNotEmpty($eventos);
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
            'hora_cierre' => '19:00',
            'por_sucursal' => [],
        ], $datos));

        ConfiguracionSistema::query()->updateOrCreate(
            ['clave' => HorarioCierreOperacionPdvConfig::CLAVE],
            [
                'valor' => json_encode($normalizado, JSON_UNESCAPED_UNICODE),
                'tipo' => 'json',
                'grupo' => 'PuntoVenta',
                'descripcion' => 'Horario de cierre operativo PDV',
            ]
        );

        Cache::forget(HorarioCierreOperacionPdvConfig::CACHE_KEY);
    }
}
