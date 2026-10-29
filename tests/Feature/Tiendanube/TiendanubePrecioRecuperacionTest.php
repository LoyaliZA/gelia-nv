<?php

namespace Tests\Feature\Tiendanube;

use App\Jobs\Tiendanube\Precios\ProcesarPrecioEjecucionJob;
use App\Jobs\Tiendanube\Precios\RecuperarPrecioEjecucionesJob;
use App\Models\Tiendanube\TiendanubeConfiguracion;
use App\Models\Tiendanube\TiendanubeOperacionTienda;
use App\Models\Tiendanube\TiendanubePrecioEjecucion;
use App\Models\Tiendanube\TiendanubePrecioEjecucionItem;
use App\Models\Tiendanube\TiendanubePrecioLote;
use App\Models\Tiendanube\TiendanubePrecioLoteRevision;
use App\Models\User;
use App\Services\Tiendanube\Gobernador\TiendanubeEnrutadorColas;
use App\Services\Tiendanube\Gobernador\TiendanubeReprogramador;
use App\Services\Tiendanube\Precios\Aplicacion\TiendanubePrecioEjecucionService;
use App\Services\Tiendanube\TiendanubeOperacionTiendaService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Str;
use Tests\Support\RefreshDatabaseSafe;
use Tests\TestCase;

class TiendanubePrecioRecuperacionTest extends TestCase
{
    use RefreshDatabaseSafe;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'tiendanube.api_base' => null,
            'tiendanube.api_host' => 'https://api.tiendanube.com',
            'tiendanube.api_version' => '2025-03',
            'tiendanube.retry_sleep_ms' => 0,
            'tiendanube.precios_aplicacion_habilitada' => true,
            'tiendanube.gobernador_activo' => false,
            'tiendanube.colas_dedicadas' => false,
            'tiendanube.recuperacion_scheduler_activo' => false,
        ]);
        TiendanubeConfiguracion::obtener()->fill([
            'store_id' => 8004291,
            'app_id' => '37163',
            'access_token' => encrypt('token-test'),
            'config_generation' => 1,
        ])->save();
        $this->user = User::factory()->create();
        $this->travelTo(Carbon::parse('2026-10-27 12:00:00'));
    }

    public function test_la_interrupcion_antes_del_despacho_se_repara_sin_trabajo_inmediato(): void
    {
        Queue::fake();
        $cuando = now()->addHour();
        $ejecucion = $this->ejecucion();
        $this->item($ejecucion, [
            'estado' => TiendanubePrecioEjecucionItem::ESTADO_REINTENTO_PENDIENTE,
            'siguiente_intento_at' => $cuando,
            'evidencia' => ['fase' => 'sin_escritura', 'continuacion' => ['reanudar_despues_at' => $cuando->getTimestamp() * 1000]],
        ]);
        $this->usarEnrutadorQueCorta();

        try {
            app(TiendanubeReprogramador::class)->programarEjecucion($ejecucion->id, $cuando);
            $this->fail('El corte debía ocurrir después de persistir y antes de encolar.');
        } catch (\RuntimeException $e) {
            $this->assertSame('corte-antes-del-despacho', $e->getMessage());
        }

        $ejecucion->refresh();
        $this->assertSame((string) $cuando->getTimestamp(), (string) $ejecucion->continuacion_programada_en);
        $this->assertNotSame((string) $cuando->getTimestamp(), (string) $ejecucion->continuacion_despachada_en);
        $this->assertSame(0, $this->contarTrabajos(false));
        $this->assertSame(0, $this->contarTrabajos(true));

        $this->restaurarEnrutador();
        app(TiendanubePrecioEjecucionService::class)->recuperar();
        app(TiendanubePrecioEjecucionService::class)->recuperar();

        $this->assertSame(0, $this->contarTrabajos(false));
        $this->assertSame(1, $this->contarTrabajos(true));
        $demorado = Queue::pushed(ProcesarPrecioEjecucionJob::class)->first();
        $this->assertNotNull($demorado);
        $this->assertTrue($demorado->delay->greaterThan(now()->addMinutes(50)));
        $ejecucion->refresh();
        $this->assertSame((string) $cuando->getTimestamp(), (string) $ejecucion->continuacion_despachada_en);
    }

    public function test_la_interrupcion_despues_del_despacho_no_duplica(): void
    {
        Queue::fake();
        $cuando = now()->addHour();
        $ejecucion = $this->ejecucion();
        $this->item($ejecucion, [
            'estado' => TiendanubePrecioEjecucionItem::ESTADO_REINTENTO_PENDIENTE,
            'siguiente_intento_at' => $cuando,
        ]);

        app(TiendanubeReprogramador::class)->programarEjecucion($ejecucion->id, $cuando);
        app(TiendanubeReprogramador::class)->programarEjecucion($ejecucion->id, $cuando);
        app(TiendanubePrecioEjecucionService::class)->recuperar();
        app(TiendanubeReprogramador::class)->despacharUnaVez($ejecucion->id, $cuando);

        $this->assertSame(0, $this->contarTrabajos(false));
        $this->assertSame(1, $this->contarTrabajos(true));
        $ejecucion->refresh();
        $this->assertSame((string) $cuando->getTimestamp(), (string) $ejecucion->continuacion_despachada_en);
    }

    public function test_todos_los_items_en_espera_no_generan_un_trabajo_inmediato(): void
    {
        Queue::fake();
        $cuando = now()->addHours(2);
        $ejecucion = $this->ejecucion();
        $item = $this->item($ejecucion, [
            'estado' => TiendanubePrecioEjecucionItem::ESTADO_REINTENTO_PENDIENTE,
            'siguiente_intento_at' => $cuando,
            'evidencia' => ['fase' => 'lectura_hecha'],
        ]);

        app(TiendanubePrecioEjecucionService::class)->recuperar();
        app(TiendanubePrecioEjecucionService::class)->recuperar();

        $this->assertSame(0, $this->contarTrabajos(false));
        $this->assertSame(1, $this->contarTrabajos(true));
        $item->refresh();
        $this->assertTrue($item->siguiente_intento_at->equalTo($cuando));
        $this->assertSame('lectura_hecha', $item->evidencia['fase'] ?? null);

        $this->travelTo($cuando->copy()->addSecond());
        app(TiendanubePrecioEjecucionService::class)->recuperar();
        $this->assertSame(0, $this->contarTrabajos(false));
        $this->assertSame(1, $this->contarTrabajos(true));
    }

    public function test_un_lease_vencido_conserva_la_fase_y_no_pisa_un_token_vigente(): void
    {
        Queue::fake();
        $cuando = now()->addMinutes(40);
        $ejecucion = $this->ejecucion();
        $abandonado = $this->item($ejecucion, [
            'estado' => TiendanubePrecioEjecucionItem::ESTADO_PROCESANDO,
            'lease_token' => (string) Str::uuid(),
            'lease_expires_at' => now()->subMinute(),
            'siguiente_intento_at' => $cuando,
            'evidencia' => [
                'fase' => 'lectura_hecha',
                'escritura' => false,
                'continuacion' => ['reanudar_despues_at' => $cuando->getTimestamp() * 1000],
            ],
        ]);
        $tokenVigente = (string) Str::uuid();
        $vigente = $this->item($ejecucion, [
            'variante_id' => 2,
            'estado' => TiendanubePrecioEjecucionItem::ESTADO_PROCESANDO,
            'lease_token' => $tokenVigente,
            'lease_expires_at' => now()->addMinutes(30),
        ]);

        app(TiendanubePrecioEjecucionService::class)->recuperar();

        $abandonado->refresh();
        $vigente->refresh();
        $this->assertSame(TiendanubePrecioEjecucionItem::ESTADO_REINTENTO_PENDIENTE, $abandonado->estado);
        $this->assertNull($abandonado->lease_token);
        $this->assertSame('lectura_hecha', $abandonado->evidencia['fase'] ?? null);
        $this->assertTrue($abandonado->siguiente_intento_at->equalTo($cuando));
        $this->assertSame($tokenVigente, $vigente->lease_token);
        $this->assertSame(TiendanubePrecioEjecucionItem::ESTADO_PROCESANDO, $vigente->estado);
        $this->assertSame(0, $this->contarTrabajos(false));
        $this->assertSame(1, $this->contarTrabajos(true));
    }

    public function test_la_cancelacion_entre_persistir_y_despachar_no_encola_envio(): void
    {
        Queue::fake();
        $cuando = now()->addHour();
        $ejecucion = $this->ejecucion();
        $item = $this->item($ejecucion, [
            'estado' => TiendanubePrecioEjecucionItem::ESTADO_REINTENTO_PENDIENTE,
            'siguiente_intento_at' => $cuando,
        ]);
        $this->usarEnrutadorQueCorta();
        try {
            app(TiendanubeReprogramador::class)->programarEjecucion($ejecucion->id, $cuando);
        } catch (\RuntimeException) {
        }
        $this->restaurarEnrutador();
        $ejecucion->update(['estado' => TiendanubePrecioEjecucion::ESTADO_CANCELADA]);
        $item->update([
            'estado' => TiendanubePrecioEjecucionItem::ESTADO_CANCELADO,
            'lease_token' => null,
            'lease_expires_at' => null,
        ]);

        app(TiendanubePrecioEjecucionService::class)->recuperar();
        app(TiendanubePrecioEjecucionService::class)->recuperar();

        $this->assertSame(0, $this->contarTrabajos(false));
        $this->assertSame(0, $this->contarTrabajos(true));
        $this->assertSame(TiendanubePrecioEjecucionItem::ESTADO_CANCELADO, $item->fresh()->estado);
    }

    public function test_una_suspension_no_reanuda_envios(): void
    {
        Queue::fake();
        $ejecucion = $this->ejecucion(TiendanubePrecioEjecucion::ESTADO_SUSPENDIDA);
        $item = $this->item($ejecucion, [
            'estado' => TiendanubePrecioEjecucionItem::ESTADO_PROCESANDO,
            'lease_token' => (string) Str::uuid(),
            'lease_expires_at' => now()->subMinute(),
            'evidencia' => ['fase' => 'sin_escritura', 'escritura' => false],
        ]);

        app(TiendanubePrecioEjecucionService::class)->recuperar();

        $this->assertSame(0, $this->contarTrabajos(false));
        $this->assertSame(0, $this->contarTrabajos(true));
        $item->refresh();
        $this->assertNotSame(TiendanubePrecioEjecucionItem::ESTADO_REINTENTO_PENDIENTE, $item->estado);
        $this->assertSame(TiendanubePrecioEjecucion::ESTADO_SUSPENDIDA, $ejecucion->fresh()->estado);
    }

    public function test_un_resultado_incierto_se_reconcilia_sin_otro_put(): void
    {
        Queue::fake();
        Http::fake(fn () => Http::response([
            'id' => 100,
            'variants' => [[
                'id' => 1000,
                'price' => '100.00',
                'promotional_price' => null,
                'cost' => null,
            ]],
        ], 200));
        $ejecucion = $this->ejecucion();
        $tokenTienda = (string) Str::uuid();
        $ejecucion->update(['lease_token' => $tokenTienda, 'lease_expires_at' => now()->addHour()]);
        TiendanubeOperacionTienda::query()->create([
            'store_id' => 8004291,
            'tipo' => TiendanubeOperacionTiendaService::TIPO_PRECIO_APLICACION,
            'owner_id' => $this->user->id,
            'config_generation' => 1,
            'lease_token' => $tokenTienda,
            'lease_expires_at' => now()->addHour(),
            'estado' => TiendanubeOperacionTienda::ESTADO_ACTIVA,
        ]);
        $item = $this->item($ejecucion, [
            'producto_id' => 100,
            'variante_id' => 1000,
            'estado' => TiendanubePrecioEjecucionItem::ESTADO_PROCESANDO,
            'lease_token' => (string) Str::uuid(),
            'lease_expires_at' => now()->subMinute(),
            'campos_objetivo' => ['normal' => ['intencion' => 'conservar']],
            'valor_anterior' => ['normal' => '100.00'],
            'evidencia' => [
                'fase' => 'escritura_enviada',
                'escritura' => true,
                'verificacion_pendiente' => true,
                'efecto' => 'posible',
            ],
        ]);

        app(TiendanubePrecioEjecucionService::class)->recuperar();

        $item->refresh();
        $this->assertSame(TiendanubePrecioEjecucionItem::ESTADO_RESULTADO_INCIERTO, $item->estado);
        $this->assertSame('escritura_enviada', $item->evidencia['fase'] ?? null);
        $this->assertNull($item->lease_token);
        $this->assertSame(1, $this->contarTrabajos(false));
        $this->assertSame(0, $this->contarTrabajos(true));
        Http::assertNothingSent();

        app(TiendanubePrecioEjecucionService::class)->procesarLote($ejecucion->id);
        $item->refresh();
        $this->assertSame(TiendanubePrecioEjecucionItem::ESTADO_CONFIRMADO, $item->estado);
        Http::assertNotSent(fn (Request $request) => $request->method() === 'PUT');
        Http::assertSent(fn (Request $request) => $request->method() === 'GET');
    }

    public function test_el_scheduler_sigue_apagado_si_la_config_cacheada_no_tiene_la_clave(): void
    {
        $this->assertFalse((bool) config('tiendanube.recuperacion_scheduler_activo', false));
        $config = (string) file_get_contents(config_path('tiendanube.php'));
        $this->assertStringContainsString("env('TIENDANUBE_RECUPERACION_SCHEDULER_ACTIVO', false)", $config);

        $cacheada = config('tiendanube');
        unset($cacheada['recuperacion_scheduler_activo']);
        config(['tiendanube' => $cacheada]);
        $this->assertFalse(array_key_exists('recuperacion_scheduler_activo', config('tiendanube')));
        $this->assertFalse((bool) config('tiendanube.recuperacion_scheduler_activo', false));

        $evento = collect(Schedule::events())->first(
            fn ($programado) => ($programado->description ?? '') === RecuperarPrecioEjecucionesJob::class
        );
        $this->assertNotNull($evento);
        $this->assertFalse($evento->filtersPass($this->app));

        config(['tiendanube.recuperacion_scheduler_activo' => false]);
        $this->assertFalse($evento->filtersPass($this->app));

        Queue::fake();
        $ejecucion = $this->ejecucion();
        $this->item($ejecucion, [
            'estado' => TiendanubePrecioEjecucionItem::ESTADO_PENDIENTE,
        ]);
        (new RecuperarPrecioEjecucionesJob)->handle(app(TiendanubePrecioEjecucionService::class));
        $this->assertSame(0, $this->contarTrabajos(false));
    }

    private function ejecucion(string $estado = TiendanubePrecioEjecucion::ESTADO_PROCESANDO): TiendanubePrecioEjecucion
    {
        $lote = TiendanubePrecioLote::query()->create([
            'store_id' => 8004291,
            'user_id' => $this->user->id,
            'api_version' => '2025-03',
            'motor_contract_version' => '1',
            'selection_id' => (string) Str::uuid(),
            'selection_version' => 1,
            'selection_modo' => 'pagina',
            'selection_filtros' => [],
            'estado' => TiendanubePrecioLote::ESTADO_APROBADO,
        ]);
        $revision = TiendanubePrecioLoteRevision::query()->create([
            'lote_id' => $lote->id,
            'numero' => 1,
            'estado' => TiendanubePrecioLoteRevision::ESTADO_APROBADO,
            'checksum' => 'e2d',
            'definicion' => [],
        ]);

        return TiendanubePrecioEjecucion::query()->create([
            'lote_id' => $lote->id,
            'revision_id' => $revision->id,
            'store_id' => 8004291,
            'user_id' => $this->user->id,
            'config_generation' => 1,
            'api_version' => '2025-03',
            'checksum_revision' => 'e2d',
            'canal' => 'api',
            'estado' => $estado,
            'total' => 1,
            'pendientes' => 1,
        ]);
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function item(TiendanubePrecioEjecucion $ejecucion, array $atributos): TiendanubePrecioEjecucionItem
    {
        return TiendanubePrecioEjecucionItem::query()->create(array_merge([
            'ejecucion_id' => $ejecucion->id,
            'producto_id' => 100,
            'variante_id' => 1000,
            'campos_objetivo' => ['normal' => ['intencion' => 'conservar']],
            'valor_aprobado' => ['normal' => '100.00'],
            'estado' => TiendanubePrecioEjecucionItem::ESTADO_PENDIENTE,
        ], $atributos));
    }

    private function usarEnrutadorQueCorta(): void
    {
        $this->app->forgetInstance(TiendanubeEnrutadorColas::class);
        $this->app->forgetInstance(TiendanubeReprogramador::class);
        $this->app->instance(TiendanubeEnrutadorColas::class, new class extends TiendanubeEnrutadorColas
        {
            public function despacharPrecio(string $ejecucionId, ?\DateTimeInterface $delay = null): void
            {
                throw new \RuntimeException('corte-antes-del-despacho');
            }
        });
    }

    private function restaurarEnrutador(): void
    {
        $this->app->forgetInstance(TiendanubeEnrutadorColas::class);
        $this->app->forgetInstance(TiendanubeReprogramador::class);
    }

    private function contarTrabajos(bool $demorados): int
    {
        return Queue::pushed(ProcesarPrecioEjecucionJob::class)
            ->filter(fn (ProcesarPrecioEjecucionJob $job) => $demorados ? $job->delay !== null : $job->delay === null)
            ->count();
    }
}
