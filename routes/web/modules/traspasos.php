<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Traspasos\SolicitudTraspasoController;
use App\Http\Controllers\Traspasos\TraspasoCedisController;

Route::prefix('traspasos')->name('traspasos.')->group(function () {
    Route::middleware(['can:traspasos.crear'])
        ->post('/', [SolicitudTraspasoController::class, 'store'])
        ->name('store');

    Route::middleware(['can:traspasos.cedis'])
        ->prefix('cedis')
        ->name('cedis.')
        ->group(function () {
            Route::get('/', [TraspasoCedisController::class, 'index'])->name('index');
            Route::post('/{traspaso}/confirmar', [TraspasoCedisController::class, 'confirmar'])->name('confirmar');
        });

    Route::get('/revisiones/{revision}/{indice}', [TraspasoCedisController::class, 'fotoRevision'])
        ->whereNumber('indice')
        ->name('revision_foto');

    Route::middleware(['can:traspasos.ver_listado'])->group(function () {
        Route::get('/', [SolicitudTraspasoController::class, 'index'])->name('index');
        Route::get('/{traspaso}', [SolicitudTraspasoController::class, 'show'])->name('show');
    });

    Route::get('/{traspaso}/evidencia', [SolicitudTraspasoController::class, 'evidencia'])
        ->name('evidencia');

    Route::put('/{traspaso}/estado', [SolicitudTraspasoController::class, 'actualizarEstado'])->name('actualizar_estado');

    Route::middleware(['can:traspasos.verificar'])
        ->put('/{traspaso}/verificar', [SolicitudTraspasoController::class, 'verificar'])
        ->name('verificar');

    Route::middleware(['can:traspasos.eliminar'])
        ->delete('/{traspaso}', [SolicitudTraspasoController::class, 'destroy'])
        ->name('destroy');
});
