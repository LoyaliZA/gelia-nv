<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Configuración para ejecución diaria fija a las 09:00 AM
Schedule::command('pagos:rechazar-vencidos')->dailyAt('09:00');

Schedule::command('activos:alertas-programadas')->dailyAt('08:00');

Schedule::command('rh:generar-cuotas-prestamos')->dailyAt('07:00');

Schedule::command('activos:notificar-pendientes-firma')->dailyAt('09:00');
Schedule::command('activos:notificar-pendientes-firma')->dailyAt('12:00');

$horariosCobranza = ['10:00', '12:00'];
try {
    if (Schema::hasTable('cobranza_configuraciones')) {
        $configuracionHorarios = \Illuminate\Support\Facades\Cache::rememberForever('cobranza_horarios', function () {
            $config = \Illuminate\Support\Facades\DB::table('cobranza_configuraciones')->where('llave', 'horarios_alertas')->first();
            return $config ? json_decode($config->valor, true) : ['10:00', '12:00'];
        });
        if (is_array($configuracionHorarios)) {
            $horariosCobranza = $configuracionHorarios;
        }
    }
} catch (\Exception $e) {
    // Si hay error (ej. migración no corrida), usar default
}

foreach ($horariosCobranza as $hora) {
    Schedule::command('cobranza:evaluar-alertas')->dailyAt($hora);
}

Schedule::command('sesiones:cerrar-jornada')->everyFiveMinutes();
Schedule::command('sesiones:sincronizar-expiradas')->everyFifteenMinutes();
