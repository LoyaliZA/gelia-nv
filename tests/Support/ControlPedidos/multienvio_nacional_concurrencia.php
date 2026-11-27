<?php

/**
 * Concurrencia real (InnoDB) de Multienvío nacional sobre la base MySQL de desarrollo que indica el .env
 * de la aplicación (decisión del usuario: sin base aislada). Esa base tiene datos reales del equipo:
 * este script solo crea filas sintéticas marcadas con MULTIENVIO-TEST-<aleatorio> y 'limpiar' borra,
 * por clave primaria, únicamente esas filas. No ejecuta migraciones.
 *
 * Requisito previo: migraciones 2026_11_27_100000 y 2026_11_27_100100 aplicadas (solo esas, con --path).
 *
 * Uso: php multienvio_nacional_concurrencia.php {setup|sostener|competir|bloqueo|afirmar|limpiar} {confirmar|activar} {directorio}
 */

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\CatalogoPaqueteriaPedido;
use App\Models\ControlPedidos\CatalogoTipoCajaPedido;
use App\Models\ControlPedidos\MultienvioNacional;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaCaja;
use App\Models\ControlPedidos\PedidoBmaDireccion;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Models\User;
use App\Services\ControlPedidos\Nacional\Multienvio\ConfirmarMultienvio;
use App\Services\ControlPedidos\Nacional\Multienvio\GuardarBorradorMultienvio;
use App\Services\ControlPedidos\Nacional\Multienvio\RegistrarDecisionMultienvio;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

if (getenv('MULTIENVIO_MYSQL') !== '1') {
    fwrite(STDERR, "Defina MULTIENVIO_MYSQL=1.\n");
    exit(2);
}

$rol = $argv[1] ?? '';
$caso = $argv[2] ?? '';
$dir = $argv[3] ?? '';
if (! in_array($caso, ['confirmar', 'activar'], true) || $dir === '' || ! is_dir($dir)) {
    fwrite(STDERR, "Argumentos inválidos.\n");
    exit(2);
}

$raiz = dirname(__DIR__, 3);
require $raiz.'/vendor/autoload.php';

// phpunit.xml fuerza DB_CONNECTION=sqlite y DB_DATABASE=:memory: y este proceso lo hereda; la conexión
// se toma del .env de la aplicación. Las credenciales no se imprimen ni se escriben en ningún archivo.
$dotenv = Dotenv\Dotenv::parse((string) file_get_contents($raiz.'/.env'));
$bd = [
    'DB_CONNECTION' => 'mysql',
    'DB_HOST' => (string) ($dotenv['DB_HOST'] ?? ''),
    'DB_PORT' => (string) ($dotenv['DB_PORT'] ?? '3306'),
    'DB_DATABASE' => (string) ($dotenv['DB_DATABASE'] ?? ''),
    'DB_USERNAME' => (string) ($dotenv['DB_USERNAME'] ?? ''),
    'DB_PASSWORD' => (string) ($dotenv['DB_PASSWORD'] ?? ''),
];
unset($dotenv);
if ($bd['DB_HOST'] !== 'mysql' || $bd['DB_DATABASE'] === '') {
    fwrite(STDERR, "Solo se admite el servidor MySQL de desarrollo (host 'mysql').\n");
    exit(2);
}
foreach ($bd as $clave => $valor) {
    putenv($clave.'='.$valor);
    $_ENV[$clave] = $valor;
    $_SERVER[$clave] = $valor;
}

$app = require $raiz.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$app['config']->set('database.default', 'mysql');
$app['config']->set('database.connections.mysql.host', $bd['DB_HOST']);
$app['config']->set('database.connections.mysql.port', $bd['DB_PORT']);
$app['config']->set('database.connections.mysql.database', $bd['DB_DATABASE']);
$app['config']->set('database.connections.mysql.username', $bd['DB_USERNAME']);
$app['config']->set('database.connections.mysql.password', $bd['DB_PASSWORD']);
unset($bd);
$app['config']->set('app.env', 'testing');
$app['config']->set('broadcasting.default', 'null');
$app['config']->set('queue.default', 'sync');
$app['config']->set('cache.default', 'array');
// Las copias físicas del soporte van al directorio de la corrida, nunca al almacenamiento real.
@mkdir($dir.'/public', 0777, true);
@mkdir($dir.'/local', 0777, true);
$app['config']->set('filesystems.disks.public.root', $dir.'/public');
$app['config']->set('filesystems.disks.local.root', $dir.'/local');
DB::purge('mysql');

if (DB::connection()->getDriverName() !== 'mysql') {
    fwrite(STDERR, "Conexión inesperada.\n");
    exit(3);
}

$metaRuta = $dir.'/meta.json';

try {
    if ($rol !== 'limpiar' && $rol !== 'bloqueo'
        && (! Schema::hasTable('multienvios_nacionales') || ! Schema::hasColumn('pedidos_bma', 'multienvio_nacional_id'))) {
        throw new RuntimeException('Requisito previo: aplique solo las migraciones 2026_11_27_100000 y 2026_11_27_100100 (--path).');
    }
    match ($rol) {
        'setup' => setup($caso, $metaRuta),
        'sostener' => sostener($caso, $metaRuta, $dir.'/held', $dir.'/release'),
        'competir' => competir($caso, $metaRuta, $dir),
        'bloqueo' => bloqueo($dir),
        'afirmar' => afirmar($caso, $metaRuta, $dir),
        'limpiar' => limpiar($metaRuta),
        default => throw new RuntimeException('Rol desconocido.'),
    };
} catch (Throwable $e) {
    fwrite(STDERR, $e::class.': '.$e->getMessage()."\n");
    exit(1);
}

function setup(string $caso, string $metaRuta): void
{
    $marca = 'MULTIENVIO-TEST-'.bin2hex(random_bytes(4));
    $meta = [
        'marca' => $marca,
        'base' => ['pedidos' => DB::table('pedidos_bma')->count(), 'users' => DB::table('users')->count()],
    ];
    guardar($metaRuta, $meta);

    // El usuario va primero y se anota de inmediato: 'limpiar' parte de él aunque el resto falle.
    $usuario = User::query()->forceCreate([
        'name' => $marca,
        'email' => strtolower($marca).'@example.invalid',
        'password' => Hash::make(Str::random(32)),
    ]);
    $meta['usuario_id'] = (int) $usuario->id;
    guardar($metaRuta, $meta);

    $paqueteria = CatalogoPaqueteriaPedido::query()->create([
        'nombre' => $marca, 'categoria' => CatalogoPaqueteriaPedido::CATEGORIA_COMERCIAL, 'requiere_peso' => true,
        'activo' => true, 'modalidad_tarifa' => CatalogoPaqueteriaPedido::MODALIDAD_FIJA,
    ]);
    $meta['paqueteria_id'] = (int) $paqueteria->id;
    $tipoCaja = CatalogoTipoCajaPedido::query()->create([
        'nombre' => $marca, 'peso_volumetrico' => 10, 'largo' => 46, 'ancho' => 32, 'alto' => 35, 'activo' => true,
    ]);
    $meta['tipo_caja_id'] = (int) $tipoCaja->id;
    guardar($metaRuta, $meta);

    // Catálogo existente: solo se lee (no se crea ni se modifica).
    $estatus = CatalogoEstatusPedido::query()->where('fase_ciclo', CatalogoEstatusPedido::FASE_PESAJE_RESPONDIDO)->firstOrFail();

    $origen = PedidoBma::query()->create([
        'folio' => $marca,
        'folio_remision' => $marca.'-WIZ',
        'fecha' => now()->toDateString(),
        'vendedor_id' => $usuario->id,
        'catalogo_estatus_pedido_id' => $estatus->id,
        'catalogo_paqueteria_id' => $paqueteria->id,
        'solicitud_preparacion_json' => [
            'modalidad_entrega' => 'nacional',
            'captura_nacional' => 'compartida',
            'codigo_modalidad' => 'ENVIO_BODEGA_NORMAL',
            'piezas_nacionales' => [['client_uuid' => 'p-1', 'descripcion' => 'Pieza 1']],
            'piezas_nacionales_vigentes' => [['client_uuid' => 'p-1', 'descripcion' => 'Pieza 1']],
            'respuestas_piezas_nacionales' => ['p-1' => 1],
            'cotizacion_nacional' => ['total' => 1800],
        ],
        'estatus_envio' => PedidoBma::ESTATUS_ENVIO_PESAJE_LISTO,
        'pesaje_solicitado_at' => now()->subHour(),
        'pesaje_respondido_at' => now()->subMinutes(30),
        'pesaje_respondido_por_id' => $usuario->id,
        'consulta_cerrada_at' => now()->subMinutes(10),
        'consulta_cerrada_por_id' => $usuario->id,
        'consulta_actualizacion_pendiente' => false,
        'numero_cajas' => 3,
        'peso_real_kg' => 36,
        'peso_cobrado_guia_kg' => 36,
        'total_mercancia' => 1500,
        'costo_envio' => 270,
        'saldo_a_favor' => 0,
        'costo_seguro' => 0,
        'total_a_cobrar' => 1770,
        'aplica_seguro' => false,
        'es_resguardo' => false,
    ]);
    $meta['pedido_id'] = (int) $origen->id;
    guardar($metaRuta, $meta);

    $cajas = [];
    foreach ([10, 12, 14] as $i => $peso) {
        $cajas[] = $origen->cajas()->create([
            'uuid_operativo' => (string) Str::uuid(),
            'catalogo_tipo_caja_id' => $tipoCaja->id,
            'orden' => $i,
            'largo' => 46, 'ancho' => 32, 'alto' => 35,
            'peso_real_kg' => $peso, 'peso_volumetrico_kg' => 10.304, 'peso_cobrado_kg' => $peso,
            'costo_envio' => 90, 'costo_seguro' => 0,
            'estado_operativo' => PedidoBmaCaja::ESTADO_ACTIVA,
        ]);
    }
    $meta['cajas'] = array_map(fn (PedidoBmaCaja $c) => (int) $c->id, $cajas);

    if ($caso === 'confirmar') {
        Storage::disk('public')->put('pedidos_bma/'.$marca.'.pdf', 'PDF');
        $origen->documentos()->create(['tipo' => PedidoBmaDocumento::TIPO_PDF_PEDIDO, 'ruta_archivo' => 'pedidos_bma/'.$marca.'.pdf', 'nombre_original' => $marca.'.pdf', 'activo' => true]);
        $origen->documentos()->create(['tipo' => PedidoBmaDocumento::TIPO_EVIDENCIA_CONDICION, 'ruta_archivo' => 'pedidos_bma/'.$marca.'-caja2.jpg', 'nombre_original' => 'caja2.jpg', 'pedido_bma_caja_id' => $cajas[1]->id, 'relacion_tipo' => PedidoBmaDocumento::RELACION_ENVIO_CAJA, 'relacion_id' => $cajas[1]->id, 'activo' => true]);
        PedidoBmaDireccion::query()->create([
            'pedido_bma_id' => $origen->id, 'version_snapshot' => 1, 'es_vigente' => true, 'nombre_destinatario' => $marca,
            'calle' => 'Calle 1', 'colonia' => 'Centro', 'codigo_postal' => '01000', 'municipio' => 'CDMX', 'estado' => 'CDMX',
            'origen' => PedidoBmaDireccion::ORIGEN_MANUAL,
        ]);

        $grupo = app(RegistrarDecisionMultienvio::class)->ejecutar($origen->fresh(), $usuario->id, 'activar');
        $meta['grupo_id'] = (int) $grupo->id;
        guardar($metaRuta, $meta);
        $envios = [];
        foreach ($cajas as $i => $caja) {
            $envios[] = ['uuid_operativo' => $caja->uuid_operativo, 'folio_remision' => $marca.'-F'.($i + 1), 'total_mercancia' => [600, 500, 400][$i], 'contenido' => 'Caja '.($i + 1)];
        }
        app(GuardarBorradorMultienvio::class)->ejecutar($grupo, $usuario->id, $envios);
        $meta['revision'] = (string) $grupo->fresh()->revision_consulta;
    }
    guardar($metaRuta, $meta);
}

function sostener(string $caso, string $metaRuta, string $held, string $release): void
{
    $meta = meta($metaRuta);
    $usuarioId = (int) $meta['usuario_id'];
    // Solo se bloquea el pedido sintético de esta corrida.
    DB::beginTransaction();
    PedidoBma::query()->whereKey($meta['pedido_id'])->lockForUpdate()->firstOrFail();
    file_put_contents($held, '1');
    $limite = time() + 25;
    while (! is_file($release) && time() < $limite) {
        usleep(100000);
    }
    if (! is_file($release)) {
        DB::rollBack();
        throw new RuntimeException('Nadie liberó el bloqueo.');
    }
    if ($caso === 'confirmar') {
        $grupo = app(ConfirmarMultienvio::class)->ejecutar(
            MultienvioNacional::query()->findOrFail($meta['grupo_id']), $usuarioId, $meta['revision']
        );
    } else {
        $grupo = app(RegistrarDecisionMultienvio::class)->ejecutar(
            PedidoBma::query()->findOrFail($meta['pedido_id']), $usuarioId, 'activar'
        );
    }
    DB::commit();
    echo json_encode(['grupo_id' => (int) $grupo->id, 'estado' => $grupo->estado], JSON_THROW_ON_ERROR);
}

function competir(string $caso, string $metaRuta, string $dir): void
{
    $meta = meta($metaRuta);
    $usuarioId = (int) $meta['usuario_id'];
    DB::statement('SET SESSION innodb_lock_wait_timeout = 20');
    file_put_contents($dir.'/competir-conexion', (string) DB::selectOne('SELECT CONNECTION_ID() AS id')->id);
    // Los modelos se cargan antes de la carrera, como en una petición que llega con la página abierta.
    $grupo = $caso === 'confirmar' ? MultienvioNacional::query()->findOrFail($meta['grupo_id']) : null;
    $pedido = PedidoBma::query()->findOrFail($meta['pedido_id']);
    $inicio = microtime(true);
    $resultado = ['ok' => false, 'error' => null, 'grupo_id' => null, 'estado' => null];
    try {
        $r = $caso === 'confirmar'
            ? app(ConfirmarMultienvio::class)->ejecutar($grupo, $usuarioId, $meta['revision'])
            : app(RegistrarDecisionMultienvio::class)->ejecutar($pedido, $usuarioId, 'activar');
        $resultado['grupo_id'] = (int) $r->id;
        $resultado['estado'] = $r->estado;
        $resultado['ok'] = true;
    } catch (Throwable $e) {
        $resultado['error'] = $e::class.': '.$e->getMessage();
    }
    $resultado['inicio'] = $inicio;
    $resultado['fin'] = microtime(true);
    $resultado['segundos'] = $resultado['fin'] - $inicio;
    file_put_contents($dir.'/competir.json', json_encode($resultado, JSON_THROW_ON_ERROR));
    if (! $resultado['ok']) {
        throw new RuntimeException('La operación concurrente lanzó: '.$resultado['error']);
    }
}

/**
 * El usuario de la base no tiene acceso a performance_schema (data_lock_waits). Se consulta la sesión del
 * proceso que compite en information_schema.PROCESSLIST (visible para el mismo usuario): debe estar
 * ejecutando el SELECT ... FOR UPDATE desde hace al menos un segundo.
 */
function bloqueo(string $dir): void
{
    $conexion = (int) @file_get_contents($dir.'/competir-conexion');
    $fila = $conexion > 0
        ? DB::selectOne('SELECT COMMAND, TIME, STATE, INFO FROM information_schema.PROCESSLIST WHERE ID = ?', [$conexion])
        : null;
    $info = (string) ($fila->INFO ?? '');
    echo json_encode([
        'conexion' => $conexion,
        'command' => $fila->COMMAND ?? null,
        'time' => isset($fila->TIME) ? (int) $fila->TIME : null,
        'state' => $fila->STATE ?? null,
        'consulta' => mb_substr($info, 0, 160),
        'esperando' => $fila !== null && in_array($fila->COMMAND ?? '', ['Query', 'Execute'], true)
            && stripos($info, 'for update') !== false && (int) ($fila->TIME ?? 0) >= 1,
    ], JSON_THROW_ON_ERROR);
}

function afirmar(string $caso, string $metaRuta, string $dir): void
{
    $meta = meta($metaRuta);
    $competir = json_decode((string) file_get_contents($dir.'/competir.json'), true, 512, JSON_THROW_ON_ERROR);
    $sostener = json_decode((string) file_get_contents($dir.'/sostener-out.txt'), true, 512, JSON_THROW_ON_ERROR);
    $liberado = (float) file_get_contents($dir.'/release');
    $fallas = [];
    $exigir = function (bool $condicion, string $mensaje) use (&$fallas): void {
        if (! $condicion) {
            $fallas[] = $mensaje;
        }
    };

    $exigir(($competir['ok'] ?? false) === true, 'la segunda llamada lanzó una excepción: '.($competir['error'] ?? ''));
    $exigir(($competir['segundos'] ?? 0) >= 1, 'la segunda llamada no esperó el bloqueo');
    $exigir(($competir['fin'] ?? 0) >= $liberado, 'la segunda llamada terminó antes de liberar el bloqueo');

    $grupos = MultienvioNacional::query()->where('pedido_origen_id', $meta['pedido_id'])->get();
    $exigir($grupos->count() === 1, 'hay '.$grupos->count().' grupos para el pedido de origen');
    $grupo = $grupos->first();
    $exigir((int) ($sostener['grupo_id'] ?? 0) === (int) $grupo?->id, 'la primera llamada devolvió otro grupo');
    $exigir((int) ($competir['grupo_id'] ?? 0) === (int) $grupo?->id, 'la segunda llamada devolvió otro grupo');

    if ($caso === 'activar') {
        $exigir($grupo?->estado === MultienvioNacional::ESTADO_CONFIGURANDO, 'el grupo no quedó en configuración');
        $exigir(PedidoBma::withTrashed()->where('vendedor_id', $meta['usuario_id'])->count() === 1, 'la activación creó pedidos');
    } else {
        $exigir($grupo?->estado === MultienvioNacional::ESTADO_ACTIVO, 'el grupo no quedó activo');
        $exigir(($competir['estado'] ?? null) === MultienvioNacional::ESTADO_ACTIVO, 'la segunda llamada no devolvió el grupo activo');
        $activos = MultienvioNacional::query()->where('pedido_origen_id', $meta['pedido_id'])->where('estado', MultienvioNacional::ESTADO_ACTIVO)->count();
        $exigir($activos === 1, 'hay '.$activos.' grupos activos');

        $miembros = PedidoBma::withTrashed()->where('multienvio_nacional_id', $grupo?->id)->orderBy('multienvio_orden')->get();
        $exigir($miembros->count() === 3, 'el grupo tiene '.$miembros->count().' miembros (se esperaban 3)');
        $exigir($miembros->pluck('multienvio_orden')->map(fn ($v) => (int) $v)->all() === [1, 2, 3], 'órdenes de envío inesperados');
        $exigir((int) $miembros->first()?->id === (int) $meta['pedido_id'], 'el origen no es el envío 1');
        $exigir(PedidoBma::withTrashed()->where('vendedor_id', $meta['usuario_id'])->count() === 3, 'se crearon pedidos de más');
        $exigir(abs((float) $miembros->sum('total_mercancia') - 1500) < 0.001, 'la mercancía repartida no suma 1500');
        $exigir($miembros->pluck('folio')->unique()->count() === 3, 'folios de pedido repetidos');

        $dueños = PedidoBmaCaja::query()->whereIn('id', $meta['cajas'])->orderBy('id')->pluck('pedido_bma_id', 'id')->map(fn ($v) => (int) $v)->all();
        $exigir(count($dueños) === 3 && count(array_unique($dueños)) === 3, 'las cajas no quedaron una por envío');
        $exigir(array_values($dueños) === $miembros->pluck('id')->map(fn ($v) => (int) $v)->all(), 'reparto de cajas inesperado');
        foreach ($miembros as $miembro) {
            $activas = PedidoBmaCaja::query()->where('pedido_bma_id', $miembro->id)->where('estado_operativo', PedidoBmaCaja::ESTADO_ACTIVA)->count();
            $exigir($activas === 1, 'el envío '.$miembro->multienvio_orden.' tiene '.$activas.' cajas activas');
            $historial = DB::table('pedido_bma_historial_estados')->where('pedido_bma_id', $miembro->id)->where('accion', AccionesHistorialPedidoBma::MULTIENVIO_CONFIRMADO)->count();
            $exigir($historial === 1, 'el envío '.$miembro->multienvio_orden.' tiene '.$historial.' registros de confirmación');
            $referencias = DB::table('pedido_bma_referencias')->where('pedido_bma_id', $miembro->id)->where('vigente', true)->count();
            $exigir($referencias === 1, 'el envío '.$miembro->multienvio_orden.' tiene '.$referencias.' referencias vigentes');
        }
    }

    if ($fallas !== []) {
        throw new RuntimeException('Fallas: '.implode('; ', $fallas));
    }
    echo json_encode(['ok' => true, 'grupo_id' => (int) $grupo->id, 'segundos_espera' => round((float) $competir['segundos'], 3)], JSON_THROW_ON_ERROR);
}

/**
 * Borra, por clave primaria y en orden seguro para las FK, solo lo creado en esta corrida: todo cuelga del
 * usuario sintético (vendedor de los pedidos y autor del grupo). Antes de borrar pedidos y usuario comprueba,
 * en cada tabla con FK hacia ellos, que no quede nada; si queda, no borra y lo informa.
 */
function limpiar(string $metaRuta): void
{
    $meta = is_file($metaRuta) ? meta($metaRuta) : [];
    $marca = (string) ($meta['marca'] ?? '');
    if (! str_starts_with($marca, 'MULTIENVIO-TEST-')) {
        echo json_encode(['ok' => true, 'nota' => 'sin corrida que limpiar'], JSON_THROW_ON_ERROR);

        return;
    }
    $usuarioId = (int) ($meta['usuario_id'] ?? DB::table('users')->where('name', $marca)->value('id'));
    $borrados = [];
    $borrar = function (string $tabla, array $ids) use (&$borrados): void {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if ($ids === []) {
            return;
        }
        $borrados[$tabla] = ($borrados[$tabla] ?? 0) + DB::table($tabla)->whereIn('id', $ids)->delete();
    };

    $pedidos = $usuarioId > 0 ? DB::table('pedidos_bma')->where('vendedor_id', $usuarioId)->pluck('id')->all() : [];
    if (isset($meta['pedido_id'])) {
        $pedidos[] = (int) $meta['pedido_id'];
    }
    $pedidos = array_values(array_unique(array_map('intval', $pedidos)));
    $grupos = $pedidos !== [] && Schema::hasTable('multienvios_nacionales')
        ? DB::table('multienvios_nacionales')->whereIn('pedido_origen_id', $pedidos)->pluck('id')->all()
        : [];

    if ($pedidos !== []) {
        $cajas = DB::table('pedido_bma_cajas')->whereIn('pedido_bma_id', $pedidos)->pluck('id')->all();
        $borrar('pedido_bma_documentos', DB::table('pedido_bma_documentos')
            ->where(fn ($q) => $q->whereIn('pedido_bma_id', $pedidos)->orWhereIn('pedido_bma_caja_id', $cajas ?: [0]))->pluck('id')->all());
        $borrar('pedido_bma_cajas', $cajas);
        foreach (['pedido_bma_historial_estados', 'pedido_bma_referencias', 'pedido_bma_direcciones'] as $tabla) {
            $borrar($tabla, DB::table($tabla)->whereIn('pedido_bma_id', $pedidos)->pluck('id')->all());
        }
        if (Schema::hasColumn('pedidos_bma', 'multienvio_nacional_id')) {
            DB::table('pedidos_bma')->whereIn('id', $pedidos)->whereNotNull('multienvio_nacional_id')->update(['multienvio_nacional_id' => null, 'multienvio_orden' => null]);
        }
    }

    $restos = referencias('pedidos_bma', $pedidos, ['multienvios_nacionales.pedido_origen_id']);
    if ($restos === []) {
        $borrar('multienvios_nacionales', $grupos);
        $borrar('pedidos_bma', $pedidos);
        $restos = referencias('users', [$usuarioId]);
        if ($restos === []) {
            $borrar('users', [$usuarioId]);
            $borrar('catalogo_paqueterias_pedido', DB::table('catalogo_paqueterias_pedido')->where('nombre', $marca)->pluck('id')->all());
            $borrar('catalogo_tipos_caja_pedido', DB::table('catalogo_tipos_caja_pedido')->where('nombre', $marca)->pluck('id')->all());
        }
    }

    $quedan = [
        'pedidos' => DB::table('pedidos_bma')->where('folio', 'like', $marca.'%')->orWhere('vendedor_id', $usuarioId)->pluck('id')->all(),
        'users' => DB::table('users')->where('name', $marca)->pluck('id')->all(),
        'grupos' => Schema::hasTable('multienvios_nacionales') && $pedidos !== [] ? DB::table('multienvios_nacionales')->whereIn('pedido_origen_id', $pedidos)->pluck('id')->all() : [],
        'paqueterias' => DB::table('catalogo_paqueterias_pedido')->where('nombre', $marca)->pluck('id')->all(),
        'tipos_caja' => DB::table('catalogo_tipos_caja_pedido')->where('nombre', $marca)->pluck('id')->all(),
        'referencias_a_filas_sinteticas' => $restos,
    ];
    $quedan = array_filter($quedan);
    $despues = ['pedidos' => DB::table('pedidos_bma')->count(), 'users' => DB::table('users')->count()];
    echo json_encode([
        'ok' => $quedan === [],
        'marca' => $marca,
        'pedidos_ids' => $pedidos,
        'usuario_id' => $usuarioId,
        'grupos_ids' => $grupos,
        'borrados' => $borrados,
        'quedan' => $quedan,
        'antes' => $meta['base'] ?? null,
        'despues' => $despues,
    ], JSON_THROW_ON_ERROR);
    if ($quedan !== []) {
        throw new RuntimeException('LIMPIEZA INCOMPLETA: '.json_encode($quedan));
    }
}

/**
 * Filas de cualquier tabla con FK hacia $tabla que aún apunten a $ids.
 *
 * @param  list<int>  $ids
 * @param  list<string>  $excluir  "tabla.columna" que se resuelven aparte
 * @return array<string, int>
 */
function referencias(string $tabla, array $ids, array $excluir = []): array
{
    $ids = array_values(array_filter(array_map('intval', $ids)));
    if ($ids === []) {
        return [];
    }
    $fks = DB::select('SELECT TABLE_NAME AS t, COLUMN_NAME AS c FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME = ?', [$tabla]);
    $restos = [];
    foreach ($fks as $fk) {
        $clave = $fk->t.'.'.$fk->c;
        if (in_array($clave, $excluir, true)) {
            continue;
        }
        $n = DB::table($fk->t)->whereIn($fk->c, $ids)->count();
        if ($n > 0) {
            $restos[$clave] = $n;
        }
    }

    return $restos;
}

function guardar(string $ruta, array $meta): void
{
    file_put_contents($ruta, json_encode($meta, JSON_THROW_ON_ERROR));
}

function meta(string $ruta): array
{
    return json_decode((string) file_get_contents($ruta), true, 512, JSON_THROW_ON_ERROR);
}
