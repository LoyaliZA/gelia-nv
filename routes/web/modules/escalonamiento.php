<?php

use App\Http\Controllers\Escalonamiento\EscalonamientoController;
use Illuminate\Support\Facades\Route;

Route::prefix('escalonamiento')->name('escalonamiento.')->group(function () {
    Route::middleware(['can:escalonamiento.ver'])->group(function () {
        Route::get('/', [EscalonamientoController::class, 'index'])->name('index');
        Route::get('/clientes/exportar', [EscalonamientoController::class, 'exportarClientes'])->name('clientes.exportar');
        Route::get('/clientes/buscar', [EscalonamientoController::class, 'buscarClientes'])->name('clientes.buscar');
        Route::get('/clientes/{cliente}', [EscalonamientoController::class, 'fichaCliente'])->name('clientes.ficha');
        Route::get('/conciliacion', [EscalonamientoController::class, 'conciliacion'])->name('conciliacion');
        Route::get('/cierre', [EscalonamientoController::class, 'cierre'])->name('cierre');
        Route::get('/metricas', [EscalonamientoController::class, 'metricas'])->name('metricas');
        Route::get('/metricas/exportar', [EscalonamientoController::class, 'exportarMetricas'])->name('metricas.exportar');
        Route::get('/cierre/{cierre}/reporte', [EscalonamientoController::class, 'descargarReporte'])->name('cierre.reporte');
        Route::get('/devoluciones-pendientes', [EscalonamientoController::class, 'pendientes'])->name('devoluciones.pendientes');
        Route::get('/devoluciones/{documento}/candidatas', [EscalonamientoController::class, 'candidatas'])->name('devoluciones.candidatas');
        Route::get('/solicitudes/{solicitud}/conciliacion/sugerencias', [EscalonamientoController::class, 'sugerirConciliacion'])
            ->name('conciliacion.sugerencias');
    });

    Route::middleware(['can:escalonamiento.operar'])->group(function () {
        Route::post('/periodos', [EscalonamientoController::class, 'abrir'])->name('periodos.abrir');
        Route::post('/periodos/historial', [EscalonamientoController::class, 'abrirHistorico'])->name('periodos.historial');
        Route::post('/importaciones/previsualizar', [EscalonamientoController::class, 'previsualizar'])->name('importaciones.previsualizar');
        Route::post('/importaciones/confirmar', [EscalonamientoController::class, 'confirmar'])->name('importaciones.confirmar');
        Route::post('/capturas', [EscalonamientoController::class, 'capturar'])->name('capturas.store');
        Route::post('/devoluciones/{documento}/vincular', [EscalonamientoController::class, 'vincular'])->name('devoluciones.vincular');
        Route::post('/aplicaciones/{aplicacion}/revertir', [EscalonamientoController::class, 'revertir'])->name('aplicaciones.revertir');
        Route::post('/incidencias/{incidencia}/resolver', [EscalonamientoController::class, 'resolverIncidencia'])->name('incidencias.resolver');
        Route::post('/conciliacion/asignar', [EscalonamientoController::class, 'asignarConciliacion'])->name('conciliacion.asignar');
        Route::post('/conciliacion/{conciliacionFila}/quitar', [EscalonamientoController::class, 'quitarConciliacion'])->name('conciliacion.quitar');
        Route::post('/cierre/simular', [EscalonamientoController::class, 'simularCierre'])->name('cierre.simular');
        Route::post('/cierre/cancelar', [EscalonamientoController::class, 'cancelarCierre'])->name('cierre.cancelar');
        Route::post('/cierre/{cierre}/aplicacion-externa', [EscalonamientoController::class, 'registrarAplicacionExterna'])
            ->name('cierre.aplicacion_externa');
    });

    Route::middleware(['can:escalonamiento.autorizar'])->group(function () {
        Route::post('/autoridad', [EscalonamientoController::class, 'actualizarAutoridad'])->name('autoridad.actualizar');
        Route::post('/cierre/autorizar', [EscalonamientoController::class, 'autorizarCierre'])->name('cierre.autorizar');
        Route::post('/cierre/aplicar', [EscalonamientoController::class, 'aplicarCierre'])->name('cierre.aplicar');
    });
});
