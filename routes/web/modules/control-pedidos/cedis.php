<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ControlPedidos\PedidoBmaCedisController;

Route::middleware(['can:control_pedidos.cedis'])
    ->prefix('control-pedidos/cedis')
    ->name('control_pedidos.cedis.')
    ->group(function () {
        Route::get('/', [PedidoBmaCedisController::class, 'index'])->name('index');
        Route::get('/listado', [PedidoBmaCedisController::class, 'listado'])->name('listado');
        Route::post('/{pedidoBma}/marcar-empacado', [PedidoBmaCedisController::class, 'marcarEmpacado'])->name('marcar_empacado');
        Route::post('/{pedidoBma}/marcar-enviado', [PedidoBmaCedisController::class, 'marcarEnviado'])->name('marcar_enviado');
        Route::post('/{pedidoBma}/reabrir-envio', [PedidoBmaCedisController::class, 'reabrirEnvio'])->name('reabrir_envio');
        Route::post('/{pedidoBma}/revertir-empacado', [PedidoBmaCedisController::class, 'revertirEmpacado'])->name('revertir_empacado');
        Route::post('/{pedidoBma}/reportar-incidencia', [PedidoBmaCedisController::class, 'reportarIncidencia'])->name('reportar_incidencia');
        Route::post('/{pedidoBma}/reportar-error-datos', [PedidoBmaCedisController::class, 'reportarErrorDatos'])->name('reportar_error_datos');
        Route::post('/{pedidoBma}/marcar-resguardo-apartado', [PedidoBmaCedisController::class, 'marcarResguardoApartado'])->name('marcar_resguardo_apartado');
        Route::post('/{pedidoBma}/responder-pesaje', [PedidoBmaCedisController::class, 'responderPesaje'])->name('responder_pesaje');
        Route::post('/{pedidoBma}/reportar-sin-existencia', [PedidoBmaCedisController::class, 'reportarSinExistencia'])->name('reportar_sin_existencia');
        Route::post('/{pedidoBma}/confirmar-stock-sin-existencia', [PedidoBmaCedisController::class, 'confirmarStockSinExistencia'])->name('confirmar_stock_sin_existencia');
        Route::post('/{pedidoBma}/sesion-evidencia', [PedidoBmaCedisController::class, 'crearSesionEvidencia'])->name('sesion_evidencia.store');
        Route::get('/{pedidoBma}/sesion-evidencia', [PedidoBmaCedisController::class, 'mostrarSesionEvidencia'])->name('sesion_evidencia.show');
        Route::put('/{pedidoBma}/sesion-evidencia/snapshot', [PedidoBmaCedisController::class, 'snapshotSesionEvidencia'])->name('sesion_evidencia.snapshot');
        Route::post('/{pedidoBma}/sesion-evidencia/cancelar', [PedidoBmaCedisController::class, 'cancelarSesionEvidencia'])->name('sesion_evidencia.cancelar');
        Route::get('/{pedidoBma}/sesion-evidencia/fotos/{foto}', [PedidoBmaCedisController::class, 'verFotoSesionEvidencia'])->name('sesion_evidencia.foto');
        Route::post('/tareas/{tarea}/liberar', [PedidoBmaCedisController::class, 'liberarTarea'])
            ->middleware('can:control_pedidos.cedis.liberar')
            ->name('liberar');
    });
