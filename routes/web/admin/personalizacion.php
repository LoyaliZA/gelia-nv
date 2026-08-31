<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\PersonalizacionController;

Route::middleware(['can:personalizacion.gestionar'])->prefix('personalizacion')->name('personalizacion.')->group(function () {
    Route::get('/', [PersonalizacionController::class, 'index'])->name('index');

    Route::post('/tonos', [PersonalizacionController::class, 'storeTono'])->name('tonos.store');
    Route::post('/tonos/{id}', [PersonalizacionController::class, 'updateTono'])->name('tonos.update');
    Route::delete('/tonos/{id}', [PersonalizacionController::class, 'destroyTono'])->name('tonos.destroy');

    Route::post('/fondos', [PersonalizacionController::class, 'storeFondo'])->name('fondos.store');
    Route::post('/fondos/{id}', [PersonalizacionController::class, 'updateFondo'])->name('fondos.update');
    Route::delete('/fondos/{id}', [PersonalizacionController::class, 'destroyFondo'])->name('fondos.destroy');

    Route::post('/temas', [PersonalizacionController::class, 'storeTema'])->name('temas.store');
    Route::put('/temas/{id}', [PersonalizacionController::class, 'updateTema'])->name('temas.update');
    Route::delete('/temas/{id}', [PersonalizacionController::class, 'destroyTema'])->name('temas.destroy');
});
