<?php

use App\Http\Controllers\PuntoVenta\Alertas\ActualizarPreferenciasAlertasPdvController;
use App\Http\Controllers\PuntoVenta\Alertas\TerminalAlertasSucursalPdvController;
use Illuminate\Support\Facades\Route;

Route::middleware(['pdv.modulo'])
    ->put('/preferencias-alertas', ActualizarPreferenciasAlertasPdvController::class)
    ->name('preferencias_alertas');

Route::middleware(['pdv.modulo'])
    ->prefix('terminal-alertas')
    ->name('terminal_alertas.')
    ->group(function () {
        Route::get('/estado', [TerminalAlertasSucursalPdvController::class, 'estado'])
            ->name('estado');
        Route::post('/activar', [TerminalAlertasSucursalPdvController::class, 'activar'])
            ->name('activar');
        Route::put('/latido', [TerminalAlertasSucursalPdvController::class, 'latido'])
            ->name('latido');
        Route::delete('/liberar', [TerminalAlertasSucursalPdvController::class, 'liberar'])
            ->name('liberar');
    });
