<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SaldosAFavor\SaldosAFavorController;
use App\Http\Controllers\SaldosAFavor\CajaSaldosAFavorController;
use App\Http\Controllers\SaldosAFavor\ConfigurarSaldosAFavorController;
use App\Http\Controllers\SaldosAFavor\MigrarSaldosAFavorController;

Route::prefix('saldos-favor')->name('saldos_favor.')->group(function () {
    Route::middleware(['can:saldos_favor.ver'])->group(function () {
        Route::get('/', [SaldosAFavorController::class, 'index'])->name('index');
        Route::get('/buscar-cliente', [SaldosAFavorController::class, 'buscarCliente'])->name('buscar_cliente');
        Route::get('/cuenta/{cliente}', [SaldosAFavorController::class, 'cuenta'])->name('cuenta');
        Route::get('/api/cuenta/{cliente}', [SaldosAFavorController::class, 'apiCuenta'])->name('api.cuenta');
        Route::get('/api/sugerir/{cliente}', [SaldosAFavorController::class, 'apiSugerir'])->name('api.sugerir');
    });

    Route::middleware(['can:saldos_favor.generar'])
        ->post('/generar', [SaldosAFavorController::class, 'generar'])
        ->name('generar');

    Route::middleware(['can:saldos_favor.revisar'])->group(function () {
        Route::post('/creditos/{credito}/revisar', [SaldosAFavorController::class, 'revisar'])->name('revisar');
        Route::post('/pagos/{pago}/revisar', [SaldosAFavorController::class, 'revisarPago'])->name('pagos.revisar');
    });

    Route::middleware(['can:saldos_favor.ajustar'])->group(function () {
        Route::post('/creditos/{credito}/ajustar', [SaldosAFavorController::class, 'ajustar'])->name('ajustar');
        Route::post('/creditos/{credito}/reactivar', [SaldosAFavorController::class, 'reactivar'])->name('reactivar');
        Route::post('/creditos/{credito}/revertir-aplicacion', [SaldosAFavorController::class, 'revertirAplicacion'])->name('revertir_aplicacion');
        Route::post('/incidencias/{incidencia}/resolver', [SaldosAFavorController::class, 'resolverIncidencia'])->name('incidencias.resolver');
    });

    Route::middleware(['can:saldos_favor.cancelar'])
        ->post('/creditos/{credito}/cancelar', [SaldosAFavorController::class, 'cancelar'])
        ->name('cancelar');
});

Route::middleware(['can:saldos_favor.caja'])->prefix('saldos-favor/caja')->name('saldos_favor.caja.')->group(function () {
    Route::get('/', [CajaSaldosAFavorController::class, 'index'])->name('index');
    Route::post('/generar', [CajaSaldosAFavorController::class, 'generarCredito'])->name('generar');
    Route::post('/aplicar', [CajaSaldosAFavorController::class, 'aplicar'])->name('aplicar');
    Route::get('/comprobante/{comprobante}', [CajaSaldosAFavorController::class, 'comprobante'])->name('comprobante');
    Route::get('/comprobante/{comprobante}/imprimir', [CajaSaldosAFavorController::class, 'imprimir'])->name('imprimir');
    Route::get('/comprobante/{comprobante}/descargar', [CajaSaldosAFavorController::class, 'descargar'])->name('descargar');
    Route::post('/comprobante/{comprobante}/firmar', [CajaSaldosAFavorController::class, 'marcarFirmado'])->name('firmar');
    Route::post('/preferencia', [CajaSaldosAFavorController::class, 'guardarPreferencia'])->name('preferencia');
});

Route::middleware(['can:saldos_favor.configurar'])->prefix('saldos-favor/configurar')->name('saldos_favor.configurar.')->group(function () {
    Route::get('/', [ConfigurarSaldosAFavorController::class, 'edit'])->name('edit');
    Route::put('/', [ConfigurarSaldosAFavorController::class, 'update'])->name('update');
});

Route::middleware(['can:saldos_favor.migrar'])->prefix('saldos-favor/migrar')->name('saldos_favor.migrar.')->group(function () {
    Route::get('/', [MigrarSaldosAFavorController::class, 'index'])->name('index');
    Route::get('/plantilla', [MigrarSaldosAFavorController::class, 'plantilla'])->name('plantilla');
    Route::post('/preview', [MigrarSaldosAFavorController::class, 'preview'])->name('preview');
    Route::post('/importar', [MigrarSaldosAFavorController::class, 'importar'])->name('importar');
});
