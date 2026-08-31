<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PlantillaBellaromaController;

Route::middleware(['can:plantilla_pedidos.ver'])->prefix('plantilla-bellaroma')->name('plantilla_bellaroma.')->group(function () {
    Route::get('/', [PlantillaBellaromaController::class, 'index'])->name('index');
    Route::post('/generar', [PlantillaBellaromaController::class, 'generar'])->name('generar');
    Route::get('/{id}/descargar', [PlantillaBellaromaController::class, 'descargar'])->name('descargar');
    Route::delete('/{id}', [PlantillaBellaromaController::class, 'eliminar'])->name('eliminar');
    Route::post('/configuracion', [PlantillaBellaromaController::class, 'guardarConfiguracion'])->name('configuracion.guardar');
});
