<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MapaLogisticoController;

Route::middleware(['can:entregas.configurar_zonas'])->prefix('mapa-logistico')->name('mapa_logistico.')->group(function () {
    Route::get('/', [MapaLogisticoController::class, 'index'])->name('index');
    Route::get('/exportar/{tipo}', [MapaLogisticoController::class, 'exportar'])->name('exportar');
    Route::post('/importar/{tipo}', [MapaLogisticoController::class, 'importar'])->name('importar');
    Route::post('/{tipo}', [MapaLogisticoController::class, 'store'])->name('store');
    Route::put('/{tipo}/{id}/poligono', [MapaLogisticoController::class, 'actualizarPoligono'])->name('poligono.update');
    Route::put('/periferia/{id}', [MapaLogisticoController::class, 'actualizarPeriferia'])->name('periferia.update');
    Route::put('/{tipo}/{id}/activo', [MapaLogisticoController::class, 'toggleActivo'])->name('toggle');
    Route::delete('/{tipo}/{id}', [MapaLogisticoController::class, 'eliminar'])->name('eliminar');
});
