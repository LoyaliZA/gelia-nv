<?php

/**
 * Proceso aislado para E2c. Usa un archivo SQLite propio y, si hace falta,
 * Redis DB 15 con prefijo de prueba. No migra, no hace FLUSHDB y aborta si
 * la conexión no es ese archivo.
 */

use App\Models\Tiendanube\TiendanubeOperacionTienda;
use App\Models\Tiendanube\TiendanubePrecioEjecucionItem;
use App\Services\Tiendanube\Gobernador\TiendanubeGobernador;
use App\Services\Tiendanube\Precios\Aplicacion\TiendanubePrecioEjecucionService;
use App\Services\Tiendanube\Precios\Aplicacion\TiendanubePrecioLeaseGuard;
use App\Services\Tiendanube\TiendanubeOperacionTiendaService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

if (getenv('ALLOW_DESTRUCTIVE_DB') === '1') {
    fwrite(STDERR, "ALLOW_DESTRUCTIVE_DB no está permitido\n");
    exit(4);
}

$accion = (string) ($argv[1] ?? '');
$sqlite = (string) ($argv[2] ?? '');
$resto = array_slice($argv, 3);

if ($accion === '' || $sqlite === '' || ! is_file($sqlite)) {
    fwrite(STDERR, "faltan acción o archivo sqlite\n");
    exit(1);
}

$ambiente = [
    'APP_ENV' => 'testing',
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => $sqlite,
    'QUEUE_CONNECTION' => 'sync',
    'CACHE_STORE' => 'array',
    'BROADCAST_CONNECTION' => 'null',
];

if (str_starts_with($accion, 'lock-')) {
    $ambiente['CACHE_STORE'] = 'redis';
    $ambiente['CACHE_PREFIX'] = (string) ($resto[0] ?? '');
    $ambiente['REDIS_CACHE_DB'] = '15';
    $ambiente['REDIS_CACHE_LOCK_CONNECTION'] = 'cache';
    if ($ambiente['CACHE_PREFIX'] === '') {
        fwrite(STDERR, "falta el prefijo de cache\n");
        exit(1);
    }
}

foreach ($ambiente as $clave => $valor) {
    putenv($clave.'='.$valor);
    $_ENV[$clave] = $valor;
    $_SERVER[$clave] = $valor;
}

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->afterBootstrapping(
    \Illuminate\Foundation\Bootstrap\LoadConfiguration::class,
    function ($aplicacion) use ($sqlite, $accion, $resto): void {
        $aplicacion['config']->set('database.default', 'sqlite');
        $aplicacion['config']->set('database.connections.sqlite.database', $sqlite);
        $aplicacion['config']->set('database.connections.sqlite.foreign_key_constraints', false);
        $aplicacion['config']->set('queue.default', 'sync');
        $aplicacion['config']->set('app.env', 'testing');
        if (str_starts_with($accion, 'lock-')) {
            $aplicacion['config']->set('cache.default', 'redis');
            $aplicacion['config']->set('cache.prefix', (string) ($resto[0] ?? ''));
            $aplicacion['config']->set('database.redis.cache.database', '15');
            $aplicacion['config']->set('cache.stores.redis.connection', 'cache');
            $aplicacion['config']->set('cache.stores.redis.lock_connection', 'cache');
        }
    }
);
$app->make(Kernel::class)->bootstrap();
DB::purge('sqlite');
DB::purge();

try {
    $lista = DB::select('PRAGMA database_list');
} catch (Throwable $e) {
    fwrite(STDERR, "aislamiento: la conexión no es sqlite\n");
    exit(3);
}

$archivo = '';
foreach ($lista as $fila) {
    if (($fila->name ?? '') === 'main') {
        $archivo = (string) $fila->file;
    }
}

if ($archivo === '' || $archivo === ':memory:' || realpath($archivo) !== realpath($sqlite)) {
    fwrite(STDERR, "aislamiento: {$archivo}\n");
    exit(3);
}

DB::statement('PRAGMA foreign_keys = OFF');
DB::statement('PRAGMA journal_mode = WAL');
DB::statement('PRAGMA busy_timeout = 5000');

if (str_starts_with($accion, 'lock-')) {
    if ((string) config('cache.default') !== 'redis' || (string) config('database.redis.cache.database') !== '15') {
        fwrite(STDERR, "aislamiento: el lock no quedó en redis 15\n");
        exit(3);
    }
    if ((string) config('cache.prefix') !== (string) ($resto[0] ?? '')) {
        fwrite(STDERR, "aislamiento: prefijo de cache distinto\n");
        exit(3);
    }
    if ((string) config('cache.stores.redis.lock_connection') !== 'cache') {
        fwrite(STDERR, "aislamiento: el lock no usa la conexión cache\n");
        exit(3);
    }
}

$responder = static function (array $datos): void {
    fwrite(STDOUT, json_encode($datos, JSON_THROW_ON_ERROR)."\n");
};

$prepararEsquema = static function (): void {
    if (Schema::hasTable('tiendanube_precio_ejecuciones')) {
        return;
    }

    Schema::create('tiendanube_precio_ejecuciones', function ($table) {
        $table->uuid('id')->primary();
        $table->uuid('lote_id');
        $table->unsignedBigInteger('revision_id');
        $table->unsignedBigInteger('store_id');
        $table->unsignedBigInteger('user_id');
        $table->unsignedBigInteger('config_generation')->default(1);
        $table->string('api_version', 16);
        $table->string('checksum_revision', 64);
        $table->string('canal', 16)->default('api');
        $table->string('estado', 24)->default('completada');
        $table->unsignedInteger('total')->default(1);
        $table->unsignedInteger('pendientes')->default(1);
        $table->string('lease_token', 64)->nullable();
        $table->timestamp('lease_expires_at')->nullable();
        $table->timestamps();
    });

    Schema::create('tiendanube_precio_ejecucion_items', function ($table) {
        $table->id();
        $table->uuid('ejecucion_id');
        $table->unsignedBigInteger('producto_id');
        $table->unsignedBigInteger('variante_id');
        $table->json('campos_objetivo');
        $table->json('valor_aprobado');
        $table->string('estado', 32)->default('pendiente');
        $table->unsignedInteger('intentos')->default(0);
        $table->string('lease_token', 64)->nullable();
        $table->timestamp('lease_expires_at')->nullable();
        $table->timestamp('siguiente_intento_at')->nullable();
        $table->string('error_codigo', 64)->nullable();
        $table->text('error_mensaje')->nullable();
        $table->json('evidencia')->nullable();
        $table->timestamps();
    });

    Schema::create('tiendanube_precio_ejecucion_eventos', function ($table) {
        $table->id();
        $table->uuid('ejecucion_id');
        $table->unsignedBigInteger('item_id')->nullable();
        $table->string('tipo', 48);
        $table->unsignedBigInteger('actor_id')->nullable();
        $table->json('payload')->nullable();
        $table->timestamp('created_at')->nullable();
    });

    Schema::create('tiendanube_operaciones_tienda', function ($table) {
        $table->id();
        $table->unsignedBigInteger('store_id')->unique();
        $table->string('tipo', 64)->nullable();
        $table->unsignedBigInteger('owner_id')->nullable();
        $table->unsignedBigInteger('config_generation')->nullable();
        $table->uuid('lease_token')->nullable();
        $table->timestamp('lease_expires_at')->nullable();
        $table->string('estado', 32)->default('liberada');
        $table->timestamps();
    });
};

try {
    match ($accion) {
        'preparar' => (function () use ($prepararEsquema, $responder, $resto) {
            $prepararEsquema();
            $ejecucionId = (string) ($resto[0] ?? '');
            $store = (int) ($resto[1] ?? 0);
            if ($ejecucionId === '' || $store < 1) {
                throw new RuntimeException('faltan ejecución o tienda');
            }
            $ahora = now();
            DB::table('tiendanube_precio_ejecuciones')->insert([
                'id' => $ejecucionId,
                'lote_id' => (string) Str::uuid(),
                'revision_id' => 1,
                'store_id' => $store,
                'user_id' => 1,
                'config_generation' => 1,
                'api_version' => '2025-03',
                'checksum_revision' => 'e2c',
                'canal' => 'api',
                'estado' => 'completada',
                'total' => 1,
                'pendientes' => 1,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
            $itemId = DB::table('tiendanube_precio_ejecucion_items')->insertGetId([
                'ejecucion_id' => $ejecucionId,
                'producto_id' => 1,
                'variante_id' => 1,
                'campos_objetivo' => '{}',
                'valor_aprobado' => '{}',
                'estado' => TiendanubePrecioEjecucionItem::ESTADO_PENDIENTE,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
            $responder(['item_id' => $itemId, 'ejecucion_id' => $ejecucionId]);
        })(),
        'reclamar' => (function () use ($responder, $resto) {
            $item = TiendanubePrecioEjecucionItem::query()->findOrFail((int) ($resto[0] ?? 0));
            $responder(['token' => app(TiendanubePrecioLeaseGuard::class)->reclamar($item)]);
        })(),
        'sembrar' => (function () use ($responder, $resto) {
            $vigente = ($resto[2] ?? '') === 'vigente';
            $n = TiendanubePrecioEjecucionItem::query()->where('id', (int) ($resto[0] ?? 0))->update([
                'estado' => TiendanubePrecioEjecucionItem::ESTADO_PROCESANDO,
                'lease_token' => (string) ($resto[1] ?? ''),
                'lease_expires_at' => $vigente ? now()->addHour() : now()->subMinute(),
                'error_codigo' => null,
                'updated_at' => now(),
            ]);
            $responder(['filas' => $n]);
        })(),
        'reemplazar' => (function () use ($responder, $resto) {
            $nuevo = (string) Str::uuid();
            $n = TiendanubePrecioEjecucionItem::query()
                ->where('id', (int) ($resto[0] ?? 0))
                ->where('lease_token', (string) ($resto[1] ?? ''))
                ->where('estado', TiendanubePrecioEjecucionItem::ESTADO_PROCESANDO)
                ->update([
                    'lease_token' => $nuevo,
                    'estado' => TiendanubePrecioEjecucionItem::ESTADO_CONFIRMADO,
                    'lease_expires_at' => now()->addHour(),
                    'updated_at' => now(),
                ]);
            $responder(['filas' => $n, 'token' => $nuevo]);
        })(),
        'finalizar' => (function () use ($responder, $resto) {
            $item = TiendanubePrecioEjecucionItem::query()->findOrFail((int) ($resto[0] ?? 0));
            $ok = app(TiendanubePrecioLeaseGuard::class)->actualizarVigente($item, (string) ($resto[1] ?? ''), [
                'estado' => TiendanubePrecioEjecucionItem::ESTADO_FALLIDO,
                'error_codigo' => 'tarde',
                'lease_token' => null,
                'lease_expires_at' => null,
            ], [TiendanubePrecioEjecucionItem::ESTADO_PROCESANDO]);
            $responder(['ok' => $ok]);
        })(),
        'detener' => (function () use ($responder, $resto) {
            $n = app(TiendanubePrecioEjecucionService::class)->detenerItemsEnCurso((string) ($resto[0] ?? ''));
            $responder(['filas' => $n]);
        })(),
        'sucesor' => (function () use ($responder, $resto) {
            $item = TiendanubePrecioEjecucionItem::query()->findOrFail((int) ($resto[0] ?? 0));
            $token = app(TiendanubePrecioLeaseGuard::class)->reclamarParaVerificar($item);
            $responder(['token' => $token, 'ok' => $token !== null]);
        })(),
        'recuperar' => (function () use ($responder) {
            $responder(['filas' => app(TiendanubePrecioEjecucionService::class)->recuperar()]);
        })(),
        'leer-item' => (function () use ($responder, $resto) {
            $item = TiendanubePrecioEjecucionItem::query()->findOrFail((int) ($resto[0] ?? 0));
            $responder([
                'estado' => $item->estado,
                'lease_token' => $item->lease_token,
                'error_codigo' => $item->error_codigo,
            ]);
        })(),
        'sembrar-tienda' => (function () use ($responder, $resto) {
            $store = (int) ($resto[0] ?? 0);
            TiendanubeOperacionTienda::query()->updateOrCreate(
                ['store_id' => $store],
                [
                    'tipo' => TiendanubeOperacionTiendaService::TIPO_PRECIO_APLICACION,
                    'owner_id' => 1,
                    'config_generation' => 1,
                    'lease_token' => (string) ($resto[1] ?? ''),
                    'lease_expires_at' => now()->addHour(),
                    'estado' => TiendanubeOperacionTienda::ESTADO_ACTIVA,
                ]
            );
            $responder(['ok' => true]);
        })(),
        'liberar-tienda' => (function () use ($responder, $resto) {
            app(TiendanubeOperacionTiendaService::class)->liberar((int) ($resto[0] ?? 0), (string) ($resto[1] ?? ''));
            $responder(['ok' => true]);
        })(),
        'leer-tienda' => (function () use ($responder, $resto) {
            $fila = TiendanubeOperacionTienda::query()->where('store_id', (int) ($resto[0] ?? 0))->first();
            $responder([
                'lease_token' => $fila?->lease_token,
                'estado' => $fila?->estado,
            ]);
        })(),
        'reserva-tomar' => (function () use ($responder, $resto) {
            $gobernador = TiendanubeGobernador::conectarPrueba((string) ($resto[0] ?? ''));
            $gobernador->anunciarDemanda((int) ($resto[1] ?? 0), 'publicacion', 'lote-e2c');
            $turno = $gobernador->reservarEscritura(
                (int) ($resto[1] ?? 0),
                'publicacion',
                (string) ($resto[2] ?? ''),
                'sin_escritura',
                0,
                null,
                (string) ($resto[3] ?? ''),
                60_000,
            );
            $responder(['concedido' => $turno->concedido, 'motivo' => $turno->motivo]);
        })(),
        'reserva-liberar' => (function () use ($responder, $resto) {
            $gobernador = TiendanubeGobernador::conectarPrueba((string) ($resto[0] ?? ''));
            $gobernador->liberarReserva((int) ($resto[1] ?? 0), (string) ($resto[2] ?? ''), (string) ($resto[3] ?? ''));
            $responder(['ok' => true]);
        })(),
        'reserva-estado' => (function () use ($responder, $resto) {
            $gobernador = TiendanubeGobernador::conectarPrueba((string) ($resto[0] ?? ''));
            $estado = $gobernador->estadoReserva((int) ($resto[1] ?? 0), (string) ($resto[2] ?? ''));
            $responder($estado);
        })(),
        'reserva-olvidar' => (function () use ($responder, $resto) {
            TiendanubeGobernador::conectarPrueba((string) ($resto[0] ?? ''))->olvidar();
            $responder(['ok' => true]);
        })(),
        'lock-tomar' => (function () use ($responder, $resto) {
            $lock = Cache::lock((string) ($resto[1] ?? ''), 30);
            if (! $lock->get()) {
                $responder(['obtuvo' => false]);

                return;
            }
            file_put_contents((string) ($resto[2] ?? '').'/held', '1');
            $limite = time() + 15;
            while (! is_file((string) ($resto[2] ?? '').'/soltar') && time() < $limite) {
                usleep(50_000);
            }
            $lock->release();
            file_put_contents((string) ($resto[2] ?? '').'/released', '1');
            $responder(['obtuvo' => true]);
        })(),
        'lock-liberar' => (function () use ($responder, $resto) {
            $lock = Cache::lock((string) ($resto[1] ?? ''), 30);
            $responder(['libero' => $lock->release()]);
        })(),
        'lock-intentar' => (function () use ($responder, $resto) {
            $lock = Cache::lock((string) ($resto[1] ?? ''), 30);
            $ok = $lock->get();
            if ($ok) {
                $lock->release();
            }
            $responder(['obtuvo' => $ok]);
        })(),
        default => throw new RuntimeException('acción desconocida'),
    };
} catch (Throwable $e) {
    fwrite(STDERR, $e::class.': '.$e->getMessage()."\n");
    exit(1);
}
