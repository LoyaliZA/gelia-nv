<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\ApiExternaController;

Route::prefix('api-externa')->name('api_externa.')->group(function () {
    Route::get('/', [ApiExternaController::class, 'index'])->name('index');
    Route::middleware(['can:api_externa.gestionar'])->group(function () {
        Route::post('/aplicaciones', [ApiExternaController::class, 'storeAplicacion'])->name('aplicaciones.store');
        Route::put('/aplicaciones/{aplicacion}', [ApiExternaController::class, 'updateAplicacion'])->name('aplicaciones.update');
        Route::post('/aplicaciones/{aplicacion}/regenerar-secret', [ApiExternaController::class, 'regenerarSecret'])->name('aplicaciones.regenerar_secret');
        Route::post('/aplicaciones/{aplicacion}/revocar-tokens', [ApiExternaController::class, 'revocarTokens'])->name('aplicaciones.revocar_tokens');
        Route::delete('/aplicaciones/{aplicacion}', [ApiExternaController::class, 'destroyAplicacion'])->name('aplicaciones.destroy');
        Route::put('/recursos/{recurso}', [ApiExternaController::class, 'updateRecurso'])->name('recursos.update');
        Route::put('/campos/{campo}', [ApiExternaController::class, 'updateCampo'])->name('campos.update');
        Route::put('/permisos/{permiso}', [ApiExternaController::class, 'updatePermiso'])->name('permisos.update');
        Route::put('/aplicacion-campos/{campo}', [ApiExternaController::class, 'updateCampoAplicacion'])->name('aplicacion_campos.update');
        Route::get('/documentacion/pdf', [ApiExternaController::class, 'descargarDocumentacion'])->name('documentacion.pdf');
    });
});
