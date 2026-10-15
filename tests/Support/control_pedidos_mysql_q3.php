<?php
/** Harness explícito, exclusivamente en el contenedor/red desechables de Q3. */
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\CatalogoOrigenPedido;
use App\Models\ControlPedidos\CatalogoModalidadPreparacionPedido;
use App\Models\Almacen;
use App\Models\User;
use App\Services\ControlPedidos\ActualizarPedidoBmaService;
use App\Services\ControlPedidos\CrearTareaPreparacionService;
use App\Services\ControlPedidos\PreparacionTiendaConfig;

if (getenv('Q3_MYSQL_DESECHABLE') !== 'SI' || getenv('DB_HOST') !== 'gelia-q3-mysql' || getenv('DB_DATABASE') !== 'gelia_q3_pruebas') throw new RuntimeException('Se requiere el MySQL desechable específico de Q3.');
config(['app.env' => 'testing', 'database.default' => 'mysql', 'database.connections.mysql.host' => 'gelia-q3-mysql', 'database.connections.mysql.database' => 'gelia_q3_pruebas', 'database.connections.mysql.username' => 'root', 'database.connections.mysql.password' => 'q3-solo-pruebas', 'database.connections.mysql.url' => null, 'cache.default' => 'array', 'queue.default' => 'sync', 'broadcasting.default' => 'null', 'mail.default' => 'array']);
set_exception_handler(function (Throwable $error) { fwrite(STDERR, $error->getMessage()."\n"); exit(1); });
DB::purge();
if (DB::connection()->getDatabaseName() !== 'gelia_q3_pruebas') throw new RuntimeException('Conexión inesperada.');
Illuminate\Support\Facades\Notification::fake();
$app->instance(App\Services\ControlPedidos\NotificarPedidoBmaService::class, Mockery::mock(App\Services\ControlPedidos\NotificarPedidoBmaService::class)->shouldReceive('ejecutar')->zeroOrMoreTimes()->getMock());
$app->instance(PreparacionTiendaConfig::class, new class extends PreparacionTiendaConfig {
    public function activo(): bool { return true; }
    public function usuarioHabilitado(User $u): bool { return true; }
    public function modalidadPermitida(string $codigo): bool { return true; }
    public function almacenPermitido(int $id): bool { return true; }
});
if (getenv('Q3_REUTILIZAR_ESQUEMA_TEST') !== 'SI') {
    $exit = Artisan::call('migrate:fresh', ['--force' => true]);
    if ($exit !== 0) throw new RuntimeException(Artisan::output());
}
if (!Illuminate\Support\Facades\Schema::hasColumn('pedidos_bma', 'captura_creacion_base')) throw new RuntimeException('Esquema de prueba desactualizado.');
echo "Esquema efímero preparado.\n";
$u = User::factory()->create();
$u->givePermissionTo(Spatie\Permission\Models\Permission::findOrCreate('control_pedidos.crear', 'web'));
$u->givePermissionTo(Spatie\Permission\Models\Permission::findOrCreate('control_pedidos.preparacion.solicitar', 'web'));
$e = CatalogoEstatusPedido::firstOrCreate(['fase_ciclo' => 'BORRADOR'], ['codigo_interno' => 'BORRADOR', 'nombre_visual' => 'Borrador', 'color_hex' => '#999999', 'activo' => true, 'orden' => 1]);
CatalogoEstatusPedido::firstOrCreate(['fase_ciclo' => 'PESAJE_PENDIENTE'], ['codigo_interno' => 'PESAJE_PENDIENTE', 'nombre_visual' => 'Consulta', 'color_hex' => '#999999', 'activo' => true, 'orden' => 2]);
CatalogoModalidadPreparacionPedido::firstOrCreate(['codigo' => 'ENVIO_BODEGA_NORMAL'], ['nombre' => 'Bodega', 'activo' => true]);
$o = CatalogoOrigenPedido::firstOrCreate(['nombre' => 'Q3'], ['requiere_logistica' => true, 'activo' => true]);
$a = Almacen::firstOrCreate(['nombre' => 'Q3'], ['codigo' => 'Q3-'.uniqid(), 'activo' => true, 'permite_salida_pedidos' => true]);
function nuevoPedido($u, $e, $o, $a, $n) {
    $p = PedidoBma::create(['folio' => 'Q3-'.uniqid().'-'.$n, 'fecha' => '2026-10-15', 'vendedor_id' => $u->id, 'catalogo_estatus_pedido_id' => $e->id, 'origen_id' => $o->id, 'almacen_id' => $a->id]);
    $p->documentos()->create(['tipo' => 'pdf_pedido', 'ruta_archivo' => 'q3/soporte.pdf', 'nombre_original' => 'soporte.pdf', 'activo' => true]);
    return $p->fresh();
}
function leer($socket) {
    stream_set_timeout($socket, 30);
    $v = fgets($socket);
    if ($v === false) throw new RuntimeException('Barrera agotada.');
    return trim($v);
}
function carrera(PedidoBma $pedido, User $u, string $tipo, string $claveB): void {
    $base = ['revision_esperada' => (int) $pedido->revision_registro, 'base_captura' => $pedido->base_captura];
    $pares = [stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0), stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0)];
    DB::disconnect();
    $pids = [];
    for ($i = 0; $i < 2; $i++) {
        $pid = pcntl_fork();
        if ($pid === 0) {
            $sock = $pares[$i][1];
            try {
                DB::purge();
                $p = PedidoBma::findOrFail($pedido->id);
                fwrite($sock, "LISTO\n");
                leer($sock);
                if ($i === 0) { DB::beginTransaction(); PedidoBma::whereKey($p->id)->lockForUpdate()->firstOrFail(); }
                if ($tipo === 'captura') app(ActualizarPedidoBmaService::class)->ejecutar($p, $base + ['comentarios_drive' => 'escritor-'.$i], $u->id);
                else app(CrearTareaPreparacionService::class)->ejecutar($p, $u, 'ENVIO_BODEGA_NORMAL', $p->almacen_id, null, $i === 0 ? 'q3-'.$p->id : $claveB, [], [], $base);
                if ($i === 0) { fwrite($sock, "APLICADO\n"); leer($sock); DB::commit(); }
                fwrite($sock, "OK\n");
                exit(0);
            } catch (App\Exceptions\ControlPedidos\ConflictoCapturaPedido $error) { fwrite($sock, "CONFLICTO\n"); exit(0); }
            catch (Throwable $error) { fwrite($sock, 'ERROR:'.$error->getMessage()."\n"); exit(1); }
        }
        if ($pid < 0) throw new RuntimeException('No se pudo crear proceso.');
        $pids[] = $pid;
        if (leer($pares[$i][0]) !== 'LISTO') throw new RuntimeException('Worker no preparado.');
        if ($i === 0) { fwrite($pares[0][0], "INICIAR\n"); if (leer($pares[0][0]) !== 'APLICADO') throw new RuntimeException('Primer writer no alcanzó la barrera transaccional.'); }
    }
    fwrite($pares[1][0], "INICIAR\n");
    // Observe an actual InnoDB lock wait before allowing the first transaction to commit.
    $espera = false;
    $limite = microtime(true) + 10;
    do {
        $espera = (int) DB::selectOne('select count(*) as n from performance_schema.data_lock_waits')->n > 0;
        if (!$espera) usleep(20000);
    } while (!$espera && microtime(true) < $limite);
    fwrite($pares[0][0], "COMMIT\n");
    $r = [leer($pares[0][0]), leer($pares[1][0])];
    foreach ($pids as $pid) pcntl_waitpid($pid, $status);
    if (!$espera) throw new RuntimeException('No se observó contención real de InnoDB.');
    $esperado = $tipo === 'captura' || $claveB !== 'q3-'.$pedido->id ? ['OK', 'CONFLICTO'] : ['OK', 'OK'];
    if ($r !== $esperado) throw new RuntimeException('Resultados inesperados: '.json_encode($r));
    if ($tipo === 'captura' && (int) $pedido->fresh()->revision_registro !== 1) throw new RuntimeException('Revisión incorrecta.');
    if ($tipo !== 'captura' && $pedido->tareasPreparacion()->count() !== 1) throw new RuntimeException('Preparación duplicada.');
    if ($tipo !== 'captura' && DB::table('pedido_bma_historial_estados')->where('pedido_bma_id', $pedido->id)->count() !== 1) throw new RuntimeException('Historial duplicado.');
    echo $tipo.' '.json_encode($r)."; espera InnoDB verificada.\n";
}
$p = nuevoPedido($u, $e, $o, $a, 1); carrera($p, $u, 'captura', '');
$p = nuevoPedido($u, $e, $o, $a, 2); carrera($p, $u, 'preparacion-misma-clave', 'q3-'.$p->id);
$p = nuevoPedido($u, $e, $o, $a, 3); carrera($p, $u, 'preparacion-claves-distintas', 'q3-otra');
echo "Q3 MySQL: tres carreras verificadas sobre servicios reales.\n";
