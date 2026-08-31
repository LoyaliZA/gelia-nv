<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Activos\ActivoController;
use App\Http\Controllers\Api\ClienteApiController;
use App\Http\Controllers\Api\CotizacionEntregaController;

Route::prefix('api')->name('api.')->group(function () {
    Route::get('/clientes', [ClienteApiController::class, 'index'])->name('clientes.index');
    Route::get('/clientes/id/{id}/direccion-envio', [ClienteApiController::class, 'direccionEnvio'])->name('clientes.direccion_envio');
    Route::get('/clientes/{numero}', [ClienteApiController::class, 'show'])->name('clientes.show');
    Route::get('/activos/usuarios', [ActivoController::class, 'buscarUsuarios'])->name('activos.usuarios');
    Route::get('/activos/buscar', [ActivoController::class, 'buscarActivos'])->name('activos.buscar');
    Route::get('/activos/marcas', [ActivoController::class, 'buscarMarcas'])->name('activos.marcas');
    Route::get('/activos/modelos', [ActivoController::class, 'buscarModelos'])->name('activos.modelos');
    Route::middleware(['auth'])->post('/entregas/cotizar', [CotizacionEntregaController::class, 'calcularCosto'])->name('entregas.cotizar');
});
