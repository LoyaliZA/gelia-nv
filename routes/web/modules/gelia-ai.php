<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GeliaAi\GeliaAiController;

Route::prefix('gelia-ai')->name('gelia_ai.')->group(function () {
    Route::get('/', [GeliaAiController::class, 'index'])->name('index');
    Route::post('/chat', [GeliaAiController::class, 'chat'])->middleware('throttle:30,1')->name('chat');
    Route::post('/archivos', [GeliaAiController::class, 'subirArchivos'])->middleware('throttle:20,1')->name('archivos.store');
    Route::post('/acciones/ejecutar', [GeliaAiController::class, 'ejecutarAccion'])->middleware('throttle:20,1')->name('acciones.ejecutar');
    Route::get('/conversaciones', [GeliaAiController::class, 'conversaciones'])->name('conversaciones.index');
    Route::post('/conversaciones', [GeliaAiController::class, 'storeConversacion'])->name('conversaciones.store');
    Route::get('/conversaciones/{conversacion}', [GeliaAiController::class, 'showConversacion'])->name('conversaciones.show');
    Route::delete('/conversaciones/{conversacion}', [GeliaAiController::class, 'destroyConversacion'])->name('conversaciones.destroy');
});
