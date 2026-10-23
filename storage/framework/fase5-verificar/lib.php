<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

function fase5_app(): Illuminate\Foundation\Application
{
    require '/var/www/html/vendor/autoload.php';
    $app = require '/var/www/html/bootstrap/app.php';
    $app->booting(function () use ($app) {
        $app['config']->set('app.env', 'testing');
        $app['config']->set('cache.default', 'array');
        $app['config']->set('queue.default', 'sync');
        $app['config']->set('session.driver', 'array');
        $app['config']->set('broadcasting.default', 'null');
        $app['config']->set('mail.default', 'array');
        $app['config']->set('database.default', 'mysql');
        $app['config']->set('database.connections.mysql.host', 'fase5-mysql-conc');
        $app['config']->set('database.connections.mysql.port', '3306');
        $app['config']->set('database.connections.mysql.database', 'fase5_sintetica');
        $app['config']->set('database.connections.mysql.username', 'root');
        $app['config']->set('database.connections.mysql.password', 'fase5-temp-only');
        $app['config']->set('filesystems.disks.fase5img', [
            'driver' => 'local',
            'root' => '/tmp/fase5-imagenes',
            'throw' => false,
            'report' => false,
        ]);
        $app['config']->set('productos_imagenes.disk', 'fase5img');
        $app['config']->set('hashing.bcrypt.rounds', 4);
    });
    $app->make(Kernel::class)->bootstrap();

    DB::purge('mysql');
    $nombre = DB::connection('mysql')->getDatabaseName();
    $token = null;
    try {
        $token = DB::table('fase5_marcador')->value('token');
    } catch (Throwable $e) {
        fwrite(STDERR, "ABORT marcador no disponible: ".$e->getMessage()."\n");
        exit(2);
    }
    if ($nombre !== 'fase5_sintetica' || $token !== 'fase5-aislada') {
        fwrite(STDERR, "ABORT base no aislada: {$nombre} token={$token}\n");
        exit(2);
    }

    return $app;
}

function fase5_png(string $ruta, int $lado = 48): void
{
    $im = imagecreatetruecolor($lado, $lado);
    $color = imagecolorallocate($im, 20, 90, 140);
    imagefilledrectangle($im, 0, 0, $lado - 1, $lado - 1, $color);
    imagepng($im, $ruta);
    imagedestroy($im);
}

function fase5_upload(string $ruta, string $nombre): Illuminate\Http\UploadedFile
{
    return new Illuminate\Http\UploadedFile($ruta, $nombre, 'image/png', null, true);
}

function fase5_producto(int $folio): App\Models\Producto
{
    return App\Models\Producto::query()->create([
        'uuid' => (string) Illuminate\Support\Str::uuid(),
        'folio' => $folio,
        'sku' => 'F5-'.$folio,
        'descripcion' => 'Sintetico fase 5 '.$folio,
        'activo' => true,
    ]);
}

function fase5_fila_producto(int $id): array
{
    $fila = (array) DB::selectOne('select * from productos where id = ?', [$id]);
    ksort($fila);
    $normalizada = [];
    foreach ($fila as $columna => $valor) {
        $normalizada[$columna] = $valor === null ? null : (string) $valor;
    }

    return $normalizada;
}
