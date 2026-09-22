<?php

use App\Http\Controllers\PuntoVenta\Publicidad\PublicidadPdvController;
use App\Services\PuntoVenta\PuntoVentaModulo;
use Illuminate\Support\Facades\Route;

Route::middleware(['pdv.piso'])
    ->prefix('publicidad')
    ->name('publicidad.')
    ->group(function () {
        Route::middleware(['pdv.permiso:'.PuntoVentaModulo::PERMISO_PUBLICIDAD_VER])
            ->group(function () {
                Route::get('/', [PublicidadPdvController::class, 'index'])->name('index');
                Route::get('/items', [PublicidadPdvController::class, 'listar'])->name('items');
            });

        Route::post('/', [PublicidadPdvController::class, 'store'])
            ->middleware('pdv.permiso:'.PuntoVentaModulo::PERMISO_PUBLICIDAD_CREAR)
            ->name('store');
        Route::patch('/orden', [PublicidadPdvController::class, 'ordenar'])
            ->middleware('pdv.permiso:'.PuntoVentaModulo::PERMISO_PUBLICIDAD_ORDENAR)
            ->name('ordenar');
        Route::patch('/{publicidad}', [PublicidadPdvController::class, 'update'])
            ->middleware('pdv.permiso:'.PuntoVentaModulo::PERMISO_PUBLICIDAD_EDITAR)
            ->name('update');
        Route::delete('/{publicidad}', [PublicidadPdvController::class, 'destroy'])
            ->middleware('pdv.permiso:'.PuntoVentaModulo::PERMISO_PUBLICIDAD_ELIMINAR)
            ->name('destroy');
    });
