<?php

require dirname(__DIR__, 3).'/vendor/autoload.php';

use App\Events\PuntoVenta\PausaIniciada;
use App\Events\PuntoVenta\TurnoAsignado;
use App\Services\PuntoVenta\Operacion\IniciarPausaPdvService;
use App\Services\PuntoVenta\Turnos\MatchmakerTurnosPdvService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\Support\PuntoVenta\MySqlMedicionA34;

[$script, $directorio, $nombre, $modo, $sucursalId, $usuarioId] = $argv;
$esperar = function (string $marca) use ($directorio): void {
    $limite = microtime(true) + 15;
    while (! is_file($directorio.'/'.$marca)) {
        if (microtime(true) > $limite) {
            throw new RuntimeException('Barrera vencida: '.$marca);
        }
        usleep(10000);
        clearstatcache();
    }
};
try {
    $app = MySqlMedicionA34::application();
    Carbon::setTestNow(Carbon::parse('2026-10-14 10:00:00', 'America/Mexico_City'));
    Event::fake([TurnoAsignado::class, PausaIniciada::class]);
    Queue::fake();
    config(['punto_venta.medicion.estado' => 'desactivada']);
    DB::statement('SET SESSION innodb_lock_wait_timeout = 5');
    $conexion = DB::selectOne('SELECT CONNECTION_ID() AS id')->id;
    $barreraUsada = false;
    DB::connection()->beforeExecuting(function ($sql) use ($modo, &$barreraUsada, $directorio, $nombre, $esperar) {
        $punto = $modo === 'pausa' ? 'insert into `pdv_intervalos_operativos`' : 'insert into `pdv_turno_atenciones`';
        if (! $barreraUsada && str_starts_with($sql, $punto)) {
            $barreraUsada = true;
            touch($directorio.'/'.$nombre.'.validado');
            $esperar($nombre.'.liberar');
        }
    });
    touch($directorio.'/'.$nombre.'.listo');
    $esperar($nombre.'.iniciar');
    touch($directorio.'/'.$nombre.'.ejecutando');
    if ($modo === 'pausa') {
        $resultado = app(IniciarPausaPdvService::class)->iniciarParaUsuario((int) $usuarioId, (int) $sucursalId, now(), (int) $usuarioId);
        $detalle = ['intervalo' => $resultado['intervalo']->id];
    } else {
        $detalle = ['asignaciones' => app(MatchmakerTurnosPdvService::class)->ejecutar((int) $sucursalId, 'prueba.a34')];
    }
    $salida = ['ok' => true, 'pid' => getmypid(), 'conexion' => $conexion] + $detalle;
} catch (Throwable $e) {
    $salida = ['ok' => false, 'pid' => getmypid(), 'conexion' => $conexion ?? null,
        'clase' => $e::class, 'codigo_mysql' => $e->errorInfo[1] ?? null, 'mensaje' => $e->getMessage()];
}
file_put_contents($directorio.'/'.$nombre.'.resultado', json_encode($salida, JSON_THROW_ON_ERROR));
echo json_encode($salida, JSON_THROW_ON_ERROR).PHP_EOL;
