<?php

use App\Http\Controllers\PuntoVenta\ConfigurarAlcancePdvController;
use App\Http\Controllers\PuntoVenta\EstablecerSucursalActivaPdvController;
use Illuminate\Support\Facades\Route;

Route::get('/configurar-sucursal', ConfigurarAlcancePdvController::class)
    ->name('alcance.configurar');

Route::put('/sucursal-activa', EstablecerSucursalActivaPdvController::class)
    ->name('sucursal_activa.establecer');
