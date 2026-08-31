<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\ConfiguracionSistemaController;

Route::middleware(['can:configuracion_sistema.gestionar'])->prefix('configuracion-sistema')->name('configuracion_sistema.')->group(function () {
    Route::get('/', [ConfiguracionSistemaController::class, 'index'])->name('index');
    Route::post('/', [ConfiguracionSistemaController::class, 'store'])->name('store');
    Route::put('/{configuracion}', [ConfiguracionSistemaController::class, 'update'])->name('update');
    Route::delete('/{configuracion}', [ConfiguracionSistemaController::class, 'destroy'])->name('destroy');
    Route::post('/test-mail', [ConfiguracionSistemaController::class, 'testMail'])->name('test_mail');
});
