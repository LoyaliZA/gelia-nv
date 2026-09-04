<?php

use App\Http\Controllers\PuntoVenta\Turnos\AltaTurnoPdvController;
use App\Http\Controllers\PuntoVenta\Turnos\BajaColaTurnoPdvController;
use App\Http\Controllers\PuntoVenta\Turnos\CerrarAtencionTurnoPdvController;
use App\Http\Controllers\PuntoVenta\Turnos\FormularioRecepcionTurnoPdvController;
use App\Http\Controllers\PuntoVenta\Turnos\IniciarAtencionTurnoPdvController;
use App\Http\Controllers\PuntoVenta\Turnos\TableroVentasPdvController;
use App\Http\Controllers\PuntoVenta\Turnos\TransferirTurnoPdvController;
use App\Services\PuntoVenta\PuntoVentaModulo;
use Illuminate\Support\Facades\Route;

Route::middleware(['pdv.piso', 'pdv.permiso:'.PuntoVentaModulo::PERMISO_TURNOS_VER])
    ->prefix('turnos')
    ->name('turnos.')
    ->group(function () {
        Route::get('/ventas', [TableroVentasPdvController::class, 'index'])->name('ventas');
        Route::get('/ventas/datos', [TableroVentasPdvController::class, 'datos'])->name('ventas.datos');
    });

Route::middleware(['pdv.piso', 'pdv.permiso:'.PuntoVentaModulo::PERMISO_TURNOS_VER])
    ->prefix('turnos')
    ->name('turnos.')
    ->group(function () {
        Route::get('/recepcion', [FormularioRecepcionTurnoPdvController::class, 'show'])->name('recepcion');
        Route::get('/recepcion/datos', [FormularioRecepcionTurnoPdvController::class, 'datos'])->name('recepcion.datos');
    });

Route::middleware(['pdv.piso', 'pdv.permiso:'.PuntoVentaModulo::PERMISO_TURNOS_ALTA])
    ->prefix('turnos')
    ->name('turnos.')
    ->group(function () {
        Route::post('/', AltaTurnoPdvController::class)->name('store');
    });

Route::middleware(['pdv.piso'])
    ->prefix('turnos')
    ->name('turnos.')
    ->group(function () {
        Route::post('/{turno}/iniciar-atencion', IniciarAtencionTurnoPdvController::class)
            ->middleware('pdv.permiso:'.PuntoVentaModulo::PERMISO_TURNOS_CERRAR_ATENCION)
            ->name('iniciar_atencion');

        Route::post('/{turno}/cerrar-atencion', CerrarAtencionTurnoPdvController::class)
            ->middleware('pdv.permiso:'.PuntoVentaModulo::PERMISO_TURNOS_CERRAR_ATENCION)
            ->name('cerrar_atencion');

        Route::post('/{turno}/baja-cola', BajaColaTurnoPdvController::class)
            ->middleware('pdv.permiso:'.PuntoVentaModulo::PERMISO_TURNOS_BAJA_COLA)
            ->name('baja_cola');

        Route::post('/{turno}/transferir', TransferirTurnoPdvController::class)
            ->middleware('pdv.permiso:'.PuntoVentaModulo::PERMISO_TURNOS_TRANSFERIR)
            ->name('transferir');
    });
