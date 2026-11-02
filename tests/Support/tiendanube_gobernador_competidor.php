<?php

use App\Services\Tiendanube\Gobernador\TiendanubeGobernador;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$prefijo = (string) ($argv[1] ?? '');
$store = (int) ($argv[2] ?? 0);
$intervalo = (int) ($argv[3] ?? 60000);
$operacion = (string) ($argv[4] ?? 'carrera');

if ($prefijo === '' || $store < 1) {
    fwrite(STDERR, "faltan prefijo o tienda\n");
    exit(1);
}

$gobernador = TiendanubeGobernador::conectarPrueba($prefijo);
$turno = $gobernador->adquirir($store, 'publicacion', 'intento', $operacion, 'sin_escritura', $intervalo);

fwrite(STDOUT, json_encode(['concedido' => $turno->concedido], JSON_THROW_ON_ERROR)."\n");
