<?php

use App\Http\Controllers\Admin\PruebasComponentesController;
use Illuminate\Support\Facades\Route;

Route::middleware(['can:sistema.pruebas_componentes.ver'])->prefix('pruebas-componentes')->name('pruebas_componentes.')->group(function () {
    Route::get('/', [PruebasComponentesController::class, 'index'])->name('index');
});
