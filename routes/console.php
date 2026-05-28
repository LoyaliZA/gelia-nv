<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Configuración para ejecución diaria fija a las 09:00 AM
Schedule::command('pagos:rechazar-vencidos')->dailyAt('09:00');

Schedule::command('activos:alertas-programadas')->dailyAt('08:00');

