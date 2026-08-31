<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ControlPedidos\PedidoBmaAuditoriaController;
use App\Http\Controllers\ControlPedidos\PedidoBmaSaldosPagosController;

Route::prefix('control-pedidos')->name('control_pedidos.')->group(function () {
    Route::middleware(['can:control_pedidos.auditar'])->group(function () {
        Route::post('/pagos/{pago}/revisar', [PedidoBmaSaldosPagosController::class, 'revisarPago'])->name('pagos.revisar');
        Route::post('/{pedidoBma}/pagos/rechazar', [PedidoBmaSaldosPagosController::class, 'rechazarPagos'])->name('pagos.rechazar');
        Route::get('/{pedidoBma}/pagos-auditoria', [PedidoBmaSaldosPagosController::class, 'resumenPago'])->name('pagos.resumen_auditoria');
    });
});

Route::middleware(['can:control_pedidos.auditar'])
    ->prefix('control-pedidos/auditar')
    ->name('control_pedidos.auditar.')
    ->group(function () {
        Route::get('/', [PedidoBmaAuditoriaController::class, 'index'])->name('index');
        Route::get('/listado', [PedidoBmaAuditoriaController::class, 'listado'])->name('listado');
        Route::post('/{pedidoBma}/validar-pago', [PedidoBmaAuditoriaController::class, 'validarPago'])->name('validar_pago');
        Route::post('/{pedidoBma}/remision', [PedidoBmaAuditoriaController::class, 'subirRemision'])->name('remision.store');
        Route::put('/{pedidoBma}/folio-remision', [PedidoBmaAuditoriaController::class, 'actualizarFolioRemision'])->name('folio_remision.update');
        Route::delete('/{pedidoBma}/remision', [PedidoBmaAuditoriaController::class, 'eliminarRemision'])->name('remision.destroy');
        Route::post('/{pedidoBma}/aprobar', [PedidoBmaAuditoriaController::class, 'aprobar'])->name('aprobar');
        Route::post('/{pedidoBma}/rechazar', [PedidoBmaAuditoriaController::class, 'rechazar'])->name('rechazar');
        Route::post('/{pedidoBma}/reportar-error-datos', [PedidoBmaAuditoriaController::class, 'reportarErrorDatos'])->name('reportar_error_datos');
        Route::post('/{pedidoBma}/liberar-resguardo', [PedidoBmaAuditoriaController::class, 'liberarResguardo'])->name('liberar_resguardo');
        Route::post('/{pedidoBma}/anexar-pago-envio', [PedidoBmaAuditoriaController::class, 'anexarPagoEnvio'])->name('anexar_pago_envio');
        Route::post('/{pedidoBma}/anexo-envio/aprobar', [PedidoBmaAuditoriaController::class, 'aprobarAnexoEnvio'])->name('anexo_envio.aprobar');
        Route::post('/{pedidoBma}/anexo-envio/rechazar', [PedidoBmaAuditoriaController::class, 'rechazarAnexoEnvio'])->name('anexo_envio.rechazar');
        Route::post('/{pedidoBma}/incidencias-saf/{incidencia}/resolver', [PedidoBmaAuditoriaController::class, 'resolverIncidenciaSaf'])->name('incidencias_saf.resolver');
        Route::post('/{pedidoBma}/revision-en-curso', [PedidoBmaAuditoriaController::class, 'marcarRevisionEnCurso'])->name('revision_en_curso');
        Route::delete('/{pedidoBma}/revision-en-curso', [PedidoBmaAuditoriaController::class, 'soltarRevisionEnCurso'])->name('revision_en_curso.soltar');
    });
