<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Reportes\ReporteSolicitudesController;
use App\Http\Controllers\Reportes\ReporteTraspasosDiaController;
use App\Http\Controllers\Reportes\ReportePagosPedidosController;
use App\Http\Controllers\Reportes\ReporteVentasController;

Route::middleware(['can:solicitudes.exportar'])->group(function () {
    Route::get('/reportes/solicitudes', [ReporteSolicitudesController::class, 'index'])->name('reportes.solicitudes.index');
    Route::get('/reportes/solicitudes/exportar', [ReporteSolicitudesController::class, 'exportar'])->name('reportes.solicitudes.exportar');
});

Route::middleware(['can:traspasos.reporte_dia'])->prefix('reportes/traspasos-dia')->name('reportes.traspasos_dia.')->group(function () {
    Route::get('/', [ReporteTraspasosDiaController::class, 'index'])->name('index');
    Route::get('/exportar', [ReporteTraspasosDiaController::class, 'exportar'])->name('exportar');
});

Route::middleware(['can:reportes.pagos_pedidos.ver'])
    ->prefix('reportes/pagos-pedidos')
    ->name('reportes.pagos_pedidos.')
    ->group(function () {
        Route::get('/', [ReportePagosPedidosController::class, 'index'])->name('index');
        Route::get('/detalle/{cierre}', [ReportePagosPedidosController::class, 'detalle'])->name('detalle');
        Route::post('/cierres/{cierre}/confirmar-admin', [ReportePagosPedidosController::class, 'confirmarPedidoAdmin'])
            ->middleware('can:reportes.pagos_pedidos.confirmar_admin')
            ->name('confirmar_admin.pedido');
        Route::post('/cierres/{cierre}/items/{item}/confirmar-admin', [ReportePagosPedidosController::class, 'confirmarExhibicionAdmin'])
            ->middleware('can:reportes.pagos_pedidos.confirmar_admin')
            ->name('confirmar_admin.exhibicion');
        Route::post('/cierres/{cierre}/reportar-error-admin', [ReportePagosPedidosController::class, 'reportarErrorPedidoAdmin'])
            ->middleware('can:reportes.pagos_pedidos.reportar_error_admin')
            ->name('reportar_error_admin.pedido');
        Route::post('/cierres/{cierre}/items/{item}/reportar-error-admin', [ReportePagosPedidosController::class, 'reportarErrorExhibicionAdmin'])
            ->middleware('can:reportes.pagos_pedidos.reportar_error_admin')
            ->name('reportar_error_admin.exhibicion');
        Route::get('/evidencias/pagos/{pago}', [ReportePagosPedidosController::class, 'evidenciaPago'])
            ->middleware('can:reportes.pagos_pedidos.ver_evidencias')
            ->name('evidencia_pago');
        Route::get('/documentos/{documento}', [ReportePagosPedidosController::class, 'documento'])
            ->middleware('can:reportes.pagos_pedidos.ver_evidencias')
            ->name('documento');
        Route::post('/exportar/solicitar', [ReportePagosPedidosController::class, 'solicitarExportacion'])
            ->name('exportar.solicitar');
        Route::get('/exportaciones', [ReportePagosPedidosController::class, 'listarExportaciones'])
            ->name('exportaciones.index');
        Route::post('/exportaciones/{exportacion}/reintentar', [ReportePagosPedidosController::class, 'reintentarExportacion'])
            ->name('exportaciones.reintentar');
        Route::post('/exportar/estimacion', [ReportePagosPedidosController::class, 'estimarExportacion'])
            ->name('exportar.estimacion');
        Route::get('/exportar/csv-resumen', [ReportePagosPedidosController::class, 'csvResumen'])
            ->middleware('can:reportes.pagos_pedidos.exportar_csv')
            ->name('csv_resumen');
        Route::get('/exportar/csv-detalle', [ReportePagosPedidosController::class, 'csvDetalle'])
            ->middleware('can:reportes.pagos_pedidos.exportar_csv')
            ->name('csv_detalle');
        Route::post('/exportar/pdf', [ReportePagosPedidosController::class, 'solicitarPdf'])
            ->middleware('can:reportes.pagos_pedidos.exportar_pdf')
            ->name('pdf.solicitar');
        Route::get('/exportar/pdf/{exportacion}/estado', [ReportePagosPedidosController::class, 'estadoPdf'])
            ->middleware('can:reportes.pagos_pedidos.exportar_pdf')
            ->name('pdf.estado');
        Route::post('/exportar/pdf/{exportacion}/cancelar', [ReportePagosPedidosController::class, 'cancelarPdf'])
            ->middleware('can:reportes.pagos_pedidos.exportar_pdf')
            ->name('pdf.cancelar');
        Route::get('/exportar/{exportacion}/descargar', [ReportePagosPedidosController::class, 'descargarPdf'])
            ->name('exportar.descargar');
        Route::get('/exportar/pdf/{exportacion}', [ReportePagosPedidosController::class, 'descargarPdf'])
            ->middleware('can:reportes.pagos_pedidos.exportar_pdf')
            ->name('pdf.descargar');
    });

Route::middleware(['role_or_permission:reportes.ventas.ver'])->prefix('reportes/ventas')->name('reportes.ventas.')->group(function () {
    Route::get('/', [ReporteVentasController::class, 'index'])->name('index');
    Route::get('/plantilla-importacion', [ReporteVentasController::class, 'descargarPlantilla'])->middleware('can:reportes.ventas.importar')->name('plantilla_importacion');
    Route::post('/import-preview', [ReporteVentasController::class, 'importPreview'])->middleware('can:reportes.ventas.importar')->name('import_preview');
    Route::post('/import-iniciar', [ReporteVentasController::class, 'importIniciar'])->middleware('can:reportes.ventas.importar')->name('import_iniciar');
});
