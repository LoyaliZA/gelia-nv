<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Mensajeria\AdjuntoMensajeController;
use App\Http\Controllers\Mensajeria\BuscarMensajeriaController;
use App\Http\Controllers\Mensajeria\ConversacionController;
use App\Http\Controllers\Mensajeria\MensajeController;
use App\Http\Controllers\Mensajeria\PresenciaController;

Route::prefix('mensajeria')->name('mensajeria.')->group(function () {
    Route::get('/', [ConversacionController::class, 'index'])->name('index');
    Route::get('/conversaciones', [ConversacionController::class, 'list'])->name('conversaciones.list');
    Route::post('/conversaciones', [ConversacionController::class, 'store'])->name('conversaciones.store');
    Route::get('/usuarios', [ConversacionController::class, 'usuarios'])->name('usuarios');
    Route::get('/conversaciones/{conversacion}/mensajes', [MensajeController::class, 'index'])->name('mensajes.index');
    Route::post('/conversaciones/{conversacion}/mensajes', [MensajeController::class, 'store'])->name('mensajes.store');
    Route::put('/conversaciones/{conversacion}/leer', [MensajeController::class, 'marcarLeida'])->name('conversaciones.leer');
    Route::get('/conversaciones/{conversacion}/medios', [ConversacionController::class, 'medios'])->name('conversaciones.medios');
    Route::get('/presencia/catalogo', [PresenciaController::class, 'catalogo'])->name('presencia.catalogo');
    Route::get('/presencia', [PresenciaController::class, 'show'])->name('presencia.show');
    Route::put('/presencia', [PresenciaController::class, 'update'])->name('presencia.update');
    Route::post('/presencia/heartbeat', [PresenciaController::class, 'heartbeat'])->name('presencia.heartbeat');
    Route::get('/buscar', [BuscarMensajeriaController::class, 'buscar'])->name('buscar');
    Route::get('/conversaciones/{conversacion}/contexto', [BuscarMensajeriaController::class, 'contexto'])->name('conversaciones.contexto');
    Route::post('/conversaciones/{conversacion}/adjuntos', [AdjuntoMensajeController::class, 'store'])->name('adjuntos.store');
    Route::get('/adjuntos/{adjunto}', [AdjuntoMensajeController::class, 'show'])->name('adjuntos.show');
});
