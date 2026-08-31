<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AutoCobranzaController;
use App\Http\Controllers\Reportes\ReporteCobranzaController;

Route::prefix('auto-cobranza')->name('auto-cobranza.')->group(function () {
    Route::get('/', [AutoCobranzaController::class, 'index'])->name('index');
    Route::post('/importar/preview', [AutoCobranzaController::class, 'previsualizarImporte'])->name('importar.preview');
    Route::post('/importar', [AutoCobranzaController::class, 'importarReporte'])->name('importar');
    Route::put('/alertas/{alerta}', [AutoCobranzaController::class, 'actualizarAlerta'])->name('alertas.update');
    Route::get('/clientes/{clienteId}/bitacora', [AutoCobranzaController::class, 'bitacora'])->name('bitacora');
    Route::get('/clientes/{cliente}/folios', [AutoCobranzaController::class, 'foliosCliente'])->name('clientes.folios');
    Route::get('/historial', [AutoCobranzaController::class, 'historial'])->name('historial');
    Route::get('/abonos-hoy', [AutoCobranzaController::class, 'abonosDelDia'])->name('abonos-hoy');
    Route::post('/clientes/{cliente}/resolver-aumento', [AutoCobranzaController::class, 'resolverAumento'])->name('alertas.resolver-aumento');
    Route::put('/clientes/{cliente}/reparar-fecha', [AutoCobranzaController::class, 'repararFechaInicio'])->name('clientes.reparar-fecha');
    Route::post('/clientes/{cliente}/recalcular-credito', [AutoCobranzaController::class, 'recalcularCredito'])->name('clientes.recalcular-credito');
    Route::post('/recalcular-creditos', [AutoCobranzaController::class, 'recalcularCreditosMasivo'])->name('recalcular-creditos');
    Route::post('/facturas/{cobranzaFactura}/confirmar-pago', [AutoCobranzaController::class, 'confirmarPagoCobranza'])->name('facturas.confirmar-pago');
    Route::post('/facturas/{cobranzaFactura}/descartar-pago', [AutoCobranzaController::class, 'descartarPagoCobranza'])->name('facturas.descartar-pago');
    Route::post('/facturas/{cobranzaFactura}/verificar', [AutoCobranzaController::class, 'verificarPago'])->name('facturas.verificar');
    Route::post('/configuracion', [AutoCobranzaController::class, 'guardarConfiguracion'])->name('configuracion.store');

    Route::middleware(['can:cobranza.reportes'])->prefix('reportes')->name('reportes.')->group(function () {
        Route::post('/generar', [ReporteCobranzaController::class, 'generar'])->name('generar');
        Route::get('/estado/{jobId}', [ReporteCobranzaController::class, 'estado'])->name('estado');
        Route::get('/descargar/{jobId}', [ReporteCobranzaController::class, 'descargar'])->name('descargar');
    });
});
