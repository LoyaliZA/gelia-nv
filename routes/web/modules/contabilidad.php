<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Contabilidad\ContabilidadController;

Route::middleware(['can:contabilidad.ver'])->prefix('contabilidad')->name('contabilidad.')->group(function () {
    Route::get('/', [ContabilidadController::class, 'index'])->name('index');
    Route::get('/retiros', [ContabilidadController::class, 'retiros'])->name('retiros');
    Route::get('/dashboard-data', [ContabilidadController::class, 'dashboardData'])->name('dashboard_data');
    Route::get('/exportar-pdf', [ContabilidadController::class, 'exportarPdf'])->name('exportar_pdf');
    Route::get('/exportar-csv', [ContabilidadController::class, 'exportarCsv'])->name('exportar_csv');
    Route::post('/lista-preview', [ContabilidadController::class, 'listaPreview'])
        ->middleware('can:contabilidad.importar')
        ->name('lista_preview');
    Route::post('/previsualizar-mapeo', [ContabilidadController::class, 'previsualizarMapeo'])
        ->middleware('can:contabilidad.importar')
        ->name('previsualizar_mapeo');
    Route::post('/procesar-lista', [ContabilidadController::class, 'procesarLista'])
        ->middleware('can:contabilidad.importar')
        ->name('procesar_lista');
    Route::post('/pedidos', [ContabilidadController::class, 'store'])
        ->middleware('can:contabilidad.pedidos.crear')
        ->name('pedidos.store');
    Route::put('/pedidos/{pedido}', [ContabilidadController::class, 'update'])
        ->middleware('can:contabilidad.pedidos.editar')
        ->name('pedidos.update');
    Route::delete('/pedidos/{pedido}', [ContabilidadController::class, 'destroy'])
        ->middleware('can:contabilidad.pedidos.eliminar')
        ->name('pedidos.destroy');
    Route::post('/pedidos/{pedido}/confirmar-retiro', [ContabilidadController::class, 'confirmarRetiro'])
        ->middleware('can:contabilidad.retiros.confirmar')
        ->name('pedidos.confirmar_retiro');
    Route::post('/retiros/confirmar-lote', [ContabilidadController::class, 'confirmarLote'])
        ->middleware('can:contabilidad.retiros.confirmar')
        ->name('retiros.confirmar_lote');
    Route::put('/plataformas/comisiones', [ContabilidadController::class, 'actualizarComisiones'])
        ->middleware('can:contabilidad.plataformas.configurar')
        ->name('plataformas.comisiones');
    Route::put('/configuracion', [ContabilidadController::class, 'actualizarConfiguracion'])
        ->middleware('can:contabilidad.plataformas.configurar')
        ->name('configuracion.update');
});
