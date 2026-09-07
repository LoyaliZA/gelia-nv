<?php

use App\Http\Controllers\PuntoVenta\Reportes\ExportacionReportePdvController;
use App\Http\Controllers\PuntoVenta\Reportes\MetricasReportePdvController;
use App\Services\PuntoVenta\PuntoVentaModulo;
use Illuminate\Support\Facades\Route;

Route::middleware(['pdv.permiso:'.PuntoVentaModulo::PERMISO_REPORTES_EXPORTAR])
    ->prefix('reportes/exportaciones')
    ->name('reportes.exportaciones.')
    ->group(function () {
        Route::post('/', [ExportacionReportePdvController::class, 'store'])->name('store');
        Route::get('/{exportacion}', [ExportacionReportePdvController::class, 'show'])->name('show');
        Route::get('/{exportacion}/descargar', [ExportacionReportePdvController::class, 'descargar'])
            ->name('descargar');
        Route::post('/{exportacion}/reintentar', [ExportacionReportePdvController::class, 'reintentar'])
            ->name('reintentar');
    });

Route::middleware(['pdv.permiso:'.PuntoVentaModulo::PERMISO_ACCEDER])
    ->prefix('reportes')
    ->name('reportes.')
    ->group(function () {
        Route::get('/', [MetricasReportePdvController::class, 'index'])->name('index');

        Route::get('/resguardos', [MetricasReportePdvController::class, 'resguardos'])->name('resguardos');

        Route::get('/turnos-operacion', [MetricasReportePdvController::class, 'turnosOperacion'])
            ->name('turnos_operacion');

        Route::get('/conjunto', [MetricasReportePdvController::class, 'conjunto'])->name('conjunto');
    });
