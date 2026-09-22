<?php

use App\Http\Controllers\Medios\CargaMedioController;
use Illuminate\Support\Facades\Route;

Route::prefix('medios/cargas')->name('medios.cargas.')->group(function () {
    Route::post('/', [CargaMedioController::class, 'iniciar'])->name('iniciar');
    Route::post('/{carga}/partes', [CargaMedioController::class, 'partes'])->name('partes');
    Route::get('/{carga}/estado', [CargaMedioController::class, 'estado'])->name('estado');
    Route::post('/{carga}/completar', [CargaMedioController::class, 'completar'])->name('completar');
    Route::delete('/{carga}', [CargaMedioController::class, 'cancelar'])->name('cancelar');
});
