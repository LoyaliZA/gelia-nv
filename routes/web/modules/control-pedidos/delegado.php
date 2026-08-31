<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ControlPedidos\PedidoBmaDelegadoController;

Route::middleware(['can:control_pedidos.delegado'])
    ->prefix('control-pedidos/delegado')
    ->name('control_pedidos.delegado.')
    ->group(function () {
        Route::get('/', [PedidoBmaDelegadoController::class, 'index'])->name('index');
        Route::get('/listado', [PedidoBmaDelegadoController::class, 'listado'])->name('listado');
        Route::get('/exportar', [PedidoBmaDelegadoController::class, 'exportar'])->name('exportar');
        Route::post('/importar', [PedidoBmaDelegadoController::class, 'importar'])->name('importar');
        Route::post('/{pedidoBma}/asignar-guia', [PedidoBmaDelegadoController::class, 'asignarGuia'])->name('asignar_guia');
        Route::post('/{pedidoBma}/actualizar-guia', [PedidoBmaDelegadoController::class, 'actualizarGuia'])->name('actualizar_guia');
        Route::post('/{pedidoBma}/guia-pdf', [PedidoBmaDelegadoController::class, 'subirGuiaPdf'])->name('guia_pdf.store');
        Route::delete('/{pedidoBma}/guia-pdf', [PedidoBmaDelegadoController::class, 'eliminarGuiaPdf'])->name('guia_pdf.destroy');
        Route::post('/{pedidoBma}/reportar-error-datos', [PedidoBmaDelegadoController::class, 'reportarErrorDatos'])->name('reportar_error_datos');
    });
