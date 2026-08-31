<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ClienteController;

Route::middleware(['can:mis_clientes.gestionar'])->group(function () {
    Route::get('/mis-clientes', [ClienteController::class, 'misClientes'])->name('mis_clientes.index');
    Route::post('/mis-clientes/rapido', [ClienteController::class, 'registroRapido'])->name('mis_clientes.rapido');
});
