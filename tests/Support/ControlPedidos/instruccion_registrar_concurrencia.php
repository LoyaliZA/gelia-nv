<?php

/**
 * Concurrencia real sobre gelia_solicitud_211_test.
 * No usa la base de trabajo y no ejecuta migrate:fresh.
 *
 * Uso: php instruccion_registrar_concurrencia.php {setup|sostener|competir|bloqueo|afirmar} {duplicar|sustituir} {directorio}
 */

use App\Models\Almacen;
use App\Models\CatalogoTipoAlmacen;
use App\Models\Cliente;
use App\Models\ConfiguracionSistema;
use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\CatalogoModalidadPreparacionPedido;
use App\Models\ControlPedidos\CatalogoOrigenPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaCumplimientoFisico;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\Departamento;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\ControlPedidos\CambiarModalidadPreparacionService;
use App\Services\ControlPedidos\ConfirmarDespachoMunicipioService;
use App\Services\ControlPedidos\PreparacionTiendaConfig;
use App\Services\ControlPedidos\SolicitarRevisionRegistrarPedidosService;
use App\Support\ControlPedidos\InstruccionEntrega;
use App\Support\ControlPedidos\RevisionCapturaPedido;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;

$baseDatos = 'gelia_solicitud_211_test';
if ($baseDatos === 'laravel') {
    fwrite(STDERR, "Base rechazada.\n");
    exit(2);
}

$host = getenv('DB_HOST') ?: 'mysql';
$puerto = getenv('DB_PORT') ?: '3306';
$usuarioBd = getenv('DB_USERNAME') ?: 'sail';
$claveBd = getenv('DB_PASSWORD');
$claveBd = ($claveBd === false || $claveBd === '') ? 'password' : $claveBd;
$pdo = new PDO(
    "mysql:host={$host};port={$puerto}",
    $usuarioBd,
    $claveBd,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$pdo->exec("CREATE DATABASE IF NOT EXISTS `{$baseDatos}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
putenv('DB_CONNECTION=mysql');
putenv('DB_DATABASE='.$baseDatos);
$_ENV['DB_CONNECTION'] = 'mysql';
$_ENV['DB_DATABASE'] = $baseDatos;
$_SERVER['DB_CONNECTION'] = 'mysql';
$_SERVER['DB_DATABASE'] = $baseDatos;

require __DIR__.'/../../../vendor/autoload.php';
$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// La configuración cacheada de la aplicación no debe redirigir esta prueba
// a la base operativa. Fijar la conexión aislada antes de cualquier consulta.
$app['config']->set('database.default', 'mysql');
$app['config']->set('database.connections.mysql.host', $host);
$app['config']->set('database.connections.mysql.port', $puerto);
$app['config']->set('database.connections.mysql.database', $baseDatos);
$app['config']->set('database.connections.mysql.username', $usuarioBd);
$app['config']->set('database.connections.mysql.password', $claveBd);
$app['config']->set('app.env', 'testing');
$app['config']->set('broadcasting.default', 'null');
$app['config']->set('queue.default', 'sync');
$app['config']->set('cache.default', 'array');
DB::purge('mysql');

if (DB::connection()->getDatabaseName() !== $baseDatos) {
    fwrite(STDERR, 'Conexión inesperada: '.DB::connection()->getDatabaseName()."\n");
    exit(3);
}

$rol = $argv[1] ?? '';
$caso = $argv[2] ?? '';
$dir = $argv[3] ?? '';
if (! in_array($caso, ['duplicar', 'sustituir'], true) || $dir === '') {
    fwrite(STDERR, "Argumentos inválidos.\n");
    exit(2);
}

if (! Illuminate\Support\Facades\Schema::hasTable('migrations')) {
    Artisan::call('migrate', ['--force' => true]);
}
app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

$metaRuta = $dir.'/meta.json';
$held = $dir.'/held';
$release = $dir.'/release';

try {
    match ($rol) {
        'setup' => setup($caso, $metaRuta),
        'sostener' => sostener($caso, $metaRuta, $held, $release),
        'competir' => competir($caso, $metaRuta, $dir.'/competir.json'),
        'bloqueo' => bloqueo(),
        'afirmar' => afirmar($caso, $metaRuta, $dir.'/competir.json'),
        default => throw new RuntimeException('Rol desconocido.'),
    };
} catch (Throwable $e) {
    fwrite(STDERR, $e::class.': '.$e->getMessage()."\n");
    exit(1);
}

function setup(string $caso, string $metaRuta): void
{
    $usuario = usuario();
    $pedido = pedido($usuario);
    $tareaId = null;
    if ($caso === 'sustituir') {
        $tareaId = tareaMunicipal($pedido, $usuario);
    }
    $pedido = $pedido->fresh();
    file_put_contents($metaRuta, json_encode([
        'pedido_id' => $pedido->id,
        'usuario_id' => $usuario->id,
        'almacen_id' => $pedido->almacen_revision ?? null,
        'pdv_id' => pdv()->id,
        'tarea_id' => $tareaId,
        'revision' => (int) $pedido->revision_registro,
        'base' => RevisionCapturaPedido::base($pedido),
    ], JSON_THROW_ON_ERROR));
}

function sostener(string $caso, string $metaRuta, string $held, string $release): void
{
    $meta = meta($metaRuta);
    $usuario = User::query()->findOrFail($meta['usuario_id']);
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
    if ($caso === 'duplicar') {
        app(SolicitarRevisionRegistrarPedidosService::class)->ejecutar(
            PedidoBma::query()->findOrFail($meta['pedido_id']),
            $usuario,
            'preparacion_tienda',
            (int) $meta['pdv_id'],
            ['revision_esperada' => $meta['revision'], 'base_captura' => $meta['base']]
        );
    } else {
        $tarea = PedidoBmaTareaPreparacion::query()->findOrFail($meta['tarea_id']);
        app(CambiarModalidadPreparacionService::class)->ejecutar(
            $tarea,
            $usuario,
            CatalogoModalidadPreparacionPedido::CODIGO_RECOGE_TIENDA,
            'Cambio concurrente de prueba'
        );
    }
    DB::commit();
}

function competir(string $caso, string $metaRuta, string $salida): void
{
    $meta = meta($metaRuta);
    $usuario = User::query()->findOrFail($meta['usuario_id']);
    DB::statement('SET innodb_lock_wait_timeout = 20');
    $inicio = microtime(true);
    $mensaje = '';
    $ok = false;
    try {
        if ($caso === 'duplicar') {
            $resultado = app(SolicitarRevisionRegistrarPedidosService::class)->ejecutar(
                PedidoBma::query()->findOrFail($meta['pedido_id']),
                $usuario,
                'preparacion_tienda',
                (int) $meta['pdv_id'],
                ['revision_esperada' => $meta['revision'], 'base_captura' => $meta['base']]
            );
            $ok = (bool) $resultado['idempotente'];
        } else {
            $tarea = PedidoBmaTareaPreparacion::query()->findOrFail($meta['tarea_id']);
            app(ConfirmarDespachoMunicipioService::class)->ejecutar($tarea, $usuario, 'Transporte de prueba');
            $mensaje = 'el despacho no debió ejecutarse';
        }
    } catch (ValidationException $e) {
        $mensaje = $e->errors()['instruccion'][0] ?? $e->getMessage();
        $ok = $caso === 'sustituir' && $mensaje === InstruccionEntrega::MENSAJE_OBSOLETA;
    }
    file_put_contents($salida, json_encode([
        'ok' => $ok,
        'mensaje' => $mensaje,
        'segundos' => microtime(true) - $inicio,
    ], JSON_THROW_ON_ERROR));
    if (! $ok) {
        throw new RuntimeException($mensaje !== '' ? $mensaje : 'La operación concurrente no quedó contenida.');
    }
}

function bloqueo(): void
{
    try {
        $espera = DB::selectOne('SELECT COUNT(*) AS total FROM performance_schema.data_lock_waits');
        echo (int) ($espera->total ?? 0);
    } catch (Throwable) {
        echo '-1';
    }
}

function afirmar(string $caso, string $metaRuta, string $competirRuta): void
{
    $meta = meta($metaRuta);
    $competir = json_decode((string) file_get_contents($competirRuta), true, 512, JSON_THROW_ON_ERROR);
    if (($competir['segundos'] ?? 0) < 1) {
        throw new RuntimeException('La segunda operación no esperó el bloqueo.');
    }
    if ($caso === 'duplicar') {
        $tareas = PedidoBmaTareaPreparacion::query()->where('pedido_bma_id', $meta['pedido_id'])->count();
        if ($tareas !== 1 || ! ($competir['ok'] ?? false)) {
            throw new RuntimeException('Se creó más de una tarea o el reintento no fue idempotente.');
        }

        return;
    }
    $anterior = PedidoBmaTareaPreparacion::query()->findOrFail($meta['tarea_id']);
    $nueva = PedidoBmaTareaPreparacion::query()
        ->where('pedido_bma_id', $meta['pedido_id'])
        ->where('estado', '!=', PedidoBmaTareaPreparacion::ESTADO_SUSTITUIDA)
        ->first();
    $estado = $nueva?->cumplimientoFisico?->estado;
    if ($anterior->estado !== PedidoBmaTareaPreparacion::ESTADO_SUSTITUIDA
        || $estado !== PedidoBmaCumplimientoFisico::ESTADO_EMPACADA
        || ! ($competir['ok'] ?? false)) {
        throw new RuntimeException('La instrucción sustituida se ejecutó sobre la nueva.');
    }
}

function meta(string $ruta): array
{
    return json_decode((string) file_get_contents($ruta), true, 512, JSON_THROW_ON_ERROR);
}

function usuario(): User
{
    $departamento = Departamento::query()->firstOrCreate(['nombre' => 'Ventas 211'], ['activo' => true]);
    $usuario = User::factory()->create(['departamento_id' => $departamento->id]);
    foreach ([
        'control_pedidos.crear',
        'control_pedidos.preparacion.solicitar',
        'control_pedidos.tienda.despachar_municipio',
    ] as $permiso) {
        $usuario->givePermissionTo(Permission::findOrCreate($permiso, 'web'));
    }

    return $usuario;
}

function pedido(User $usuario): PedidoBma
{
    foreach ([
        CatalogoEstatusPedido::FASE_BORRADOR => 'BORRADOR',
        CatalogoEstatusPedido::FASE_PESAJE_PENDIENTE => 'PESAJE',
    ] as $fase => $codigo) {
        CatalogoEstatusPedido::query()->firstOrCreate(
            ['fase_ciclo' => $fase],
            ['codigo_interno' => $codigo, 'nombre_visual' => $fase, 'color_hex' => '#999999', 'orden' => 1, 'activo' => true]
        );
    }
    foreach ([
        CatalogoModalidadPreparacionPedido::CODIGO_RECOGE_TIENDA => 'Recoge en tienda',
        CatalogoModalidadPreparacionPedido::CODIGO_ENVIO_MUNICIPIO => 'Municipio',
    ] as $codigo => $nombre) {
        CatalogoModalidadPreparacionPedido::query()->firstOrCreate(
            ['codigo' => $codigo],
            ['nombre' => $nombre, 'activo' => true]
        );
    }
    ConfiguracionSistema::query()->updateOrCreate(
        ['clave' => PreparacionTiendaConfig::CLAVE_FLAG],
        ['valor' => json_encode([
            'activo' => true,
            'modalidades_habilitadas' => ['ENVIO_MUNICIPIO', 'RECOGE_TIENDA'],
            'departamentos_habilitados' => [],
            'almacenes_habilitados' => [],
            'usuarios_piloto' => [],
        ])]
    );
    app(PreparacionTiendaConfig::class)->olvidarCache();
    pdv();
    $listaId = DB::table('catalogo_listas_descuento')->insertGetId([
        'nombre' => 'Lista 211 '.uniqid(),
        'activo' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $cliente = Cliente::query()->create([
        'numero_cliente' => (string) random_int(10000, 99999),
        'nombre' => 'Cliente ficticio',
        'lista_actual_id' => $listaId,
        'vendedor_id' => $usuario->id,
    ]);
    $origen = CatalogoOrigenPedido::query()->create([
        'codigo' => 'ORI-'.uniqid(),
        'nombre' => 'Mostrador '.uniqid(),
        'requiere_logistica' => true,
        'activo' => true,
    ]);
    $salida = Almacen::query()->create([
        'codigo' => 'CED-'.uniqid(),
        'nombre' => 'Salida general',
        'activo' => true,
        'visible_en_pedidos' => true,
        'permite_salida_pedidos' => true,
    ]);

    $pedido = PedidoBma::query()->create([
        'folio' => 'INT-'.uniqid(),
        'folio_remision' => 'FOL-'.uniqid(),
        'fecha' => now()->toDateString(),
        'vendedor_id' => $usuario->id,
        'cliente_id' => $cliente->id,
        'departamento_operacion_id' => $usuario->departamento_id,
        'origen_id' => $origen->id,
        'almacen_id' => $salida->id,
        'sucursal_destino_id' => Sucursal::factory()->create(['activo' => true])->id,
        'catalogo_estatus_pedido_id' => CatalogoEstatusPedido::porFase(CatalogoEstatusPedido::FASE_BORRADOR)->id,
        'total_mercancia' => 100,
        'costo_envio' => 0,
        'saldo_a_favor' => 0,
        'costo_seguro' => 0,
        'total_a_cobrar' => 100,
        'aplica_seguro' => false,
        'es_resguardo' => false,
        'prioridad_md' => false,
        'solicitud_preparacion_json' => ['version' => 1, 'modalidad_entrega' => 'tienda'],
    ]);
    PedidoBmaDocumento::query()->create([
        'pedido_bma_id' => $pedido->id,
        'tipo' => PedidoBmaDocumento::TIPO_PDF_PEDIDO,
        'ruta_archivo' => 'pedidos_bma/pedido-ficticio.pdf',
        'nombre_original' => 'pedido-ficticio.pdf',
        'mime_type' => 'application/pdf',
        'tamano_bytes' => 1200,
        'orden' => 1,
        'activo' => true,
    ]);

    return $pedido;
}

function pdv(): Almacen
{
    $tipo = CatalogoTipoAlmacen::query()->firstOrCreate(['nombre' => 'PDV']);

    return Almacen::query()->firstOrCreate(
        ['codigo' => 'SUC-211-LOCK'],
        [
            'nombre' => 'Sucursal Centro',
            'activo' => true,
            'visible_en_pedidos' => true,
            'permite_salida_pedidos' => true,
            'tipo_almacen_id' => $tipo->id,
        ]
    );
}

function tareaMunicipal(PedidoBma $pedido, User $usuario): int
{
    $modalidad = CatalogoModalidadPreparacionPedido::query()
        ->where('codigo', CatalogoModalidadPreparacionPedido::CODIGO_ENVIO_MUNICIPIO)
        ->firstOrFail();
    $tarea = PedidoBmaTareaPreparacion::query()->create([
        'pedido_bma_id' => $pedido->id,
        'catalogo_modalidad_preparacion_id' => $modalidad->id,
        'almacen_id' => $pedido->almacen_id,
        'area_responsable_codigo' => 'TIENDA',
        'estado' => PedidoBmaTareaPreparacion::ESTADO_PENDIENTE,
        'solicitada_por_id' => $usuario->id,
        'solicitada_at' => now(),
        'version' => 1,
    ]);
    PedidoBmaCumplimientoFisico::query()->create([
        'pedido_bma_tarea_preparacion_id' => $tarea->id,
        'estado' => PedidoBmaCumplimientoFisico::ESTADO_EMPACADA,
        'cantidad' => 1,
        'ubicacion' => 'Mesa',
        'version' => 1,
    ]);

    return (int) $tarea->id;
}
