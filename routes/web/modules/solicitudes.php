<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Solicitudes\SolicitudController;

Route::prefix('solicitudes')->name('solicitudes.')->group(function () {
    Route::middleware(['can:solicitudes.ver_listado'])->group(function () {
        Route::get('/', [SolicitudController::class, 'index'])->name('index');
    });

    Route::middleware(['can:solicitudes.exportar'])->group(function () {
        Route::get('/exportar', [SolicitudController::class, 'exportar'])->name('exportar');
    });

    Route::middleware(['can:solicitudes.crear'])->group(function () {
        Route::post('/', [SolicitudController::class, 'store'])->name('store');
    });

    Route::put('/{solicitud}/confirmar-pago', [SolicitudController::class, 'confirmarPago'])
        ->middleware('can:solicitudes.confirmar_pago')
        ->name('confirmar_pago');

    Route::put('/{solicitud}', [SolicitudController::class, 'update'])->name('update');
    Route::put('/{solicitud}/rechazar-pago', [SolicitudController::class, 'rechazarPago'])->name('rechazar_pago');
    Route::put('/{solicitud}/estado', [SolicitudController::class, 'actualizarEstado'])->name('actualizar_estado');
    Route::put('/{solicitud}/confirmar-lista', [SolicitudController::class, 'confirmarCambioLista'])
        ->middleware('can:solicitudes.confirmar_cambio_lista')
        ->name('confirmar_lista');
    Route::put('/{solicitud}/confirmar-rollback', [SolicitudController::class, 'confirmarRollback'])->name('confirmar_rollback');
    Route::post('/{solicitud}/solicitar-cancelacion', [SolicitudController::class, 'solicitarCancelacion'])
        ->middleware('can:solicitudes.solicitar_cancelacion')
        ->name('solicitar_cancelacion');
    Route::put('/{solicitud}/cancelar', [SolicitudController::class, 'cancelar'])
        ->middleware('can:solicitudes.cancelar')
        ->name('cancelar');
    Route::post('/{solicitud}/consultas', [SolicitudController::class, 'storeConsulta'])
        ->name('consultas.store');
    Route::put('/{solicitud}/consultas/{consulta}', [SolicitudController::class, 'responderConsulta'])->name('consultas.responder');
    Route::put('/{solicitud}/consultas/{consulta}/leer', [SolicitudController::class, 'marcarConsultaLeida'])->name('consultas.leer');
    Route::delete('/{solicitud}', [SolicitudController::class, 'destroy'])->name('destroy');
    Route::put('/{id}/restaurar', [SolicitudController::class, 'restaurar'])->name('restaurar');
});
