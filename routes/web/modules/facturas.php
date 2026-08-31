<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Facturas\{SolicitudFacturaController, DatosFiscalesController, ArchivoFacturaController};

Route::prefix('facturas')->name('facturas.')->group(function () {
    Route::middleware(['can:facturas.gestionar_datos_fiscales'])
        ->prefix('datos-fiscales')
        ->name('datos_fiscales.')
        ->group(function () {
            Route::get('/', [DatosFiscalesController::class, 'index'])->name('index');
            Route::get('/plantilla-clientes', [DatosFiscalesController::class, 'plantillaClientes'])->name('plantilla_clientes');
            Route::post('/importar-clientes', [DatosFiscalesController::class, 'importarClientes'])->name('importar_clientes');
            Route::get('/plantilla-receptores', [DatosFiscalesController::class, 'plantillaReceptores'])->name('plantilla_receptores');
            Route::post('/importar-receptores', [DatosFiscalesController::class, 'importarReceptores'])->name('importar_receptores');
            Route::get('/receptores/buscar', [DatosFiscalesController::class, 'buscarReceptores'])->name('receptores.buscar');
            Route::post('/receptores', [DatosFiscalesController::class, 'storeReceptor'])->name('receptores.store');
            Route::put('/receptores/{receptor}', [DatosFiscalesController::class, 'updateReceptor'])->name('receptores.update');
            Route::put('/{cliente}', [DatosFiscalesController::class, 'update'])->name('update');
        });

    Route::middleware(['can:facturas.gestionar_datos_fiscales'])
        ->post('/{factura}/aplicar-datos-fiscales-cliente', [SolicitudFacturaController::class, 'aplicarDatosFiscalesAlCliente'])
        ->name('aplicar_datos_fiscales_cliente');

    Route::middleware(['can:facturas.crear'])->group(function () {
        Route::get('/receptores/buscar', [DatosFiscalesController::class, 'buscarReceptores'])->name('receptores.buscar');
        Route::get('/plantilla-fiscales/descargar', [SolicitudFacturaController::class, 'descargarPlantilla'])->name('plantilla_fiscales');
        Route::post('/', [SolicitudFacturaController::class, 'store'])->name('store');
        Route::put('/{factura}/borrador', [SolicitudFacturaController::class, 'actualizarBorrador'])->name('borrador');
        Route::post('/{factura}/enlace-fiscal', [SolicitudFacturaController::class, 'regenerarEnlaceFiscal'])->name('enlace_fiscal');
        Route::put('/{factura}/reparar', [SolicitudFacturaController::class, 'reparar'])->name('reparar');
    });

    Route::middleware(['can:facturas.ver_listado'])->group(function () {
        Route::get('/', [SolicitudFacturaController::class, 'index'])->name('index');
        Route::get('/exportar', [SolicitudFacturaController::class, 'exportar'])
            ->middleware('can:facturas.exportar')
            ->name('exportar');
        Route::get('/{factura}/datos-fiscales', [SolicitudFacturaController::class, 'datosFiscales'])->name('datos_fiscales');
        Route::get('/{factura}/archivo/{tipo}', [ArchivoFacturaController::class, 'show'])->name('archivo');
        Route::get('/{factura}', [SolicitudFacturaController::class, 'show'])->name('show');
    });

    Route::put('/{factura}/estado', [SolicitudFacturaController::class, 'actualizarEstado'])->name('actualizar_estado');
    Route::put('/{factura}/corregir', [SolicitudFacturaController::class, 'corregir'])->name('corregir');

    Route::middleware(['can:facturas.verificar'])
        ->put('/{factura}/verificar', [SolicitudFacturaController::class, 'verificar'])
        ->name('verificar');

    Route::middleware(['can:facturas.eliminar'])
        ->delete('/{factura}', [SolicitudFacturaController::class, 'destroy'])
        ->name('destroy');
});
