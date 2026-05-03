<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Solicitudes\SolicitudController;

// Ruta temporal sin protección para verificar la vista
Route::get('/solicitudes', [SolicitudController::class, 'index'])->name('solicitudes.index');

Route::middleware(['auth', 'verified'])->group(function () {
    // Mantén el POST protegido
    Route::post('/solicitudes', [SolicitudController::class, 'store'])->name('solicitudes.store');
});