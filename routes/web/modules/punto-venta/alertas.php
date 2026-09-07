<?php

use App\Http\Controllers\PuntoVenta\Alertas\ActualizarPreferenciasAlertasPdvController;
use Illuminate\Support\Facades\Route;

Route::middleware(['pdv.modulo'])
    ->put('/preferencias-alertas', ActualizarPreferenciasAlertasPdvController::class)
    ->name('preferencias_alertas');
