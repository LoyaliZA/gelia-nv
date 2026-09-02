<?php

use App\Http\Controllers\PuntoVenta\Resguardos\BandejaResguardoPdvController;
use App\Http\Controllers\PuntoVenta\Resguardos\DetalleResguardoPdvController;
use App\Http\Controllers\PuntoVenta\Resguardos\EntregaResguardoPdvController;
use App\Http\Controllers\PuntoVenta\Resguardos\FormularioEntregaResguardoPdvController;
use App\Http\Controllers\PuntoVenta\Resguardos\FormularioRecepcionFisicaResguardoPdvController;
use App\Http\Controllers\PuntoVenta\Resguardos\RecepcionFisicaResguardoPdvController;
use App\Services\PuntoVenta\PuntoVentaModulo;
use Illuminate\Support\Facades\Route;

Route::middleware(['pdv.piso', 'pdv.permiso:'.PuntoVentaModulo::PERMISO_RESGUARDOS_VER])
    ->prefix('resguardos')
    ->name('resguardos.')
    ->group(function () {
        Route::get('/', [BandejaResguardoPdvController::class, 'index'])->name('index');
        Route::get('/listado', [BandejaResguardoPdvController::class, 'listado'])->name('listado');
        Route::get('/{resguardo}', [DetalleResguardoPdvController::class, 'show'])->name('show');
    });

Route::middleware(['pdv.piso', 'pdv.permiso:'.PuntoVentaModulo::PERMISO_RESGUARDOS_RECIBIR])
    ->prefix('resguardos')
    ->name('resguardos.')
    ->group(function () {
        Route::get('/{resguardo}/recepcion', [FormularioRecepcionFisicaResguardoPdvController::class, 'show'])
            ->name('recepcion.create');
        Route::put('/{resguardo}/recepcion', RecepcionFisicaResguardoPdvController::class)->name('recepcion');
    });

Route::middleware(['pdv.piso', 'pdv.permiso:'.PuntoVentaModulo::PERMISO_RESGUARDOS_ENTREGAR])
    ->prefix('resguardos')
    ->name('resguardos.')
    ->group(function () {
        Route::get('/{resguardo}/entrega', [FormularioEntregaResguardoPdvController::class, 'show'])
            ->name('entrega.create');
        Route::put('/{resguardo}/entrega', EntregaResguardoPdvController::class)->name('entrega');
    });
