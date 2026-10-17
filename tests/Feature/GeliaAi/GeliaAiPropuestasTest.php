<?php

namespace Tests\Feature\GeliaAi;

use App\Jobs\Almacenes\ImportarAlmacenCatalogoJob;
use App\Models\Almacen;
use App\Models\Almacenes\ImportacionAlmacenLog;
use App\Models\GeliaAiConversacion;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\GeliaAi\Acciones\PropuestasAccionGeliaAi;
use App\Services\GeliaAi\GeliaAiArchivoService;
use App\Services\GeliaAi\InspeccionarArchivoGeliaAiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Throwable;

class GeliaAiPropuestasTest extends TestCase
{
    use RefreshDatabase;

    private const MAPPING = ['sku' => 'sku', 'costo' => 'costo'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
            \Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class,
        ]);
        Role::findOrCreate('Super Admin', 'web');
        foreach (['almacenes.costos.importar', 'almacenes.inventarios.importar', 'listados.ver'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
        config([
            'deepseek.api_token' => 'test-token',
            'deepseek.base_url' => 'https://api.deepseek.test',
            'gelia_ai.acceso_modo' => 'super_admin',
            'gelia_ai.propuesta_ttl_minutes' => 30,
        ]);
        Storage::fake('local');
        Queue::fake();
    }

    public function test_sin_propuesta_id_se_rechaza_aunque_lleguen_accion_y_payload_validos(): void
    {
        [$admin, $cedis, , $meta] = $this->escenario();

        $this->actingAs($admin)->postJson(route('gelia_ai.acciones.ejecutar'), [
            'accion' => 'importar_costos',
            'confirmado' => true,
            'payload' => ['file_id' => $meta['file_id'], 'almacen_id' => $cedis->id, 'mapping' => self::MAPPING],
        ])->assertStatus(422)->assertJsonValidationErrors('propuesta_id');

        Queue::assertNothingPushed();
        $this->assertSame(0, ImportacionAlmacenLog::count());
    }

    public function test_ejecuta_los_parametros_guardados_e_ignora_sustituciones_del_navegador(): void
    {
        [$admin, $cedis, $tienda, $meta] = $this->escenario();
        $propuesta = $this->proponer($admin, $cedis, $meta);

        $this->actingAs($admin)->postJson(route('gelia_ai.acciones.ejecutar'), [
            'propuesta_id' => $propuesta['id'],
            'confirmado' => true,
            'accion' => 'importar_inventarios',
            'payload' => ['file_id' => $meta['file_id'], 'almacen_id' => $tienda->id, 'mapping' => ['sku' => 'sku', 'existencia' => 'costo']],
        ])->assertOk()
            ->assertJsonPath('accion', 'importar_costos')
            ->assertJsonPath('reporte.estado', 'en_cola')
            ->assertJsonPath('estado_propuesta', 'ejecutada');

        $log = ImportacionAlmacenLog::sole();
        $this->assertSame('costos', $log->tipo);
        $this->assertSame($cedis->id, (int) $log->almacen_id);
        $this->assertSame(self::MAPPING, $log->mapping);
        Queue::assertPushed(ImportarAlmacenCatalogoJob::class, 1);
    }

    public function test_propuesta_vencida_se_rechaza_con_mensaje_claro(): void
    {
        [$admin, $cedis, , $meta] = $this->escenario();
        $propuesta = $this->proponer($admin, $cedis, $meta);

        $this->travel(31)->minutes();

        $this->actingAs($admin)->postJson(route('gelia_ai.acciones.ejecutar'), [
            'propuesta_id' => $propuesta['id'],
            'confirmado' => true,
        ])->assertStatus(410)
            ->assertJsonPath('estado_propuesta', 'vencida')
            ->assertJsonPath('message', 'La propuesta venció sin ejecutarse (cada propuesta es válida 30 minutos). Pide a GELIA que la prepare de nuevo.');

        Queue::assertNothingPushed();
    }

    public function test_propuesta_consumida_no_se_ejecuta_otra_vez(): void
    {
        [$admin, $cedis, , $meta] = $this->escenario();
        $propuesta = $this->proponer($admin, $cedis, $meta);
        $body = ['propuesta_id' => $propuesta['id'], 'confirmado' => true];

        $this->actingAs($admin)->postJson(route('gelia_ai.acciones.ejecutar'), $body)->assertOk();
        $this->actingAs($admin)->postJson(route('gelia_ai.acciones.ejecutar'), $body)
            ->assertStatus(409)
            ->assertJsonPath('estado_propuesta', 'ejecutada')
            ->assertJsonPath('message', 'Esta propuesta ya se ejecutó. Revisa el resultado en la conversación o en el módulo correspondiente.');

        Queue::assertPushed(ImportarAlmacenCatalogoJob::class, 1);
    }

    public function test_una_ejecucion_fallida_no_habilita_un_segundo_intento(): void
    {
        [$admin, $cedis, , $meta] = $this->escenario();
        $propuesta = $this->proponer($admin, $cedis, $meta);
        $conv = $this->conversacion($admin, $propuesta['id']);
        $body = ['propuesta_id' => $propuesta['id'], 'confirmado' => true, 'conversacion_id' => $conv->id];

        $ruta = "temp/gelia_ai/{$admin->id}/{$meta['file_id']}.csv";
        $contenido = Storage::get($ruta);
        Storage::delete($ruta);

        $this->actingAs($admin)->postJson(route('gelia_ai.acciones.ejecutar'), $body)
            ->assertStatus(422)
            ->assertJsonPath('estado_propuesta', 'fallida');

        // Aunque el archivo vuelva a estar disponible, la misma propuesta no se reintenta.
        Storage::put($ruta, $contenido);
        $this->actingAs($admin)->postJson(route('gelia_ai.acciones.ejecutar'), $body)
            ->assertStatus(409)
            ->assertJsonPath('estado_propuesta', 'fallida');

        Queue::assertNothingPushed();
        $conv->refresh();
        $this->assertNull($conv->contexto['tarea_pendiente']);
        $this->assertSame('fallida', $conv->contexto['ultima_accion']['estado']);
        $this->assertStringContainsString('No se reintentó automáticamente', $conv->mensajes()->get()->last()->content);
    }

    public function test_cancelar_invalida_la_propuesta_en_el_servidor_y_limpia_la_tarea_pendiente(): void
    {
        [$admin, $cedis, , $meta] = $this->escenario();
        $propuesta = $this->proponer($admin, $cedis, $meta);
        $conv = $this->conversacion($admin, $propuesta['id']);

        $this->actingAs($admin)->postJson(route('gelia_ai.acciones.cancelar'), [
            'propuesta_id' => $propuesta['id'],
            'conversacion_id' => $conv->id,
        ])->assertOk()->assertJsonPath('estado_propuesta', 'cancelada');

        $conv->refresh();
        $this->assertNull($conv->contexto['tarea_pendiente']);
        $this->assertStringContainsString('Propuesta cancelada; no se ejecutó nada', $conv->mensajes()->get()->last()->content);

        $this->actingAs($admin)->postJson(route('gelia_ai.acciones.ejecutar'), [
            'propuesta_id' => $propuesta['id'],
            'confirmado' => true,
        ])->assertStatus(409)->assertJsonPath('estado_propuesta', 'cancelada');

        $this->actingAs($admin)->postJson(route('gelia_ai.acciones.cancelar'), ['propuesta_id' => $propuesta['id']])
            ->assertStatus(409)->assertJsonPath('estado_propuesta', 'cancelada');

        Queue::assertNothingPushed();
    }

    public function test_no_se_cancela_una_propuesta_ya_ejecutada_ni_ajena(): void
    {
        [$admin, $cedis, , $meta] = $this->escenario();
        $propuesta = $this->proponer($admin, $cedis, $meta);
        $otra = User::factory()->create();
        $otra->assignRole('Super Admin');

        $this->actingAs($otra)->postJson(route('gelia_ai.acciones.cancelar'), ['propuesta_id' => $propuesta['id']])
            ->assertNotFound();

        $this->actingAs($admin)->postJson(route('gelia_ai.acciones.ejecutar'), ['propuesta_id' => $propuesta['id'], 'confirmado' => true])
            ->assertOk();
        $this->actingAs($admin)->postJson(route('gelia_ai.acciones.cancelar'), ['propuesta_id' => $propuesta['id']])
            ->assertStatus(409)->assertJsonPath('estado_propuesta', 'ejecutada');
    }

    public function test_abrir_conversacion_restaura_propuesta_pendiente_y_archivos(): void
    {
        [$admin, $cedis, , $meta] = $this->escenario();
        $propuesta = $this->proponer($admin, $cedis, $meta);
        $conv = $this->conversacion($admin, $propuesta['id'], [$meta['file_id']]);

        $this->actingAs($admin)->getJson(route('gelia_ai.conversaciones.show', $conv->id))
            ->assertOk()
            ->assertJsonPath('propuesta_pendiente.id', $propuesta['id'])
            ->assertJsonPath('propuesta_pendiente.detalle.confirmar', 'Confirmar importación')
            ->assertJsonPath('propuesta_pendiente.detalle.almacen', 'CEDIS · CEDIS Central')
            ->assertJsonMissingPath('propuesta_pendiente.payload')
            ->assertJsonPath('archivos.0.file_id', $meta['file_id']);

        app(PropuestasAccionGeliaAi::class)->cancelar($admin, $propuesta['id']);

        $this->actingAs($admin)->getJson(route('gelia_ai.conversaciones.show', $conv->id))
            ->assertOk()
            ->assertJsonPath('propuesta_pendiente', null);
    }

    public function test_dos_confirmaciones_simultaneas_ejecutan_una_sola_vez(): void
    {
        [$admin, $cedis, , $meta] = $this->escenario();
        $this->usarCacheCompartidaEntreProcesos();
        $propuesta = $this->proponer($admin, $cedis, $meta);
        $this->actingAs($admin);

        $resultados = $this->enParalelo(2, function () use ($propuesta) {
            $res = $this->postJson(route('gelia_ai.acciones.ejecutar'), ['propuesta_id' => $propuesta['id'], 'confirmado' => true]);

            return [
                'status' => $res->status(),
                'estado' => $res->json('estado_propuesta'),
                'jobs' => Queue::pushed(ImportarAlmacenCatalogoJob::class)->count(),
            ];
        });

        $this->assertEqualsCanonicalizing([200, 409], array_column($resultados, 'status'), json_encode($resultados));
        $this->assertSame(1, array_sum(array_column($resultados, 'jobs')));
        $rechazo = collect($resultados)->firstWhere('status', 409);
        $this->assertContains($rechazo['estado'], ['procesando', 'ejecutada', 'ocupada']);

        $this->assertSame('ejecutada', app(PropuestasAccionGeliaAi::class)->reclamar($admin, $propuesta['id'])['estado']);
    }

    public function test_reclamos_simultaneos_entregan_la_propuesta_a_un_solo_proceso(): void
    {
        [$admin, $cedis, , $meta] = $this->escenario();
        $this->usarCacheCompartidaEntreProcesos();
        $propuesta = $this->proponer($admin, $cedis, $meta);

        $resultados = $this->enParalelo(6, function () use ($admin, $propuesta) {
            $r = app(PropuestasAccionGeliaAi::class)->reclamar($admin, $propuesta['id']);

            return ['reclamada' => isset($r['propuesta']), 'estado' => $r['estado']];
        });

        $this->assertSame(1, collect($resultados)->where('reclamada', true)->count(), json_encode($resultados));
        foreach (collect($resultados)->where('reclamada', false) as $r) {
            $this->assertContains($r['estado'], ['procesando', 'ocupada']);
        }
    }

    /** @return array{0: User, 1: Almacen, 2: Almacen, 3: array<string, mixed>} */
    private function escenario(): array
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $sucursal = Sucursal::factory()->create();
        $admin->concederAccesoSucursal($sucursal, true);
        $cedis = Almacen::create(['codigo' => 'CEDIS', 'nombre' => 'CEDIS Central', 'activo' => true, 'sucursal_id' => $sucursal->id]);
        $tienda = Almacen::create(['codigo' => 'TIENDA1', 'nombre' => 'Tienda Uno', 'activo' => true, 'sucursal_id' => $sucursal->id]);

        $tmp = tempnam(sys_get_temp_dir(), 'gelia');
        file_put_contents($tmp, "sku,costo\nMSKY100,9.9\n");
        $meta = app(GeliaAiArchivoService::class)->guardarUno(
            $admin,
            new UploadedFile($tmp, 'costos.csv', 'text/csv', null, true),
            app(InspeccionarArchivoGeliaAiService::class),
        );

        return [$admin, $cedis, $tienda, $meta];
    }

    /** @return array<string, mixed> */
    private function proponer(User $user, Almacen $almacen, array $meta): array
    {
        return app(PropuestasAccionGeliaAi::class)->registrar(
            $user,
            'importar_costos',
            ['file_id' => $meta['file_id'], 'almacen_id' => $almacen->id, 'mapping' => self::MAPPING],
            "Importar costos al almacén {$almacen->codigo}",
            [],
            ['operacion' => 'Importar costos', 'almacen' => "{$almacen->codigo} · {$almacen->nombre}", 'confirmar' => 'Confirmar importación'],
        );
    }

    /** @param  list<string>  $archivos */
    private function conversacion(User $user, string $propuestaId, array $archivos = []): GeliaAiConversacion
    {
        return GeliaAiConversacion::create([
            'user_id' => $user->id,
            'titulo' => 'Importar costos',
            'temporal' => false,
            'contexto' => [
                'tema' => 'importar_costos',
                'archivos' => $archivos,
                'tarea_pendiente' => ['accion' => 'importar_costos', 'estado' => 'esperando_confirmacion', 'propuesta_id' => $propuestaId],
            ],
        ]);
    }

    /** El store array de pruebas vive en un solo proceso; file comparte datos y locks (flock) entre procesos. */
    private function usarCacheCompartidaEntreProcesos(): void
    {
        if (! function_exists('pcntl_fork') || ! function_exists('posix_kill')) {
            $this->markTestSkipped('Se requieren pcntl y posix para lanzar procesos simultáneos.');
        }

        $dir = storage_path('framework/testing/gelia-concurrencia-'.Str::uuid());
        File::ensureDirectoryExists($dir);
        $this->beforeApplicationDestroyed(fn () => File::deleteDirectory($dir));
        config([
            'cache.stores.gelia_concurrencia' => ['driver' => 'file', 'path' => $dir, 'lock_path' => $dir],
            'cache.default' => 'gelia_concurrencia',
        ]);
        Cache::setDefaultDriver('gelia_concurrencia');
    }

    /**
     * Ejecuta $fn en $n procesos que arrancan a la vez (barrera por archivo) y devuelve lo que cada uno reporta.
     *
     * @return list<array<string, mixed>>
     */
    private function enParalelo(int $n, callable $fn): array
    {
        $dir = sys_get_temp_dir().'/gelia-paralelo-'.Str::uuid();
        mkdir($dir);
        $pids = [];
        for ($i = 0; $i < $n; $i++) {
            $pid = pcntl_fork();
            $this->assertNotSame(-1, $pid, 'No se pudo crear el proceso hijo.');
            if ($pid === 0) {
                while (! file_exists("{$dir}/go")) {
                    usleep(100);
                }
                try {
                    $out = $fn();
                } catch (Throwable $e) {
                    $out = ['error' => $e::class.': '.$e->getMessage()];
                }
                file_put_contents("{$dir}/{$i}.json", json_encode($out));
                // Termina sin ejecutar los cierres de PHPUnit/Laravel heredados del proceso padre.
                posix_kill(posix_getpid(), SIGKILL);
            }
            $pids[] = $pid;
        }

        usleep(200_000);
        touch("{$dir}/go");
        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
        }

        $resultados = [];
        for ($i = 0; $i < $n; $i++) {
            $resultados[] = json_decode((string) @file_get_contents("{$dir}/{$i}.json"), true) ?? ['error' => 'sin resultado'];
        }
        File::deleteDirectory($dir);

        return $resultados;
    }
}
