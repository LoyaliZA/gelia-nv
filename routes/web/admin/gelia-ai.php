<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\{GeliaAiAccesoController, GeliaAiUsoController};

Route::middleware(['can:gelia_ai.gestionar_acceso'])->prefix('gelia-ai')->name('gelia_ai.')->group(function () {
    Route::get('/acceso', [GeliaAiAccesoController::class, 'index'])->name('acceso.index');
    Route::put('/acceso', [GeliaAiAccesoController::class, 'update'])->name('acceso.update');
    Route::get('/usuarios', [GeliaAiAccesoController::class, 'buscarUsuarios'])->name('usuarios');
    Route::get('/uso', [GeliaAiUsoController::class, 'index'])->name('uso.index');
    Route::get('/uso/turnos', [GeliaAiUsoController::class, 'turnos'])->name('uso.turnos');
    Route::get('/uso/conversaciones/{conversacion}', [GeliaAiUsoController::class, 'conversacion'])->name('uso.conversacion');
});
