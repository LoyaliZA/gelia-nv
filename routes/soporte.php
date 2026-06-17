<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Soporte\SoporteTicketController;
use App\Http\Controllers\Soporte\SoporteAgenteController;
use App\Http\Controllers\Soporte\SoporteConfiguracionController;
use App\Http\Controllers\Soporte\SoporteBaseConocimientoController;

Route::middleware(['auth', 'verified'])->prefix('soporte')->name('soporte.')->group(function () {

    // Base de conocimientos (sugerencias para todos)
    Route::get('/base-conocimiento/sugerencias', [SoporteBaseConocimientoController::class, 'sugerencias'])->name('base_conocimiento.sugerencias');

    // Portal de Usuario Común (Reportador) - Sin permiso especial requerido
    Route::group([], function () {
        Route::get('/mis-tickets', [SoporteTicketController::class, 'index'])->name('tickets.index');
        Route::post('/mis-tickets', [SoporteTicketController::class, 'store'])->name('tickets.store');
        Route::get('/mis-tickets/{ticket}', [SoporteTicketController::class, 'show'])->name('tickets.show');
        Route::post('/mis-tickets/{ticket}/reply', [SoporteTicketController::class, 'reply'])->name('tickets.reply');
        Route::get('/qa', [SoporteBaseConocimientoController::class, 'index'])->name('qa.index');
    });

    // Portal del Agente (Manejo de todos los tickets)
    Route::middleware(['permission:soporte.gestionar'])->prefix('agente')->name('agente.')->group(function () {
        Route::get('/tickets', [SoporteAgenteController::class, 'index'])->name('tickets.index');
        Route::get('/tickets/{ticket}', [SoporteAgenteController::class, 'show'])->name('tickets.show');
        Route::post('/tickets/{ticket}/reply', [SoporteAgenteController::class, 'reply'])->name('tickets.reply');
        Route::post('/tickets/{ticket}/status', [SoporteAgenteController::class, 'updateStatus'])->name('tickets.status');
        Route::post('/tickets/{ticket}/priority', [SoporteAgenteController::class, 'updatePriority'])->name('tickets.priority');
        Route::post('/tickets/{ticket}/assign', [SoporteAgenteController::class, 'assignAgent'])->name('tickets.assign');
    });

    // Administración del Sistema de Soporte
    Route::middleware(['permission:soporte.administrar'])->prefix('admin')->name('admin.')->group(function () {
        Route::get('/configuracion', [SoporteConfiguracionController::class, 'index'])->name('config.index');
        Route::post('/configuracion', [SoporteConfiguracionController::class, 'update'])->name('config.update');
        
        Route::post('/configuracion/catalogos/{tipo}', [SoporteConfiguracionController::class, 'storeCatalogo'])->name('config.catalogos.store');
        Route::put('/configuracion/catalogos/{tipo}/{id}', [SoporteConfiguracionController::class, 'updateCatalogo'])->name('config.catalogos.update');
    });
});
