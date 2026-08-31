<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AuditoriaListaDescuentoController;

Route::middleware(['permission:sistema.auditorias.ver|sistema.auditorias.accesos.ver'])->group(function () {
    Route::get('/auditorias-sistema', [AuditoriaListaDescuentoController::class, 'index'])
        ->name('auditorias_sistema.index');
});
