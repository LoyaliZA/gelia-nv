<?php

use App\Http\Controllers\PuntoVenta\Pantallas\EnlacePantallaSalaPdvController;
use App\Services\PuntoVenta\PuntoVentaModulo;
use Illuminate\Support\Facades\Route;

Route::middleware(['pdv.piso', 'pdv.permiso:'.PuntoVentaModulo::PERMISO_PANTALLA_SALA_ABRIR])
    ->prefix('pantalla-sala')
    ->name('pantalla_sala.')
    ->group(function () {
        Route::get('/', [EnlacePantallaSalaPdvController::class, 'index'])
            ->name('index');
        Route::get('/enlace', [EnlacePantallaSalaPdvController::class, 'estado'])
            ->name('enlace.estado');
        Route::post('/enlace', [EnlacePantallaSalaPdvController::class, 'obtener'])
            ->name('enlace.obtener');
        Route::put('/enlace/activar', [EnlacePantallaSalaPdvController::class, 'activar'])
            ->name('enlace.activar');
        Route::put('/enlace/desactivar', [EnlacePantallaSalaPdvController::class, 'desactivar'])
            ->name('enlace.desactivar');
    });
