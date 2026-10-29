<?php

namespace Tests\Feature\Tiendanube;

use App\Exceptions\Tiendanube\TiendanubeApiRateLimitException;
use App\Exceptions\Tiendanube\TiendanubePresupuestoLocalException;
use App\Jobs\Tiendanube\Precios\ProcesarPrecioEjecucionJob;
use App\Services\Tiendanube\Gobernador\TiendanubeContextoHttpActual;
use App\Services\Tiendanube\Gobernador\TiendanubeEmisorHttp;
use App\Services\Tiendanube\Gobernador\TiendanubeEnrutadorColas;
use App\Services\Tiendanube\Gobernador\TiendanubeGobernador;
use App\Services\Tiendanube\Gobernador\TiendanubeReprogramadorDurable;
use App\Services\Tiendanube\Gobernador\TiendanubeTurnoResultado;
use App\Services\Tiendanube\Precios\Aplicacion\TiendanubePrecioEjecucionItemProcessor;
use App\Services\Tiendanube\TiendanubeApiClient;
use App\Services\Tiendanube\TiendanubeProductoWriteService;
use App\Services\Tiendanube\TiendanubeStockPayload;
use App\Support\Tiendanube\TiendanubeClaseTurno;
use App\Models\Tiendanube\TiendanubeConfiguracion;
use App\Models\Tiendanube\TiendanubePrecioEjecucion;
use App\Models\Tiendanube\TiendanubePrecioEjecucionItem;
use App\Models\Tiendanube\TiendanubeProducto;
use App\Models\Tiendanube\TiendanubeProductoVariante;
use App\Models\User;
use App\Exceptions\Tiendanube\TiendanubeOperacionConflictException;
use App\Services\Tiendanube\Precios\Aplicacion\TiendanubePrecioEjecucionService;
use App\Services\Tiendanube\TiendanubeOperacionTiendaService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\RefreshDatabaseSafe;
use Tests\Support\TiendanubePrecioMotorFixtures;
use Tests\TestCase;

class TiendanubePrecioEjecucionTest extends TestCase
{
    use RefreshDatabaseSafe;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        config([
            'tiendanube.api_base' => null,
            'tiendanube.api_host' => 'https://api.tiendanube.com',
            'tiendanube.api_version' => '2025-03',
            'tiendanube.retry_sleep_ms' => 0,
            'tiendanube.precios_aplicacion_habilitada' => true,
            'tiendanube.precio_lote_sync_max' => 200,
        ]);

        TiendanubeConfiguracion::obtener()->fill([
            'store_id' => 8004291,
            'app_id' => '37163',
            'access_token' => Crypt::encryptString('token-test'),
            'config_generation' => 1,
        ])->save();

        foreach ([
            'tiendanube.ver',
            'tiendanube.precios.ver',
            'tiendanube.precios.editar',
            'tiendanube.precios.reglas.ver',
            'tiendanube.precios.reglas.administrar',
            'tiendanube.precios.aprobar',
            'tiendanube.precios.aplicar',
        ] as $perm) {
            Permission::findOrCreate($perm, 'web');
        }

        $this->user = User::factory()->create();
        $this->user->givePermissionTo([
            'tiendanube.ver',
            'tiendanube.precios.ver',
            'tiendanube.precios.editar',
            'tiendanube.precios.reglas.ver',
            'tiendanube.precios.reglas.administrar',
            'tiendanube.precios.aprobar',
            'tiendanube.precios.aplicar',
        ]);

        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    public function test_aplicar_dos_veces_no_duplica_ejecucion(): void
    {
        Queue::fake();
        Http::fake();
        $this->crearVariante(1000, 100, '100.00');
        $lote = $this->aprobarLote([1000]);

        $primero = $this->actingAs($this->user)
            ->postJson(route('tiendanube.precios.lotes.aplicar', $lote['lote_id']), [
                'checksum' => $lote['revision']['checksum'],
            ])
            ->assertCreated();

        $segundo = $this->actingAs($this->user)
            ->postJson(route('tiendanube.precios.lotes.aplicar', $lote['lote_id']), [
                'checksum' => $lote['revision']['checksum'],
            ])
            ->assertSuccessful();

        $this->assertSame($primero->json('id'), $segundo->json('id'));
        $this->assertSame(1, TiendanubePrecioEjecucion::query()->count());
        Queue::assertPushed(ProcesarPrecioEjecucionJob::class, 1);
        Http::assertNothingSent();
    }

    public function test_generacion_cambiada_no_escribe(): void
    {
        Queue::fake();
        Http::fake();
        $this->crearVariante(1000, 100, '100.00');
        $lote = $this->aprobarLote([1000]);
        TiendanubeConfiguracion::obtener()->increment('config_generation');

        $this->actingAs($this->user)
            ->postJson(route('tiendanube.precios.lotes.aplicar', $lote['lote_id']), [
                'checksum' => $lote['revision']['checksum'],
            ])
            ->assertStatus(409);

        Http::assertNothingSent();
        $this->assertSame(0, TiendanubePrecioEjecucion::query()->count());
    }

    public function test_variante_ajena_cero_escrituras(): void
    {
        Queue::fake();
        $this->crearVariante(1000, 100, '100.00');
        $lote = $this->aprobarLote([1000]);
        TiendanubeProductoVariante::query()->where('id', 1000)->delete();

        $puts = 0;
        Http::fake(function (Request $request) use (&$puts) {
            if ($request->method() === 'PUT') {
                $puts++;
            }

            return Http::response($this->productoRemoto(100, 1000, '100.00'), 200);
        });

        $this->actingAs($this->user)
            ->postJson(route('tiendanube.precios.lotes.aplicar', $lote['lote_id']), [
                'checksum' => $lote['revision']['checksum'],
            ])
            ->assertCreated();

        $job = new ProcesarPrecioEjecucionJob(TiendanubePrecioEjecucion::query()->value('id'));
        $job->handle(app(TiendanubePrecioEjecucionService::class));

        $this->assertSame(0, $puts);
        $item = TiendanubePrecioEjecucionItem::query()->first();
        $this->assertSame(TiendanubePrecioEjecucionItem::ESTADO_FALLIDO, $item->estado);
    }

    public function test_cambio_remoto_genera_conflicto_sin_put(): void
    {
        Queue::fake();
        $this->crearVariante(1000, 100, '100.00');
        $lote = $this->aprobarLote([1000]);
        $puts = [];
        Http::fake(function (Request $request) use (&$puts) {
            if ($request->method() === 'PUT') {
                $puts[] = $request->data();
            }

            return Http::response($this->productoRemoto(100, 1000, '250.00'), 200);
        });

        $this->aplicarYProcesar($lote);
        $item = TiendanubePrecioEjecucionItem::query()->first();
        $this->assertSame(TiendanubePrecioEjecucionItem::ESTADO_CONFLICTO, $item->estado);
        $this->assertSame([], $puts);
        $ejecucion = TiendanubePrecioEjecucion::query()->first();
        $this->assertSame(TiendanubePrecioEjecucion::ESTADO_PARCIAL, $ejecucion->estado);
        $this->assertFalse((bool) $this->actingAs($this->user)
            ->getJson(route('tiendanube.precios.lotes.ejecucion', $lote['lote_id']))
            ->json('exito_global'));
    }

    public function test_timeout_con_resultado_aplicado_se_confirma_por_relectura(): void
    {
        Queue::fake();
        $this->crearVariante(1000, 100, '100.00');
        $lote = $this->aprobarLote([1000]);
        $puts = 0;
        $gets = 0;
        Http::fake(function (Request $request) use (&$puts, &$gets) {
            if ($request->method() === 'PUT') {
                $puts++;

                return Http::response(['error' => 'gateway'], 504);
            }

            $gets++;

            return Http::response($this->productoRemoto(100, 1000, $gets === 1 ? '100.00' : '110.00'), 200);
        });

        $this->aplicarYProcesar($lote);
        $item = TiendanubePrecioEjecucionItem::query()->first();
        $this->assertSame(1, $puts);
        $this->assertSame(TiendanubePrecioEjecucionItem::ESTADO_CONFIRMADO, $item->estado);
        $this->assertSame('110.00', $item->valor_confirmado['normal']);
    }

    public function test_cancelacion_conserva_confirmados_y_detiene_pendientes(): void
    {
        Queue::fake();
        $this->crearVariante(1000, 100, '100.00');
        $this->crearVariante(1001, 101, '200.00');
        $lote = $this->aprobarLote([1000, 1001]);

        $res = $this->actingAs($this->user)
            ->postJson(route('tiendanube.precios.lotes.aplicar', $lote['lote_id']), [
                'checksum' => $lote['revision']['checksum'],
            ])
            ->assertCreated();

        $ejecucionId = $res->json('id');
        $primero = TiendanubePrecioEjecucionItem::query()->where('variante_id', 1000)->first();
        $primero->update(['estado' => TiendanubePrecioEjecucionItem::ESTADO_CONFIRMADO]);

        $this->actingAs($this->user)
            ->postJson(route('tiendanube.precios.ejecuciones.cancelar', $ejecucionId))
            ->assertOk();

        $this->assertSame(
            TiendanubePrecioEjecucionItem::ESTADO_CONFIRMADO,
            TiendanubePrecioEjecucionItem::query()->where('variante_id', 1000)->value('estado')
        );
        $this->assertSame(
            TiendanubePrecioEjecucionItem::ESTADO_CANCELADO,
            TiendanubePrecioEjecucionItem::query()->where('variante_id', 1001)->value('estado')
        );

        Http::fake();
        $job = new ProcesarPrecioEjecucionJob($ejecucionId);
        $job->handle(app(TiendanubePrecioEjecucionService::class));
        Http::assertNothingSent();
    }

    public function test_payload_publicado_coincide_con_snapshot_sin_stock(): void
    {
        Queue::fake();
        $this->crearVariante(1000, 100, '100.00');
        $lote = $this->aprobarLote([1000]);
        $puts = [];
        Http::fake(function (Request $request) use (&$puts) {
            if ($request->method() === 'PUT') {
                $puts[] = $request->data();

                return Http::response(['id' => 1000, 'price' => '110.00'], 200);
            }

            return Http::response($this->productoRemoto(100, 1000, $puts === [] ? '100.00' : '110.00'), 200);
        });

        $this->aplicarYProcesar($lote);
        $this->assertCount(1, $puts);
        $this->assertSame(['price' => '110.00'], $puts[0]);
        $this->assertArrayNotHasKey('stock', $puts[0]);
        $this->assertArrayNotHasKey('sku', $puts[0]);
        $this->assertArrayNotHasKey('name', $puts[0]);
        $this->assertArrayNotHasKey('categories', $puts[0]);
        $item = TiendanubePrecioEjecucionItem::query()->first();
        $this->assertSame(TiendanubePrecioEjecucionItem::ESTADO_CONFIRMADO, $item->estado);
        $this->assertSame('110.00', $item->valor_confirmado['normal']);
        $this->assertEquals(110.0, (float) TiendanubeProductoVariante::find(1000)?->getRawOriginal('price'));
        $this->assertEquals(5, (int) TiendanubeProductoVariante::find(1000)?->stock);
    }

    public function test_sync_activo_rechaza_la_aplicacion(): void
    {
        Queue::fake();
        Http::fake();
        $this->crearVariante(1000, 100, '100.00');
        $lote = $this->aprobarLote([1000]);
        app(TiendanubeOperacionTiendaService::class)->adquirirExclusiva(
            8004291,
            TiendanubeOperacionTiendaService::TIPO_CATALOGO_SYNC,
            (int) $this->user->id,
            1
        );

        $this->actingAs($this->user)
            ->postJson(route('tiendanube.precios.lotes.aplicar', $lote['lote_id']), [
                'checksum' => $lote['revision']['checksum'],
            ])
            ->assertStatus(409)
            ->assertJsonPath('codigo', 'operacion_tienda');

        Http::assertNothingSent();
        $this->assertSame(0, TiendanubePrecioEjecucion::query()->count());
    }

    public function test_aplicacion_de_precios_bloquea_escritura_manual_e_imagenes(): void
    {
        $ops = app(TiendanubeOperacionTiendaService::class);
        $ops->adquirirExclusiva(8004291, TiendanubeOperacionTiendaService::TIPO_PRECIO_APLICACION, (int) $this->user->id, 1);

        foreach ([
            TiendanubeOperacionTiendaService::TIPO_PRODUCTO_WRITE,
            TiendanubeOperacionTiendaService::TIPO_IMAGE_IMPORT,
            TiendanubeOperacionTiendaService::TIPO_CATALOGO_WIPE,
        ] as $tipo) {
            try {
                $ops->assertAdmisible($tipo, 8004291);
                $this->fail('Debió rechazar '.$tipo);
            } catch (TiendanubeOperacionConflictException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_default_deshabilitado_no_admite_y_el_valor_explicito_si(): void
    {
        Queue::fake();
        Http::fake();
        $this->crearVariante(1000, 100, '100.00');
        $lote = $this->aprobarLote([1000]);

        config(['tiendanube.precios_aplicacion_habilitada' => false]);
        $this->actingAs($this->user)
            ->postJson(route('tiendanube.precios.lotes.aplicar', $lote['lote_id']), [
                'checksum' => $lote['revision']['checksum'],
            ])
            ->assertStatus(403)
            ->assertJsonPath('codigo', 'deshabilitado');

        config(['tiendanube.precios_aplicacion_habilitada' => true]);
        $this->actingAs($this->user)
            ->postJson(route('tiendanube.precios.lotes.aplicar', $lote['lote_id']), [
                'checksum' => $lote['revision']['checksum'],
            ])
            ->assertCreated();
        Http::assertNothingSent();
    }

    public function test_archivo_de_configuracion_declara_default_falso(): void
    {
        $src = (string) file_get_contents(config_path('tiendanube.php'));
        $this->assertStringContainsString("env('TIENDANUBE_PRECIOS_APLICACION_HABILITADA', false)", $src);
    }

    public function test_timeout_con_origen_no_repite_el_put_en_el_mismo_turno(): void
    {
        Queue::fake();
        $this->crearVariante(1000, 100, '100.00');
        $lote = $this->aprobarLote([1000]);
        $puts = 0;
        Http::fake(function (Request $request) use (&$puts) {
            if ($request->method() === 'PUT') {
                $puts++;

                return Http::response(['error' => 'gateway'], 504);
            }

            return Http::response($this->productoRemoto(100, 1000, '100.00'), 200);
        });

        $this->aplicarYProcesar($lote);
        $item = TiendanubePrecioEjecucionItem::query()->first();
        $this->assertSame(1, $puts);
        $this->assertSame(TiendanubePrecioEjecucionItem::ESTADO_REINTENTO_PENDIENTE, $item->estado);
        $this->assertNotNull($item->siguiente_intento_at);
    }

    public function test_reintentar_incierto_reconcilia_sin_otro_put(): void
    {
        Queue::fake();
        $this->crearVariante(1000, 100, '100.00');
        $lote = $this->aprobarLote([1000]);
        $res = $this->actingAs($this->user)
            ->postJson(route('tiendanube.precios.lotes.aplicar', $lote['lote_id']), [
                'checksum' => $lote['revision']['checksum'],
            ])
            ->assertCreated();
        $item = TiendanubePrecioEjecucionItem::query()->first();
        $item->update([
            'estado' => TiendanubePrecioEjecucionItem::ESTADO_RESULTADO_INCIERTO,
        ]);

        $puts = 0;
        Http::fake(function (Request $request) use (&$puts) {
            if ($request->method() === 'PUT') {
                $puts++;
            }

            return Http::response($this->productoRemoto(100, 1000, '100.00'), 200);
        });

        $this->actingAs($this->user)
            ->postJson(route('tiendanube.precios.ejecuciones.items.reintentar', [$res->json('id'), $item->id]))
            ->assertOk();

        $this->assertSame(0, $puts);
        $item->refresh();
        $this->assertSame(TiendanubePrecioEjecucionItem::ESTADO_REINTENTO_PENDIENTE, $item->estado);
        Queue::assertPushed(ProcesarPrecioEjecucionJob::class);
    }

    public function test_relectura_de_vigencia_no_cobra_otro_turno_de_clase(): void
    {
        Queue::fake();
        config([
            'tiendanube.gobernador_activo' => true,
            'tiendanube.gobernador_intervalo_ms' => 0,
        ]);
        $gobernador = TiendanubeGobernador::conectarPrueba('tn-gov-test-'.bin2hex(random_bytes(4)));
        $this->app->instance(TiendanubeGobernador::class, $gobernador);

        try {
            $this->crearVariante(1000, 100, '100.00');
            $lote = $this->aprobarLote([1000]);
            $puts = 0;
            Http::fake(function (Request $request) use (&$puts) {
                if ($request->method() === 'PUT') {
                    $puts++;

                    return Http::response($this->productoRemoto(100, 1000, '110.00'), 200);
                }

                return Http::response($this->productoRemoto(100, 1000, '100.00'), 200);
            });

            $this->aplicarYProcesar($lote);
            $this->assertSame(1, $puts);
            $this->assertSame(['publicacion'], $gobernador->ventana(8004291));
        } finally {
            $gobernador->olvidar();
        }
    }

    public function test_cada_reintento_interno_toma_turno_y_el_429_no_es_presupuesto_local(): void
    {
        Queue::fake();
        config([
            'tiendanube.gobernador_activo' => true,
            'tiendanube.gobernador_intervalo_ms' => 0,
        ]);
        $gobernador = TiendanubeGobernador::conectarPrueba('tn-gov-test-'.bin2hex(random_bytes(4)));
        $this->app->instance(TiendanubeGobernador::class, $gobernador);
        $cliente = app(TiendanubeApiClient::class);

        try {
            $paso = 0;
            $modo = 'reintento';
            Http::fake(function () use (&$paso, &$modo) {
                if ($modo === 'limite') {
                    return Http::response(['error' => 'rate'], 429, ['x-rate-limit-reset' => '25000']);
                }
                $paso++;
                if ($paso === 1) {
                    return Http::response(['error' => 'gateway'], 503);
                }

                return Http::response(['id' => 1, 'variants' => []], 200);
            });
            app(TiendanubeContextoHttpActual::class)->usar(
                'op-reintento',
                'catalogo',
                'intento',
                'sin_escritura',
                fn () => $cliente->getProduct(1),
            );
            $this->assertCount(2, $gobernador->ventana(8004291));

            $modo = 'limite';
            $antes = (int) floor(microtime(true) * 1000);
            try {
                app(TiendanubeContextoHttpActual::class)->usar(
                    'op-429',
                    'catalogo',
                    'intento',
                    'escritura_enviada',
                    fn () => $cliente->getProduct(1),
                );
                $this->fail('El 429 debía quedar como límite remoto.');
            } catch (TiendanubeApiRateLimitException $e) {
                $this->assertSame(429, $e->statusCode);
            }
            $espera = $gobernador->espera(8004291, 'op-429');
            $this->assertSame('rate_limit', $espera['motivo']);
            $this->assertSame('escritura_enviada', $espera['fase']);
            $this->assertGreaterThan(10_000, $espera['reanudar_despues_at'] - $antes);

            try {
                app(TiendanubeContextoHttpActual::class)->usar(
                    'op-local',
                    'catalogo',
                    'intento',
                    'lectura_hecha',
                    fn () => $cliente->getProduct(1),
                );
                $this->fail('El cupo remoto vigente no es un presupuesto local.');
            } catch (TiendanubeApiRateLimitException) {
                $this->assertSame('rate_limit', $gobernador->espera(8004291, 'op-local')['motivo']);
                $this->assertSame('lectura_hecha', $gobernador->espera(8004291, 'op-local')['fase']);
            }

            Queue::assertNothingPushed();
        } finally {
            $gobernador->olvidar();
        }
    }

    public function test_sin_presupuesto_local_no_sale_a_la_red(): void
    {
        Queue::fake();
        config([
            'tiendanube.gobernador_activo' => true,
            'tiendanube.gobernador_intervalo_ms' => 60_000,
        ]);
        $gobernador = TiendanubeGobernador::conectarPrueba('tn-gov-test-'.bin2hex(random_bytes(4)));
        $this->app->instance(TiendanubeGobernador::class, $gobernador);
        $cliente = app(TiendanubeApiClient::class);

        try {
            Http::fake(['*' => Http::response(['id' => 1, 'variants' => []], 200)]);
            $contexto = app(TiendanubeContextoHttpActual::class);
            $contexto->usar('op-local-1', 'interactivo', 'intento', 'sin_escritura', fn () => $cliente->getProduct(1));
            try {
                $contexto->usar('op-local-2', 'interactivo', 'intento', 'lectura_hecha', fn () => $cliente->getProduct(1));
                $this->fail('El segundo intento debía esperar presupuesto local.');
            } catch (TiendanubePresupuestoLocalException $e) {
                $this->assertSame('sin_presupuesto_local', $e->turno->motivo);
                $this->assertNotSame('rate_limit', $e->turno->motivo);
            }
            Http::assertSentCount(1);
            Queue::assertNothingPushed();
        } finally {
            $gobernador->olvidar();
        }
    }

    public function test_la_reserva_reanuda_el_put_conservando_la_lectura(): void
    {
        Queue::fake();
        config([
            'tiendanube.gobernador_activo' => true,
            'tiendanube.gobernador_intervalo_ms' => 1000,
        ]);
        $gobernador = TiendanubeGobernador::conectarPrueba('tn-gov-test-'.bin2hex(random_bytes(4)));
        $inicio = 1_700_000_000_000;
        $gobernador->fijarAhora($inicio);
        $this->app->instance(TiendanubeGobernador::class, $gobernador);
        $gets = 0;
        $puts = 0;

        try {
            $this->crearVariante(1000, 100, '100.00');
            $lote = $this->aprobarLote([1000]);
            Http::fake(function (Request $request) use (&$gets, &$puts) {
                if ($request->method() === 'PUT') {
                    $puts++;

                    return Http::response($this->productoRemoto(100, 1000, '110.00'), 200);
                }
                $gets++;

                return Http::response($this->productoRemoto(100, 1000, '100.00'), 200);
            });
            $ejecucionId = $this->actingAs($this->user)
                ->postJson(route('tiendanube.precios.lotes.aplicar', $lote['lote_id']), [
                    'checksum' => $lote['revision']['checksum'],
                ])
                ->assertCreated()
                ->json('id');
            $processor = app(TiendanubePrecioEjecucionItemProcessor::class);

            $this->reanudarItem($processor, $ejecucionId);
            $this->assertSame(0, $gets);
            $this->assertSame(0, $puts);

            $gobernador->fijarAhora($inicio + 1000);
            $this->reanudarItem($processor, $ejecucionId);
            $this->assertSame(1, $gets);
            $this->assertSame(0, $puts);
            $item = TiendanubePrecioEjecucionItem::query()->where('ejecucion_id', $ejecucionId)->first();
            $this->assertSame('comprobacion_vigencia', $item->evidencia['lectura_vigente']['proposito'] ?? null);
            $this->assertSame('lectura_hecha', $item->evidencia['lectura_vigente']['fase'] ?? null);
            $this->assertSame('sin_presupuesto_local', $item->evidencia['motivo'] ?? null);

            $gobernador->fijarAhora($inicio + 2000);
            $this->reanudarItem($processor, $ejecucionId);
            $this->assertSame(1, $gets);
            $this->assertSame(1, $puts);
            $item->refresh();
            $this->assertSame(TiendanubePrecioEjecucionItem::ESTADO_CONFIRMADO, $item->estado);
            $this->assertContains('publicacion', $gobernador->ventana(8004291));
            $this->assertSame([], $gobernador->clasesConDemanda(8004291, $inicio + 2000));
        } finally {
            $gobernador->olvidar();
        }
    }

    public function test_verificar_recupera_con_su_clase_y_la_interrupcion_retira_la_demanda(): void
    {
        Queue::fake();
        config([
            'tiendanube.gobernador_activo' => true,
            'tiendanube.gobernador_intervalo_ms' => 0,
        ]);
        $gobernador = TiendanubeGobernador::conectarPrueba('tn-gov-test-'.bin2hex(random_bytes(4)));
        $this->app->instance(TiendanubeGobernador::class, $gobernador);

        try {
            $this->crearVariante(1000, 100, '100.00');
            $lote = $this->aprobarLote([1000]);
            $ejecucionId = $this->actingAs($this->user)
                ->postJson(route('tiendanube.precios.lotes.aplicar', $lote['lote_id']), [
                    'checksum' => $lote['revision']['checksum'],
                ])
                ->assertCreated()
                ->json('id');
            $item = TiendanubePrecioEjecucionItem::query()->where('ejecucion_id', $ejecucionId)->first();
            $item->update(['estado' => TiendanubePrecioEjecucionItem::ESTADO_RESULTADO_INCIERTO]);
            Http::fake(fn () => Http::response($this->productoRemoto(100, 1000, '100.00'), 200));

            $this->actingAs($this->user)
                ->postJson(route('tiendanube.precios.ejecuciones.items.reintentar', [$ejecucionId, $item->id]))
                ->assertOk();

            $this->assertSame(['recuperacion'], array_values(array_unique($gobernador->ventana(8004291))));
            $this->assertSame([], $gobernador->clasesConDemanda(8004291));

            $gobernador->olvidar();
            Http::fake(fn () => Http::response(['error' => 'gateway'], 503));
            $item->update([
                'estado' => TiendanubePrecioEjecucionItem::ESTADO_PENDIENTE,
                'siguiente_intento_at' => null,
                'intentos' => 0,
            ]);
            app(TiendanubePrecioEjecucionItemProcessor::class)->procesar($item->fresh());
            $this->assertSame([], $gobernador->clasesConDemanda(8004291));
            $this->assertNotContains('catalogo', $gobernador->ventana(8004291));

            $gobernador->anunciarDemanda(8004291, 'publicacion', 'precio:'.$item->id);
            app(TiendanubePrecioEjecucionService::class)->cancelar($ejecucionId, 8004291, $this->user->id);
            $this->assertNotContains('publicacion', $gobernador->clasesConDemanda(8004291));
        } finally {
            $gobernador->olvidar();
        }
    }

    public function test_la_escritura_manual_se_clasifica_como_interactiva(): void
    {
        config([
            'tiendanube.gobernador_activo' => true,
            'tiendanube.gobernador_intervalo_ms' => 0,
        ]);
        TiendanubeConfiguracion::obtener()->update(['locations_probe' => 'ok']);
        $gobernador = TiendanubeGobernador::conectarPrueba('tn-gov-test-'.bin2hex(random_bytes(4)));
        $this->app->instance(TiendanubeGobernador::class, $gobernador);

        try {
            $this->crearVariante(1000, 100, '100.00');
            Http::fake(fn () => Http::response($this->productoRemoto(100, 1000, '100.00'), 200));
            app(TiendanubeProductoWriteService::class)->aplicarStock(TiendanubeStockPayload::fromArray([
                'store_id' => 8004291,
                'product_id' => 100,
                'variant_id' => 1000,
                'stock_management' => false,
                'mode' => TiendanubeStockPayload::MODE_SET_STOCK_MANAGEMENT,
            ]));
            $this->assertSame(['interactivo'], array_values(array_unique($gobernador->ventana(8004291))));
            $this->assertSame([], $gobernador->clasesConDemanda(8004291));

            try {
                app(TiendanubeEmisorHttp::class)->bajo(
                    TiendanubeClaseTurno::Publicacion,
                    'precio:corte',
                    'sin_escritura',
                    function (): void {
                        throw new \RuntimeException('corte');
                    },
                );
                $this->fail('La interrupción debía propagarse.');
            } catch (\RuntimeException $e) {
                $this->assertSame('corte', $e->getMessage());
            }
            $this->assertSame([], $gobernador->clasesConDemanda(8004291));
        } finally {
            $gobernador->olvidar();
        }
    }

    public function test_revertir_mantiene_la_reconciliacion_en_el_procesador(): void
    {
        Queue::fake();
        config([
            'tiendanube.gobernador_activo' => true,
            'tiendanube.gobernador_intervalo_ms' => 0,
            'tiendanube.colas_dedicadas' => true,
        ]);
        $gobernador = TiendanubeGobernador::conectarPrueba('tn-gov-test-'.bin2hex(random_bytes(4)));
        $this->app->instance(TiendanubeGobernador::class, $gobernador);
        $enrutador = app(TiendanubeEnrutadorColas::class);

        try {
            $enrutador->despacharPrecio('ejec-dedicada');
            Queue::assertPushed(ProcesarPrecioEjecucionJob::class, function (ProcesarPrecioEjecucionJob $job): bool {
                return $job->queue === 'tiendanube_publicacion' && $job->connection === 'tiendanube';
            });

            $gobernador->revertirRecorridoADefault();
            $enrutador->despacharPrecio('ejec-default');
            Queue::assertPushed(ProcesarPrecioEjecucionJob::class, function (ProcesarPrecioEjecucionJob $job): bool {
                return $job->queue === null && $job->connection === null;
            });

            $this->crearVariante(1000, 100, '100.00');
            $lote = $this->aprobarLote([1000]);
            $puts = 0;
            Http::fake(function (Request $request) use (&$puts) {
                if ($request->method() === 'PUT') {
                    $puts++;

                    return Http::response(['error' => 'gateway'], 504);
                }

                return Http::response($this->productoRemoto(100, 1000, '100.00'), 200);
            });
            $this->aplicarYProcesar($lote);
            $this->assertSame(1, $puts);
            $item = TiendanubePrecioEjecucionItem::query()->first();
            $this->assertSame(TiendanubePrecioEjecucionItem::ESTADO_REINTENTO_PENDIENTE, $item->estado);
            $espera = $gobernador->espera(8004291, 'precio:'.$item->id);
            $this->assertSame('escritura_enviada', $espera['fase']);
            $this->assertSame('comprobacion_vigencia', $espera['evidencia']['proposito'] ?? null);
            $this->assertSame('comprobacion_vigencia', $item->evidencia['lectura_vigente']['proposito'] ?? null);
            $this->assertSame('lectura_hecha', $item->evidencia['lectura_vigente']['fase'] ?? null);
        } finally {
            config(['tiendanube.colas_dedicadas' => false]);
            $gobernador->olvidar();
        }
    }

    public function test_lectura_previa_fallida_no_escribe(): void
    {
        Queue::fake();
        $this->crearVariante(1000, 100, '100.00');
        $lote = $this->aprobarLote([1000]);
        $puts = 0;
        Http::fake(function (Request $request) use (&$puts) {
            if ($request->method() === 'PUT') {
                $puts++;

                return Http::response(['ok' => true], 200);
            }

            return Http::response(['error' => 'gateway'], 503);
        });

        $this->aplicarYProcesar($lote);
        $item = TiendanubePrecioEjecucionItem::query()->first();
        $this->assertSame(0, $puts);
        $this->assertSame(TiendanubePrecioEjecucionItem::ESTADO_REINTENTO_PENDIENTE, $item->estado);
        $this->assertFalse((bool) ($item->evidencia['escritura'] ?? true));
        $this->assertSame('sin_escritura', $item->evidencia['fase'] ?? null);
    }

    public function test_espera_prolongada_persiste_la_continuacion_sin_trabajo_inmediato(): void
    {
        Queue::fake();
        config([
            'tiendanube.gobernador_activo' => true,
            'tiendanube.colas_dedicadas' => false,
            'tiendanube.gobernador_intervalo_ms' => 60_000,
            'tiendanube.gobernador_demanda_margen_ms' => 30_000,
        ]);
        $prefijo = 'tn-gov-test-'.bin2hex(random_bytes(4));
        $gobernador = TiendanubeGobernador::conectarPrueba($prefijo);
        $inicio = 1_700_000_000_000;
        $gobernador->fijarAhora($inicio);
        $this->app->instance(TiendanubeGobernador::class, $gobernador);

        try {
            $this->crearVariante(1000, 100, '100.00');
            $lote = $this->aprobarLote([1000]);
            $puts = 0;
            Http::fake(function (Request $request) use (&$puts) {
                if ($request->method() === 'PUT') {
                    $puts++;
                }

                return Http::response($this->productoRemoto(100, 1000, '100.00'), 200);
            });
            $ejecucionId = $this->actingAs($this->user)
                ->postJson(route('tiendanube.precios.lotes.aplicar', $lote['lote_id']), [
                    'checksum' => $lote['revision']['checksum'],
                ])
                ->assertCreated()
                ->json('id');
            $inmediatosAlAplicar = $this->contarTrabajosPrecio(false);

            app(TiendanubePrecioEjecucionService::class)->procesarLote($ejecucionId);

            $this->assertSame(0, $puts);
            $item = TiendanubePrecioEjecucionItem::query()->where('ejecucion_id', $ejecucionId)->firstOrFail();
            $this->assertSame(TiendanubePrecioEjecucionItem::ESTADO_REINTENTO_PENDIENTE, $item->estado);
            $this->assertSame(0, (int) $item->intentos);
            $this->assertSame('sin_presupuesto_local', $item->evidencia['categoria'] ?? null);
            $this->assertGreaterThan($inicio + 30_000, (int) ($item->evidencia['continuacion']['reanudar_despues_at'] ?? 0));
            $this->assertSame($inmediatosAlAplicar, $this->contarTrabajosPrecio(false));
            $this->assertSame(1, $this->contarTrabajosPrecio(true));
            $demorado = Queue::pushed(ProcesarPrecioEjecucionJob::class)->first(fn (ProcesarPrecioEjecucionJob $job) => $job->delay !== null);
            $this->assertNotNull($demorado);
            $this->assertTrue($demorado->delay->greaterThan(now()->addSeconds(50)));
            $this->assertNotSame('tiendanube_publicacion', $demorado->queue);
            $this->assertContains('publicacion', $gobernador->clasesConDemanda(8004291, $inicio));
            $this->assertSame([], $gobernador->clasesConDemanda(8004291, $inicio + 90_001));

            $gobernador->anunciarDemanda(8004291, 'publicacion', 'precio:'.$item->id, $inicio + 120_000);
            app(TiendanubePrecioEjecucionService::class)->cancelar($ejecucionId, 8004291, $this->user->id);
            $this->assertNotContains('publicacion', $gobernador->clasesConDemanda(8004291, $inicio));
        } finally {
            $gobernador->olvidar();
        }
    }

    public function test_lectura_caducada_o_de_otra_configuracion_se_renueva_sin_otro_turno_de_clase(): void
    {
        Queue::fake();
        config([
            'tiendanube.gobernador_activo' => true,
            'tiendanube.gobernador_intervalo_ms' => 1000,
            'tiendanube.gobernador_lectura_max_ms' => 1_500,
        ]);
        $gobernador = TiendanubeGobernador::conectarPrueba('tn-gov-test-'.bin2hex(random_bytes(4)));
        $inicio = 1_700_000_000_000;
        $gobernador->fijarAhora($inicio);
        $this->app->instance(TiendanubeGobernador::class, $gobernador);
        $gets = 0;
        $puts = 0;

        try {
            $this->crearVariante(1000, 100, '100.00');
            $lote = $this->aprobarLote([1000]);
            Http::fake(function (Request $request) use (&$gets, &$puts) {
                if ($request->method() === 'PUT') {
                    $puts++;

                    return Http::response($this->productoRemoto(100, 1000, '110.00'), 200);
                }
                $gets++;

                return Http::response($this->productoRemoto(100, 1000, '100.00'), 200);
            });
            $ejecucionId = $this->solicitarAplicacion($lote);
            $processor = app(TiendanubePrecioEjecucionItemProcessor::class);
            $this->reanudarItem($processor, $ejecucionId);

            $gobernador->fijarAhora($inicio + 1000);
            $this->reanudarItem($processor, $ejecucionId);
            $this->assertSame(1, $gets);
            $this->assertSame(0, $puts);

            $gobernador->fijarAhora($inicio + 2501);
            $this->reanudarItem($processor, $ejecucionId);
            $this->assertSame(2, $gets);
            $this->assertSame(0, $puts);
            $this->assertCount(1, $gobernador->ventana(8004291));

            $gobernador->fijarAhora($inicio + 3501);
            $this->reanudarItem($processor, $ejecucionId);
            $this->assertSame(2, $gets);
            $this->assertSame(1, $puts);
            $this->assertCount(1, $gobernador->ventana(8004291));
            app(TiendanubePrecioEjecucionService::class)->refrescarContadores(
                TiendanubePrecioEjecucion::query()->findOrFail($ejecucionId)
            );

            $gobernador->olvidar();
            $gets = 0;
            $puts = 0;
            $gobernador->fijarAhora($inicio);
            TiendanubeProductoVariante::query()->whereKey(1000)->update(['price' => '100.00']);
            $loteConfiguracion = $this->aprobarLote([1000]);
            $ejecucionId = $this->solicitarAplicacion($loteConfiguracion);
            $this->reanudarItem($processor, $ejecucionId);
            $gobernador->fijarAhora($inicio + 1000);
            $this->reanudarItem($processor, $ejecucionId);
            $this->assertSame(1, $gets);
            TiendanubePrecioEjecucion::query()->whereKey($ejecucionId)->update(['config_generation' => 2]);
            TiendanubeConfiguracion::obtener()->update(['config_generation' => 2]);
            config(['tiendanube.gobernador_lectura_max_ms' => 30_000]);

            $gobernador->fijarAhora($inicio + 2000);
            $this->reanudarItem($processor, $ejecucionId);
            $this->assertSame(2, $gets);
            $this->assertSame(0, $puts);
            $this->assertCount(1, $gobernador->ventana(8004291));

            $gobernador->fijarAhora($inicio + 3000);
            $this->reanudarItem($processor, $ejecucionId);
            $this->assertSame(1, $puts);
            $this->assertCount(1, $gobernador->ventana(8004291));
        } finally {
            $gobernador->olvidar();
        }
    }

    public function test_denegaciones_locales_no_agotan_los_intentos_de_envio(): void
    {
        Queue::fake();
        config([
            'tiendanube.gobernador_activo' => true,
            'tiendanube.gobernador_intervalo_ms' => 1000,
            'tiendanube.precio_ejecucion_item_max_intentos' => 3,
        ]);
        $gobernador = TiendanubeGobernador::conectarPrueba('tn-gov-test-'.bin2hex(random_bytes(4)));
        $inicio = 1_700_000_000_000;
        $gobernador->fijarAhora($inicio);
        $this->app->instance(TiendanubeGobernador::class, $gobernador);
        $puts = 0;

        try {
            $this->crearVariante(1000, 100, '100.00');
            $lote = $this->aprobarLote([1000]);
            Http::fake(function (Request $request) use (&$puts) {
                if ($request->method() === 'PUT') {
                    $puts++;

                    return Http::response($this->productoRemoto(100, 1000, '110.00'), 200);
                }

                return Http::response($this->productoRemoto(100, 1000, '100.00'), 200);
            });
            $ejecucionId = $this->solicitarAplicacion($lote);
            $processor = app(TiendanubePrecioEjecucionItemProcessor::class);

            for ($vez = 0; $vez < 6; $vez++) {
                $this->reanudarItem($processor, $ejecucionId);
            }

            $item = TiendanubePrecioEjecucionItem::query()->where('ejecucion_id', $ejecucionId)->firstOrFail();
            $this->assertSame(TiendanubePrecioEjecucionItem::ESTADO_REINTENTO_PENDIENTE, $item->estado);
            $this->assertSame(0, (int) $item->intentos);
            $this->assertSame(0, (int) ($item->evidencia['contadores']['envios'] ?? 0));
            $this->assertSame(6, (int) ($item->evidencia['contadores']['esperas'] ?? 0));
            $this->assertSame(0, $puts);
            $this->assertSame(1, $this->contarTrabajosPrecio(true));

            $gobernador->fijarAhora($inicio + 1000);
            $this->reanudarItem($processor, $ejecucionId);
            $gobernador->fijarAhora($inicio + 2000);
            $this->reanudarItem($processor, $ejecucionId);
            $item->refresh();
            $this->assertSame(1, $puts);
            $this->assertSame(1, (int) $item->intentos);
            $this->assertSame(TiendanubePrecioEjecucionItem::ESTADO_CONFIRMADO, $item->estado);
        } finally {
            $gobernador->olvidar();
        }
    }

    public function test_la_continuacion_persistida_se_lee_tras_reiniciar_y_no_se_duplica(): void
    {
        Queue::fake();
        config([
            'tiendanube.gobernador_activo' => true,
            'tiendanube.gobernador_intervalo_ms' => 60_000,
            'tiendanube.colas_dedicadas' => false,
        ]);
        $prefijo = 'tn-gov-test-'.bin2hex(random_bytes(4));
        $gobernador = TiendanubeGobernador::conectarPrueba($prefijo);
        $inicio = 1_700_000_000_000;
        $gobernador->fijarAhora($inicio);
        $this->app->instance(TiendanubeGobernador::class, $gobernador);

        try {
            $this->crearVariante(1000, 100, '100.00');
            $lote = $this->aprobarLote([1000]);
            Http::fake(fn () => Http::response($this->productoRemoto(100, 1000, '100.00'), 200));
            $ejecucionId = $this->solicitarAplicacion($lote);
            $processor = app(TiendanubePrecioEjecucionItemProcessor::class);
            $this->reanudarItem($processor, $ejecucionId);
            $this->reanudarItem($processor, $ejecucionId);

            $item = TiendanubePrecioEjecucionItem::query()->where('ejecucion_id', $ejecucionId)->firstOrFail();
            $this->assertSame(1, $this->contarTrabajosPrecio(true));
            $otro = TiendanubeGobernador::conectarPrueba($prefijo);
            $continuacion = $otro->continuacion(8004291, 'precio:'.$item->id);
            $this->assertSame('sin_presupuesto_local', $continuacion['motivo'] ?? null);
            $this->assertSame('procesar', $continuacion['como'] ?? null);
            $this->assertSame('publicacion', $continuacion['clase'] ?? null);
            $this->assertGreaterThan($inicio, (int) ($continuacion['reanudar_despues_at'] ?? 0));

            $reprogramador = new TiendanubeReprogramadorDurable($otro, app(TiendanubeEnrutadorColas::class));
            $reprogramador->reprogramar(new TiendanubeTurnoResultado(
                false,
                (string) $continuacion['motivo'],
                (int) $continuacion['reanudar_despues_at'],
                (string) $continuacion['fase'],
                'precio:'.$item->id,
                (string) $continuacion['clase'],
            ));
            $this->assertSame(1, $this->contarTrabajosPrecio(true));
        } finally {
            $gobernador->olvidar();
            $otro = null;
        }
    }

    public function test_resultado_incierto_se_verifica_antes_de_otra_escritura(): void
    {
        Queue::fake();
        config([
            'tiendanube.gobernador_activo' => true,
            'tiendanube.gobernador_intervalo_ms' => 1000,
        ]);
        $gobernador = TiendanubeGobernador::conectarPrueba('tn-gov-test-'.bin2hex(random_bytes(4)));
        $inicio = 1_700_000_000_000;
        $gobernador->fijarAhora($inicio);
        $this->app->instance(TiendanubeGobernador::class, $gobernador);
        $gets = 0;
        $puts = 0;

        try {
            $this->crearVariante(1000, 100, '100.00');
            $lote = $this->aprobarLote([1000]);
            Http::fake(function (Request $request) use (&$gets, &$puts) {
                if ($request->method() === 'PUT') {
                    $puts++;

                    return Http::response(['error' => 'gateway'], 504);
                }
                $gets++;

                return Http::response($this->productoRemoto(100, 1000, '100.00'), 200);
            });
            $ejecucionId = $this->solicitarAplicacion($lote);
            $processor = app(TiendanubePrecioEjecucionItemProcessor::class);
            $this->reanudarItem($processor, $ejecucionId);
            $gobernador->fijarAhora($inicio + 1000);
            $this->reanudarItem($processor, $ejecucionId);
            $gobernador->fijarAhora($inicio + 2000);
            $this->reanudarItem($processor, $ejecucionId);

            $item = TiendanubePrecioEjecucionItem::query()->where('ejecucion_id', $ejecucionId)->firstOrFail();
            $this->assertSame(1, $puts);
            $this->assertTrue((bool) ($item->evidencia['verificacion_pendiente'] ?? false));
            $this->assertArrayHasKey('payload', $item->evidencia);
            $this->assertNotSame(TiendanubePrecioEjecucionItem::ESTADO_FALLIDO, $item->estado);

            $getsAlEnviar = $gets;
            $gobernador->fijarAhora($inicio + 3000);
            $this->reanudarItem($processor, $ejecucionId);
            $item->refresh();
            $this->assertSame(1, $puts);
            $this->assertGreaterThan($getsAlEnviar, $gets);
            $this->assertArrayHasKey('payload', $item->evidencia);
            $this->assertNotSame(TiendanubePrecioEjecucionItem::ESTADO_CONFIRMADO, $item->estado);
        } finally {
            $gobernador->olvidar();
        }
    }

    public function test_cada_categoria_de_espera_conserva_su_evidencia(): void
    {
        Queue::fake();
        config([
            'tiendanube.gobernador_activo' => true,
            'tiendanube.gobernador_intervalo_ms' => 0,
            'tiendanube.gobernador_reset_respaldo_ms' => 4_000,
        ]);
        $gobernador = TiendanubeGobernador::conectarPrueba('tn-gov-test-'.bin2hex(random_bytes(4)));
        $this->app->instance(TiendanubeGobernador::class, $gobernador);
        $cliente = app(TiendanubeApiClient::class);

        try {
            $antes = (int) floor(microtime(true) * 1000);
            $respuesta = Http::response(['error' => 'rate'], 429);
            Http::fake(function () use (&$respuesta) {
                return $respuesta;
            });
            try {
                app(TiendanubeContextoHttpActual::class)->usar(
                    'op-sin-reset',
                    'catalogo',
                    'intento',
                    'sin_escritura',
                    fn () => $cliente->getProduct(1),
                );
                $this->fail('El 429 sin reset debía reprogramarse.');
            } catch (TiendanubeApiRateLimitException) {
                $espera = $gobernador->espera(8004291, 'op-sin-reset');
                $this->assertSame('rate_limit', $espera['motivo']);
                $this->assertGreaterThanOrEqual(4_000, $espera['reanudar_despues_at'] - $antes);
                $this->assertLessThan(10_000, $espera['reanudar_despues_at'] - $antes);
            }

            $gobernador->olvidar();
            $this->crearVariante(1000, 100, '100.00');
            $lote = $this->aprobarLote([1000]);
            $processor = app(TiendanubePrecioEjecucionItemProcessor::class);

            $respuesta = Http::response(['error' => 'payment'], 402);
            $ejecucionCuenta = $this->solicitarAplicacion($lote);
            $itemCuenta = TiendanubePrecioEjecucionItem::query()->where('ejecucion_id', $ejecucionCuenta)->firstOrFail();
            $processor->procesar($itemCuenta);
            $itemCuenta->refresh();
            $ejecucion = TiendanubePrecioEjecucion::query()->findOrFail($ejecucionCuenta);
            $this->assertSame(TiendanubePrecioEjecucion::ESTADO_SUSPENDIDA, $ejecucion->estado);
            $this->assertSame('cuenta', $ejecucion->error_codigo);
            $this->assertNotSame('credenciales', $ejecucion->error_codigo);
            Http::assertNotSent(fn (Request $request) => $request->method() === 'PUT');
            app(TiendanubeOperacionTiendaService::class)->liberar((int) $ejecucion->store_id, (string) $ejecucion->lease_token);

            $gobernador->olvidar();
            $gobernador->fijarAhora((int) floor(microtime(true) * 1000));
            $respuesta = Http::response(['error' => 'unauthorized'], 401);
            $loteCredenciales = $this->aprobarLote([1000]);
            $ejecucionCredenciales = $this->solicitarAplicacion($loteCredenciales);
            $processor->procesar(TiendanubePrecioEjecucionItem::query()->where('ejecucion_id', $ejecucionCredenciales)->firstOrFail());
            $ejecucionCredencialesModelo = TiendanubePrecioEjecucion::query()->findOrFail($ejecucionCredenciales);
            $this->assertSame('credenciales', $ejecucionCredencialesModelo->error_codigo);
            app(TiendanubeOperacionTiendaService::class)->liberar((int) $ejecucionCredencialesModelo->store_id, (string) $ejecucionCredencialesModelo->lease_token);

            $gobernador->olvidar();
            $respuesta = Http::response(['error' => 'invalid'], 422);
            $loteFuncional = $this->aprobarLote([1000]);
            $ejecucionFuncional = $this->solicitarAplicacion($loteFuncional);
            $processor->procesar(TiendanubePrecioEjecucionItem::query()->where('ejecucion_id', $ejecucionFuncional)->firstOrFail());
            $itemFuncional = TiendanubePrecioEjecucionItem::query()->where('ejecucion_id', $ejecucionFuncional)->firstOrFail();
            $this->assertSame(TiendanubePrecioEjecucionItem::ESTADO_FALLIDO, $itemFuncional->estado);
            $this->assertSame('validacion_remota', $itemFuncional->error_codigo);
            $ejecucionFuncionalModelo = TiendanubePrecioEjecucion::query()->findOrFail($ejecucionFuncional);
            $this->assertNotSame(TiendanubePrecioEjecucion::ESTADO_SUSPENDIDA, $ejecucionFuncionalModelo->estado);
            app(TiendanubeOperacionTiendaService::class)->liberar((int) $ejecucionFuncionalModelo->store_id, (string) $ejecucionFuncionalModelo->lease_token);

            $gobernador->olvidar();
            $respuesta = Http::response(['error' => 'gateway'], 503);
            $loteTransitorio = $this->aprobarLote([1000]);
            $ejecucionTransitoria = $this->solicitarAplicacion($loteTransitorio);
            $processor->procesar(TiendanubePrecioEjecucionItem::query()->where('ejecucion_id', $ejecucionTransitoria)->firstOrFail());
            $itemTransitorio = TiendanubePrecioEjecucionItem::query()->where('ejecucion_id', $ejecucionTransitoria)->firstOrFail();
            $this->assertSame(TiendanubePrecioEjecucionItem::ESTADO_REINTENTO_PENDIENTE, $itemTransitorio->estado);
            $this->assertSame('error_transitorio', $itemTransitorio->evidencia['categoria'] ?? null);
            $this->assertFalse((bool) ($itemTransitorio->evidencia['escritura'] ?? true));
            $this->assertSame('sin_escritura', $itemTransitorio->evidencia['fase'] ?? null);
            $this->assertSame(0, (int) $itemTransitorio->intentos);
        } finally {
            $gobernador->olvidar();
        }
    }

    public function test_renovar_no_revive_un_lease_de_tienda_vencido(): void
    {
        $ops = app(TiendanubeOperacionTiendaService::class);
        $token = $ops->adquirirExclusiva(8004291, TiendanubeOperacionTiendaService::TIPO_PRECIO_APLICACION, (int) $this->user->id, 1);
        \App\Models\Tiendanube\TiendanubeOperacionTienda::query()->where('store_id', 8004291)->update([
            'lease_expires_at' => now()->subMinute(),
        ]);

        $this->assertFalse($ops->renovar(8004291, $token));
        $vence = \App\Models\Tiendanube\TiendanubeOperacionTienda::query()->where('store_id', 8004291)->value('lease_expires_at');
        $this->assertTrue(\Illuminate\Support\Carbon::parse($vence)->lt(now()));
        \App\Models\Tiendanube\TiendanubeOperacionTienda::query()->where('store_id', 8004291)->update([
            'lease_token' => (string) \Illuminate\Support\Str::uuid(),
            'lease_expires_at' => now()->addMinute(),
            'estado' => \App\Models\Tiendanube\TiendanubeOperacionTienda::ESTADO_ACTIVA,
        ]);
        $this->assertNull($ops->recuperarPropiedad(8004291, $token, TiendanubeOperacionTiendaService::TIPO_PRECIO_APLICACION, (int) $this->user->id, 1));
        \App\Models\Tiendanube\TiendanubeOperacionTienda::query()->where('store_id', 8004291)->update([
            'lease_expires_at' => now()->subMinute(),
        ]);
        $recuperado = $ops->recuperarPropiedad(8004291, $token, TiendanubeOperacionTiendaService::TIPO_PRECIO_APLICACION, (int) $this->user->id, 1);
        $this->assertNotNull($recuperado);
        $this->assertNotSame($token, $recuperado);
    }

    public function test_renovacion_falsa_antes_del_put_detiene_el_envio(): void
    {
        Queue::fake();
        $this->crearVariante(1000, 100, '100.00');
        $lote = $this->aprobarLote([1000]);
        $puts = 0;
        $token = null;
        Http::fake(function (Request $request) use (&$puts, &$token) {
            if ($token === null) {
                $token = \App\Models\Tiendanube\TiendanubeOperacionTienda::query()->where('store_id', 8004291)->value('lease_token');
            }
            if ($request->method() === 'GET') {
                \App\Models\Tiendanube\TiendanubeOperacionTienda::query()->where('store_id', 8004291)->update([
                    'lease_expires_at' => now()->subMinute(),
                ]);

                return Http::response($this->productoRemoto(100, 1000, '100.00'), 200);
            }
            $puts++;

            return Http::response($this->productoRemoto(100, 1000, '110.00'), 200);
        });

        $this->aplicarYProcesar($lote);
        $this->assertSame(0, $puts);
        $fila = \App\Models\Tiendanube\TiendanubeOperacionTienda::query()->where('store_id', 8004291)->first();
        $this->assertSame($token, $fila->lease_token);
        $this->assertTrue($fila->lease_expires_at->lt(now()));
        $this->assertNotSame(
            TiendanubePrecioEjecucionItem::ESTADO_CONFIRMADO,
            TiendanubePrecioEjecucionItem::query()->value('estado')
        );
    }

    public function test_el_lease_vence_durante_la_llamada_y_exige_conciliacion(): void
    {
        Queue::fake();
        $this->crearVariante(1000, 100, '100.00');
        $lote = $this->aprobarLote([1000]);
        $puts = 0;
        Http::fake(function (Request $request) use (&$puts) {
            if ($request->method() === 'PUT') {
                $puts++;
                TiendanubePrecioEjecucionItem::query()->update([
                    'lease_expires_at' => now()->subMinute(),
                ]);

                return Http::response($this->productoRemoto(100, 1000, '110.00'), 200);
            }

            return Http::response($this->productoRemoto(100, 1000, '100.00'), 200);
        });

        $this->aplicarYProcesar($lote);
        $item = TiendanubePrecioEjecucionItem::query()->firstOrFail();
        $this->assertSame(1, $puts);
        $this->assertSame(TiendanubePrecioEjecucionItem::ESTADO_RESULTADO_INCIERTO, $item->estado);
        $this->assertTrue((bool) ($item->evidencia['verificacion_pendiente'] ?? false));
        $this->assertStringContainsString(
            'no cancela una solicitud que Tiendanube ya pudo recibir',
            (string) $item->error_mensaje
        );
        $this->assertSame(
            1,
            \App\Models\Tiendanube\TiendanubePrecioEjecucionEvento::query()
                ->where('tipo', \App\Models\Tiendanube\TiendanubePrecioEjecucionEvento::TIPO_CONCILIACION_EXIGIDA)
                ->count()
        );
    }

    public function test_cancelar_durante_el_put_no_deja_que_el_worker_confirme(): void
    {
        Queue::fake();
        $this->crearVariante(1000, 100, '100.00');
        $lote = $this->aprobarLote([1000]);
        $ejecucionId = null;
        Http::fake(function (Request $request) use (&$ejecucionId) {
            if ($request->method() === 'PUT') {
                app(TiendanubePrecioEjecucionService::class)->detenerItemsEnCurso((string) $ejecucionId);

                return Http::response($this->productoRemoto(100, 1000, '110.00'), 200);
            }

            return Http::response($this->productoRemoto(100, 1000, '100.00'), 200);
        });
        $ejecucionId = $this->solicitarAplicacion($lote);
        (new ProcesarPrecioEjecucionJob($ejecucionId))->handle(app(TiendanubePrecioEjecucionService::class));
        $item = TiendanubePrecioEjecucionItem::query()->where('ejecucion_id', $ejecucionId)->firstOrFail();
        $this->assertSame(TiendanubePrecioEjecucionItem::ESTADO_CANCELADO, $item->estado);
        $this->assertSame(
            1,
            \App\Models\Tiendanube\TiendanubePrecioEjecucionEvento::query()
                ->where('tipo', \App\Models\Tiendanube\TiendanubePrecioEjecucionEvento::TIPO_CONCILIACION_EXIGIDA)
                ->count()
        );
    }

    public function test_la_espera_prolongada_recupera_la_propiedad_con_un_token_nuevo(): void
    {
        Queue::fake();
        $this->crearVariante(1000, 100, '100.00');
        $lote = $this->aprobarLote([1000]);
        $ejecucionId = $this->solicitarAplicacion($lote);
        $anterior = (string) TiendanubePrecioEjecucion::query()->findOrFail($ejecucionId)->lease_token;
        \App\Models\Tiendanube\TiendanubeOperacionTienda::query()->where('store_id', 8004291)->update([
            'lease_expires_at' => now()->subMinute(),
        ]);
        TiendanubePrecioEjecucion::query()->where('id', $ejecucionId)->update([
            'lease_expires_at' => now()->subMinute(),
        ]);
        TiendanubePrecioEjecucionItem::query()->where('ejecucion_id', $ejecucionId)->update([
            'estado' => TiendanubePrecioEjecucionItem::ESTADO_REINTENTO_PENDIENTE,
            'lease_token' => null,
            'lease_expires_at' => null,
            'siguiente_intento_at' => now()->subSecond(),
        ]);
        Http::fake(function (Request $request) {
            if ($request->method() === 'PUT') {
                return Http::response($this->productoRemoto(100, 1000, '110.00'), 200);
            }

            return Http::response($this->productoRemoto(100, 1000, '100.00'), 200);
        });

        (new ProcesarPrecioEjecucionJob($ejecucionId))->handle(app(TiendanubePrecioEjecucionService::class));
        $ejecucion = TiendanubePrecioEjecucion::query()->findOrFail($ejecucionId);
        $item = TiendanubePrecioEjecucionItem::query()->where('ejecucion_id', $ejecucionId)->firstOrFail();
        $this->assertSame(TiendanubePrecioEjecucionItem::ESTADO_CONFIRMADO, $item->estado);
        $tienda = \App\Models\Tiendanube\TiendanubeOperacionTienda::query()->where('store_id', 8004291)->first();
        $this->assertNotSame($anterior, $tienda->lease_token);
        $this->assertNotNull($ejecucion->lease_token);
        $this->assertNotSame($anterior, $ejecucion->lease_token);
    }

    public function test_una_exclusiva_ajena_impide_recuperar_la_propiedad_y_el_put(): void
    {
        Queue::fake();
        $this->crearVariante(1000, 100, '100.00');
        $lote = $this->aprobarLote([1000]);
        $ejecucionId = $this->solicitarAplicacion($lote);
        $ajeno = (string) \Illuminate\Support\Str::uuid();
        \App\Models\Tiendanube\TiendanubeOperacionTienda::query()->where('store_id', 8004291)->update([
            'lease_token' => $ajeno,
            'lease_expires_at' => now()->addMinute(),
            'estado' => \App\Models\Tiendanube\TiendanubeOperacionTienda::ESTADO_ACTIVA,
            'tipo' => TiendanubeOperacionTiendaService::TIPO_CATALOGO_SYNC,
        ]);
        TiendanubePrecioEjecucionItem::query()->where('ejecucion_id', $ejecucionId)->update([
            'estado' => TiendanubePrecioEjecucionItem::ESTADO_REINTENTO_PENDIENTE,
            'lease_token' => null,
            'lease_expires_at' => null,
            'siguiente_intento_at' => now()->subSecond(),
        ]);
        $puts = 0;
        Http::fake(function (Request $request) use (&$puts) {
            if ($request->method() === 'PUT') {
                $puts++;
            }

            return Http::response($this->productoRemoto(100, 1000, '100.00'), 200);
        });

        (new ProcesarPrecioEjecucionJob($ejecucionId))->handle(app(TiendanubePrecioEjecucionService::class));

        $this->assertSame(0, $puts);
        $this->assertSame(
            $ajeno,
            \App\Models\Tiendanube\TiendanubeOperacionTienda::query()->where('store_id', 8004291)->value('lease_token')
        );
        $this->assertNotSame(
            TiendanubePrecioEjecucionItem::ESTADO_CONFIRMADO,
            TiendanubePrecioEjecucionItem::query()->where('ejecucion_id', $ejecucionId)->value('estado')
        );
    }

    public function test_un_worker_sin_token_no_finaliza_ni_reprograma(): void
    {
        Queue::fake();
        $this->crearVariante(1000, 100, '100.00');
        $lote = $this->aprobarLote([1000]);
        $worker = null;
        $sucesor = (string) \Illuminate\Support\Str::uuid();
        Http::fake(function (Request $request) use (&$worker, $sucesor) {
            if ($request->method() !== 'PUT') {
                return Http::response($this->productoRemoto(100, 1000, '100.00'), 200);
            }
            $item = TiendanubePrecioEjecucionItem::query()->firstOrFail();
            $worker = (string) $item->lease_token;
            $item->update([
                'lease_token' => $sucesor,
                'estado' => TiendanubePrecioEjecucionItem::ESTADO_CONFIRMADO,
                'lease_expires_at' => now()->addHour(),
                'error_codigo' => null,
            ]);

            return Http::response($this->productoRemoto(100, 1000, '110.00'), 200);
        });
        $ejecucionId = $this->solicitarAplicacion($lote);
        (new ProcesarPrecioEjecucionJob($ejecucionId))->handle(app(TiendanubePrecioEjecucionService::class));
        $this->assertNotNull($worker);
        $item = TiendanubePrecioEjecucionItem::query()->firstOrFail();
        app(\App\Services\Tiendanube\Gobernador\TiendanubeReprogramadorDurable::class)->reprogramar(new TiendanubeTurnoResultado(
            false,
            'sin_presupuesto_local',
            $this->app->make(\App\Services\Tiendanube\Gobernador\TiendanubeGobernador::class)->marcaTemporal() + 5_000,
            'sin_escritura',
            'precio:'.$item->id,
            'publicacion',
        ), $worker);
        $item->refresh();
        $this->assertSame(TiendanubePrecioEjecucionItem::ESTADO_CONFIRMADO, $item->estado);
        $this->assertSame($sucesor, $item->lease_token);
    }

    public function test_la_publicacion_bloquea_edicion_e_imagenes_y_el_espejo_remoto_sigue(): void
    {
        $this->user->givePermissionTo(
            Permission::findOrCreate('tiendanube.productos.editar', 'web')
        );
        $this->crearVariante(1000, 100, '100.00');
        app(TiendanubeOperacionTiendaService::class)->adquirirExclusiva(
            8004291,
            TiendanubeOperacionTiendaService::TIPO_PRECIO_APLICACION,
            (int) $this->user->id,
            1
        );

        $this->actingAs($this->user)
            ->putJson(route('tiendanube.productos.update', 100), ['name' => 'Editado en Gelia'])
            ->assertStatus(409);

        $zip = tempnam(sys_get_temp_dir(), 'tnzip');
        $archivo = new \ZipArchive();
        $archivo->open($zip, \ZipArchive::OVERWRITE);
        $archivo->addFromString('SKU-1000.jpg', 'img');
        $archivo->close();
        $this->actingAs($this->user)
            ->post(route('tiendanube.imagenes.importar'), [
                'zip' => new \Illuminate\Http\UploadedFile($zip, 'lote.zip', 'application/zip', null, true),
            ])
            ->assertStatus(409);

        app(\App\Services\Tiendanube\TiendanubeCatalogoSyncService::class)->upsertProducto([
            'id' => 100,
            'name' => ['es' => 'Cambiado en Tiendanube'],
            'published' => true,
        ]);
        $producto = TiendanubeProducto::query()->findOrFail(100);
        $nombre = is_array($producto->name) ? ($producto->name['es'] ?? '') : (string) $producto->name;
        $this->assertSame('Cambiado en Tiendanube', $nombre);
    }

    public function test_una_edicion_en_curso_impide_el_put_de_publicacion(): void
    {
        Queue::fake();
        $this->crearVariante(1000, 100, '100.00');
        $lote = $this->aprobarLote([1000]);
        $lock = app(\App\Services\Tiendanube\TiendanubeProductoRecursoLockService::class)
            ->intentarProducto(8004291, 100, 90);
        $this->assertNotNull($lock);
        $puts = 0;
        Http::fake(function (Request $request) use (&$puts) {
            if ($request->method() === 'PUT') {
                $puts++;
            }

            return Http::response($this->productoRemoto(100, 1000, '100.00'), 200);
        });

        try {
            $this->aplicarYProcesar($lote);
        } finally {
            $lock->release();
        }

        $this->assertSame(0, $puts);
        $this->assertSame(
            TiendanubePrecioEjecucionItem::ESTADO_REINTENTO_PENDIENTE,
            TiendanubePrecioEjecucionItem::query()->value('estado')
        );
    }

    public function test_las_duraciones_dejan_el_http_dentro_del_lease_y_el_respaldo_es_2000(): void
    {
        $src = (string) file_get_contents(config_path('tiendanube.php'));
        $defecto = static function (string $clave) use ($src): int {
            $encontrado = preg_match("/'".$clave."' => \(int\) env\('[^']+', (\d+(?:_\d+)?)\)/", $src, $coincidencia);

            return $encontrado === 1 ? (int) str_replace('_', '', $coincidencia[1]) : -1;
        };
        $http = $defecto('precio_http_timeout_seconds');
        $trabajo = $defecto('precio_trabajo_max_seconds');
        $lease = $defecto('precio_ejecucion_lease_seconds');
        $lock = $defecto('precio_recurso_lock_seconds');
        $tienda = $defecto('sync_lease_seconds');
        $respaldo = $defecto('gobernador_reset_respaldo_ms');
        $this->assertSame(30, $http);
        $this->assertSame(80, $trabajo);
        $this->assertSame(90, $lease);
        $this->assertSame(90, $lock);
        $this->assertSame(120, $tienda);
        $this->assertSame(2_000, $respaldo);
        $this->assertSame($trabajo, (new ProcesarPrecioEjecucionJob('x'))->timeout);
        $this->assertLessThan($trabajo, $http);
        $this->assertLessThan($lease, $trabajo);
        $this->assertLessThanOrEqual($lock, $lease);
        $this->assertLessThanOrEqual($tienda, $lock);
        $this->assertStringContainsString('No es una espera fija de cinco segundos', $src);
        $procesador = (string) file_get_contents(app_path('Services/Tiendanube/Precios/Aplicacion/TiendanubePrecioEjecucionItemProcessor.php'));
        $this->assertStringContainsString("config('tiendanube.precio_http_timeout_seconds', 30)", $procesador);
        $this->assertStringContainsString("config('tiendanube.precio_recurso_lock_seconds', 90)", $procesador);
        $this->assertStringContainsString("config('tiendanube.gobernador_reset_respaldo_ms', 2_000)", $procesador);
    }

    /**
     * @param  array<string, mixed>  $lote
     */
    private function solicitarAplicacion(array $lote): string
    {
        return $this->actingAs($this->user)
            ->postJson(route('tiendanube.precios.lotes.aplicar', $lote['lote_id']), [
                'checksum' => $lote['revision']['checksum'],
            ])
            ->assertCreated()
            ->json('id');
    }

    private function contarTrabajosPrecio(bool $demorados): int
    {
        return Queue::pushed(ProcesarPrecioEjecucionJob::class)
            ->filter(fn (ProcesarPrecioEjecucionJob $job) => $demorados ? $job->delay !== null : $job->delay === null)
            ->count();
    }

    /**
     * @param  list<int>  $varianteIds
     * @return array<string, mixed>
     */
    private function aprobarLote(array $varianteIds): array
    {
        $selection = $this->actingAs($this->user)
            ->postJson(route('tiendanube.precios.selecciones.store'), [
                'modo' => 'pagina',
                'variante_ids' => $varianteIds,
            ])
            ->assertCreated();

        $lote = $this->actingAs($this->user)
            ->postJson(route('tiendanube.precios.lotes.store'), [
                'selection_id' => $selection->json('selection_id'),
                'selection_version' => $selection->json('version'),
                'definicion' => TiendanubePrecioMotorFixtures::regla(
                    'temp',
                    'precio_normal_actual',
                    'aumentar_porcentaje',
                    'normal',
                    '10'
                ),
            ])
            ->assertCreated()
            ->json();

        $this->actingAs($this->user)
            ->postJson(route('tiendanube.precios.lotes.aprobar', $lote['lote_id']), [
                'checksum' => $lote['revision']['checksum'],
            ])
            ->assertOk();

        return $lote;
    }

    /**
     * @param  array<string, mixed>  $lote
     */
    private function aplicarYProcesar(array $lote): void
    {
        $res = $this->actingAs($this->user)
            ->postJson(route('tiendanube.precios.lotes.aplicar', $lote['lote_id']), [
                'checksum' => $lote['revision']['checksum'],
            ])
            ->assertCreated();

        $job = new ProcesarPrecioEjecucionJob($res->json('id'));
        $job->handle(app(TiendanubePrecioEjecucionService::class));
    }

    /**
     * @return array<string, mixed>
     */
    private function productoRemoto(int $productoId, int $varianteId, string $precio): array
    {
        return [
            'id' => $productoId,
            'name' => ['es' => 'Producto '.$productoId],
            'published' => true,
            'variants' => [[
                'id' => $varianteId,
                'sku' => 'SKU-'.$varianteId,
                'price' => $precio,
                'promotional_price' => null,
                'cost' => '40.00',
                'stock' => 5,
                'stock_management' => true,
            ]],
        ];
    }

    private function reanudarItem(TiendanubePrecioEjecucionItemProcessor $processor, string $ejecucionId): void
    {
        $item = TiendanubePrecioEjecucionItem::query()->where('ejecucion_id', $ejecucionId)->firstOrFail();
        if ($item->estado === TiendanubePrecioEjecucionItem::ESTADO_REINTENTO_PENDIENTE) {
            $item->update(['siguiente_intento_at' => now()->subSecond()]);
        }
        $processor->procesar($item->fresh());
    }

    private function crearVariante(int $id, int $productoId, string $precio): void
    {
        TiendanubeProducto::query()->create([
            'id' => $productoId,
            'name' => ['es' => 'Producto '.$productoId],
            'published' => true,
            'synced_at' => now(),
        ]);
        TiendanubeProductoVariante::query()->create([
            'id' => $id,
            'producto_id' => $productoId,
            'sku' => 'SKU-'.$id,
            'price' => $precio,
            'cost' => '40.00',
            'values' => ['Talla M'],
            'stock' => 5,
        ]);
    }
}
