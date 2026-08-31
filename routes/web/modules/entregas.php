<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EntregasController;

Route::middleware(['can:entregas.cotizar'])->group(function () {
    Route::get('/entregas/cotizador', [EntregasController::class, 'index'])->name('entregas.index');
    Route::put('/entregas/configuracion', [EntregasController::class, 'actualizarConfiguracion'])->name('entregas.configuracion.update')->middleware('can:entregas.configurar_zonas');
    Route::post('/entregas/zonas', [EntregasController::class, 'storeZona'])->name('entregas.zonas.store')->middleware('can:entregas.configurar_zonas');
});
