<?php
require 'vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\Rh\CalcularHorasExtraService;
use App\Models\RhColaborador;

$service = new CalcularHorasExtraService();
$colab = new RhColaborador(['horas_laboradas_oficiales' => 8]);
$turno = new App\Models\CatalogoTurno([
    'matriz_horario' => [
        'sabado' => ['entrada' => '08:00', 'salida' => '13:00', 'descanso' => false]
    ]
]);
$colab->setRelation('turno', $turno);

$datos = [
    'fecha_turno' => '2026-06-27', // Saturday
    'hora_entrada' => '08:00',
    'hora_salida' => '15:00', // 2 hours extra! (13:00 to 15:00)
    'horas_normales_snapshot' => 8,
];
$config = App\Models\RhConfiguracion::first() ?: new App\Models\RhConfiguracion(['he_gracia_minutos_despues_salida' => 30, 'he_minutos_minimos' => 30]);

$res = $service->ejecutar($datos, $config, $colab);
print_r($res);
