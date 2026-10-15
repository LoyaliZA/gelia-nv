<?php

// Proceso independiente de pruebas; no es un comando disponible en producción.
require dirname(__DIR__, 3).'/vendor/autoload.php';

use App\Events\PuntoVenta\JornadaAbierta;
use App\Models\PuntoVenta\MedicionEventoPdv;
use App\Services\PuntoVenta\Operacion\AbrirJornadaPdvService;
use App\Services\PuntoVenta\Operacion\RegistrarTransicionMedicionPdvService;
use Carbon\Carbon;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\Support\PuntoVenta\MySqlMedicionA331;

[$script, $directorio, $nombre, $userId, $sucursalId, $modo, $estado] = $argv;
$marcar = fn (string $marca) => touch($directorio.'/'.$nombre.'.'.$marca);
$esperar = function (string $marca) use ($directorio): void {
    $limite = microtime(true) + 20;
    while (! is_file($directorio.'/'.$marca)) {
        if (microtime(true) > $limite) {
            throw new RuntimeException('Barrera vencida: '.$marca);
        }
        usleep(10000);
        clearstatcache();
    }
};
try {
    $app = MySqlMedicionA331::application();
    config(['punto_venta.medicion.estado' => $estado, 'punto_venta.medicion.sucursales' => [(int) $sucursalId]]);
    Event::fake([JornadaAbierta::class]);
    DB::statement('SET SESSION innodb_lock_wait_timeout = '.($modo === 'timeout' ? '1' : '8'));
    $conexion = DB::selectOne('SELECT CONNECTION_ID() AS id')->id;
    DB::connection()->beforeExecuting(function ($sql) use ($directorio, $nombre) {
        file_put_contents($directorio.'/'.$nombre.'.sql', $sql);
    });
    $detenido = false;
    DB::listen(function (QueryExecuted $q) use ($modo, &$detenido, $marcar, $esperar, $nombre) {
        if ($detenido) {
            return;
        }
        if ($modo === 'carrera_sin_medicion' && str_starts_with($q->sql, 'select') && str_contains($q->sql, 'from `pdv_jornadas`')) {
            $detenido = true;
            $marcar('retenido');
            $esperar('carrera.liberar');
        }
        $usuario = str_contains($q->sql, 'from `users`') && str_contains($q->sql, 'for update');
        $evento = str_starts_with($q->sql, 'insert into `pdv_medicion_eventos`');
        $intervalo = str_starts_with($q->sql, 'insert into `pdv_intervalos_operativos`');
        if (($modo === 'retener_usuario' && $usuario) || ($modo === 'retener_evento' && $evento) || ($modo === 'retener_intervalo' && $intervalo)) {
            $detenido = true;
            $marcar('retenido');
            $esperar($nombre.'.liberar');
        }
    });
    if ($modo === 'fallo_evento') {
        $app->instance(RegistrarTransicionMedicionPdvService::class, new class($marcar, $esperar, $nombre) extends RegistrarTransicionMedicionPdvService
        {
            public function __construct(private $marcar, private $esperar, private string $nombre) {}

            public function ejecutar(array $datos): MedicionEventoPdv
            {
                parent::ejecutar($datos);
                ($this->marcar)('retenido');
                ($this->esperar)($this->nombre.'.liberar');
                throw new RuntimeException('Fallo instrumentado después del INSERT');
            }
        });
    }
    if ($modo === 'snapshot') {
        DB::beginTransaction();
        MedicionEventoPdv::query()->where('user_id', (int) $userId)->count();
    }
    $marcar('listo');
    $esperar($nombre.'.iniciar');
    $inicio = microtime(true);
    $marcar('ejecutando');
    $r = app(AbrirJornadaPdvService::class)->abrirParaUsuario(
        (int) $userId, (int) $sucursalId, Carbon::parse('2026-10-14 10:00:00', 'America/Mexico_City'), (int) $userId,
    );
    if ($modo === 'snapshot') {
        DB::commit();
    }
    $salida = ['ok' => true, 'pid' => getmypid(), 'conexion' => $conexion, 'jornada' => $r['jornada']->id,
        'intervalo' => $r['intervalo']->id, 'reintento' => $r['reintento'], 'duracion' => microtime(true) - $inicio];
} catch (Throwable $e) {
    while (isset($app) && DB::transactionLevel() > 0) {
        DB::rollBack();
    }
    $salida = ['ok' => false, 'pid' => getmypid(), 'conexion' => $conexion ?? null,
        'clase' => $e::class, 'codigo_mysql' => $e->errorInfo[1] ?? null, 'mensaje' => $e->getMessage()];
}
file_put_contents($directorio.'/'.$nombre.'.resultado', json_encode($salida, JSON_THROW_ON_ERROR));
echo json_encode($salida, JSON_THROW_ON_ERROR).PHP_EOL;
