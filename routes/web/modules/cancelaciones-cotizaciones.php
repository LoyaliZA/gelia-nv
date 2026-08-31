<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CancelacionesCotizaciones\SolicitudOperativaController;

Route::prefix('cancelaciones-cotizaciones')->name('cancelaciones_cotizaciones.')->group(function () {
    Route::middleware(['can:cancelaciones_cotizaciones.ver_listado'])->group(function () {
        Route::get('/', [SolicitudOperativaController::class, 'index'])->name('index');
        Route::get('/exportar', [SolicitudOperativaController::class, 'exportar'])
            ->middleware('can:cancelaciones_cotizaciones.exportar')
            ->name('exportar');
    });

    Route::middleware(['can:cancelaciones_cotizaciones.crear'])
        ->post('/', [SolicitudOperativaController::class, 'store'])
        ->name('store');

    Route::put('/{solicitud}/estado', [SolicitudOperativaController::class, 'actualizarEstado'])
        ->name('actualizar_estado');

    Route::middleware(['can:cancelaciones_cotizaciones.solicitar_cancelacion'])
        ->post('/{solicitud}/solicitar-cancelacion', [SolicitudOperativaController::class, 'solicitarCancelacion'])
        ->name('solicitar_cancelacion');

    Route::middleware(['can:cancelaciones_cotizaciones.cancelar'])
        ->put('/{solicitud}/cancelar', [SolicitudOperativaController::class, 'cancelar'])
        ->name('cancelar');

    Route::middleware(['can:cancelaciones_cotizaciones.eliminar'])
        ->delete('/{solicitud}', [SolicitudOperativaController::class, 'destroy'])
        ->name('destroy');
});
