<?php

use App\Http\Controllers\Comercial\VisitaProgramadaController;
use Illuminate\Support\Facades\Route;

Route::middleware(['can:visitas_programadas.gestionar'])->group(function () {
    Route::get('/visitas-programadas', [VisitaProgramadaController::class, 'index'])->name('visitas_programadas.index');
    Route::post('/visitas-programadas', [VisitaProgramadaController::class, 'store'])->name('visitas_programadas.store');
    Route::get('/visitas-programadas/buscar-cliente', [VisitaProgramadaController::class, 'buscarCliente'])
        ->name('visitas_programadas.buscar_cliente');
});
