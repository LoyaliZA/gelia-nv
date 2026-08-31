<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\MonitoreoMensajeriaController;

Route::middleware(['can:mensajeria.monitorear'])->prefix('mensajeria-monitoreo')->name('mensajeria_monitoreo.')->group(function () {
    Route::get('/', [MonitoreoMensajeriaController::class, 'index'])->name('index');
    Route::get('/conversaciones', [MonitoreoMensajeriaController::class, 'conversaciones'])->name('conversaciones');
    Route::get('/conversaciones/{conversacion}/mensajes', [MonitoreoMensajeriaController::class, 'mensajes'])->name('mensajes');
    Route::delete('/conversaciones/{conversacion}', [MonitoreoMensajeriaController::class, 'destroyConversacion'])->name('conversaciones.destroy');
    Route::delete('/mensajes/{mensaje}', [MonitoreoMensajeriaController::class, 'destroyMensaje'])->name('mensajes.destroy');
});
