<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GestionInterna\DirectorioController;
use App\Http\Controllers\GestionInterna\ProductoController as GestionInternaProductoController;

Route::prefix('gestion-interna')->name('gestion_interna.')->group(function () {
    // Lookup catálogo (CEDIS pesaje + almacén); fuera del grupo "ver productos" para no bloquear CEDIS.
    Route::get('/productos/buscar', [GestionInternaProductoController::class, 'buscar'])
        ->middleware('role_or_permission:gestion_interna.productos.ver|almacenes.productos.ver|almacenes.inventarios.ver|almacenes.costos.ver|catalogos.gestionar|reportes.ventas.ver|control_pedidos.cedis')
        ->name('productos.buscar');

    Route::middleware(['role_or_permission:gestion_interna.productos.ver|catalogos.gestionar|almacenes.productos.ver'])->prefix('productos')->name('productos.')->group(function () {
        Route::get('/', [GestionInternaProductoController::class, 'index'])->name('index');
        Route::get('/plantilla-importacion', [GestionInternaProductoController::class, 'descargarPlantillaImportacion'])->middleware('role_or_permission:gestion_interna.productos.importar|gestion_interna.productos.gestionar|catalogos.gestionar')->name('plantilla_importacion');
        Route::post('/import-preview', [GestionInternaProductoController::class, 'importPreview'])->middleware('role_or_permission:gestion_interna.productos.importar|gestion_interna.productos.gestionar|catalogos.gestionar')->name('import_preview');
        Route::post('/import-iniciar', [GestionInternaProductoController::class, 'importIniciar'])->middleware('role_or_permission:gestion_interna.productos.importar|gestion_interna.productos.gestionar|catalogos.gestionar')->name('import_iniciar');
        Route::get('/{producto}/ficha', [GestionInternaProductoController::class, 'ficha'])->name('ficha');
        Route::post('/', [GestionInternaProductoController::class, 'store'])->middleware('role_or_permission:gestion_interna.productos.gestionar|catalogos.gestionar')->name('store');
        Route::put('/{producto}', [GestionInternaProductoController::class, 'update'])->middleware('role_or_permission:gestion_interna.productos.gestionar|catalogos.gestionar')->name('update');
        Route::delete('/{producto}', [GestionInternaProductoController::class, 'destroy'])->middleware('role_or_permission:gestion_interna.productos.gestionar|catalogos.gestionar')->name('destroy');
    });

    Route::middleware(['can:gestion_interna.directorio.ver'])->group(function () {
        Route::get('/directorio', [DirectorioController::class, 'index'])->name('directorio.index');

        Route::post('/directorio/correos', [DirectorioController::class, 'storeCorreo'])->name('directorio.correos.store');
        Route::put('/directorio/correos/{id}', [DirectorioController::class, 'updateCorreo'])->name('directorio.correos.update');
        Route::delete('/directorio/correos/{id}', [DirectorioController::class, 'destroyCorreo'])->name('directorio.correos.destroy');

        Route::post('/directorio/telefonos', [DirectorioController::class, 'storeTelefono'])->name('directorio.telefonos.store');
        Route::put('/directorio/telefonos/{id}', [DirectorioController::class, 'updateTelefono'])->name('directorio.telefonos.update');
        Route::delete('/directorio/telefonos/{id}', [DirectorioController::class, 'destroyTelefono'])->name('directorio.telefonos.destroy');

        Route::post('/directorio/extensiones', [DirectorioController::class, 'storeExtension'])->name('directorio.extensiones.store');
        Route::put('/directorio/extensiones/{id}', [DirectorioController::class, 'updateExtension'])->name('directorio.extensiones.update');
        Route::delete('/directorio/extensiones/{id}', [DirectorioController::class, 'destroyExtension'])->name('directorio.extensiones.destroy');
    });
});
