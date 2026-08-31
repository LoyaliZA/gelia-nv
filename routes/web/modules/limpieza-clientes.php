<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LimpiezaClientesController;

Route::middleware(['can:funciones.limpieza_clientes'])->group(function () {
    Route::get('/funciones/limpieza-clientes', [LimpiezaClientesController::class, 'index'])->name('funciones.limpieza-clientes.index');
    Route::post('/funciones/limpieza-clientes/procesar', [LimpiezaClientesController::class, 'procesar'])->name('funciones.limpieza-clientes.procesar');
});
