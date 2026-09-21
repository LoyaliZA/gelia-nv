<?php

use App\Http\Controllers\PuntoVenta\Pantallas\EnlacePantallaSalaPdvController;
use App\Http\Controllers\PuntoVenta\Pantallas\PublicidadPantallaSalaPdvController;
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
        Route::get('/publicidad', [PublicidadPantallaSalaPdvController::class, 'index'])
            ->name('publicidad.index');
        Route::post('/publicidad', [PublicidadPantallaSalaPdvController::class, 'store'])
            ->name('publicidad.store');
        Route::post('/publicidad/{publicidad}', [PublicidadPantallaSalaPdvController::class, 'update'])
            ->name('publicidad.update');
        Route::delete('/publicidad/{publicidad}', [PublicidadPantallaSalaPdvController::class, 'destroy'])
            ->name('publicidad.destroy');
    });
