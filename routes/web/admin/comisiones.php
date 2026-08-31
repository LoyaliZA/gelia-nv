<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminController;

Route::middleware(['can:comisiones.gestionar'])->group(function () {
    Route::get('/comisiones', [AdminController::class, 'comisiones'])->name('comisiones');
    Route::put('/comisiones/{id}', [AdminController::class, 'actualizarComision'])->name('comisiones.update');
});
