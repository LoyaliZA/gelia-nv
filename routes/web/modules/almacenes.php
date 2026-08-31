<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Almacenes\CostoController as AlmacenCostoController;
use App\Http\Controllers\Almacenes\ImportacionAlmacenController;
use App\Http\Controllers\Almacenes\InventarioController as AlmacenInventarioController;

Route::prefix('almacenes')->name('almacenes.')->group(function () {
    Route::permanentRedirect('/productos', '/gestion-interna/productos');

    Route::middleware(['role_or_permission:almacenes.inventarios.ver|catalogos.gestionar'])->prefix('inventarios')->name('inventarios.')->group(function () {
        Route::get('/', [AlmacenInventarioController::class, 'index'])->name('index');
        Route::post('/', [AlmacenInventarioController::class, 'store'])->middleware('role_or_permission:almacenes.inventarios.gestionar|catalogos.gestionar')->name('store');
        Route::put('/{inventario}', [AlmacenInventarioController::class, 'update'])->middleware('role_or_permission:almacenes.inventarios.gestionar|catalogos.gestionar')->name('update');
        Route::delete('/{inventario}', [AlmacenInventarioController::class, 'destroy'])->middleware('role_or_permission:almacenes.inventarios.gestionar|catalogos.gestionar')->name('destroy');
        Route::post('/import-preview', [AlmacenInventarioController::class, 'importPreview'])->middleware('role_or_permission:almacenes.inventarios.importar|catalogos.gestionar')->name('import_preview');
        Route::post('/import-iniciar', [AlmacenInventarioController::class, 'importIniciar'])->middleware('role_or_permission:almacenes.inventarios.importar|catalogos.gestionar')->name('import_iniciar');
        Route::get('/plantilla-importacion', [AlmacenInventarioController::class, 'descargarPlantillaImportacion'])->middleware('role_or_permission:almacenes.inventarios.importar|catalogos.gestionar')->name('plantilla_importacion');
    });

    Route::middleware(['role_or_permission:almacenes.costos.ver|catalogos.gestionar'])->prefix('costos')->name('costos.')->group(function () {
        Route::get('/', [AlmacenCostoController::class, 'index'])->name('index');
        Route::post('/', [AlmacenCostoController::class, 'store'])->middleware('role_or_permission:almacenes.costos.gestionar|catalogos.gestionar')->name('store');
        Route::put('/{costo}', [AlmacenCostoController::class, 'update'])->middleware('role_or_permission:almacenes.costos.gestionar|catalogos.gestionar')->name('update');
        Route::delete('/{costo}', [AlmacenCostoController::class, 'destroy'])->middleware('role_or_permission:almacenes.costos.gestionar|catalogos.gestionar')->name('destroy');
        Route::get('/plantilla-importacion', [AlmacenCostoController::class, 'descargarPlantillaImportacion'])->middleware('role_or_permission:almacenes.costos.importar|catalogos.gestionar')->name('plantilla_importacion');
        Route::post('/import-preview', [AlmacenCostoController::class, 'importPreview'])->middleware('role_or_permission:almacenes.costos.importar|catalogos.gestionar')->name('import_preview');
        Route::post('/import-iniciar', [AlmacenCostoController::class, 'importIniciar'])->middleware('role_or_permission:almacenes.costos.importar|catalogos.gestionar')->name('import_iniciar');
    });

    Route::prefix('importaciones')->name('importaciones.')->group(function () {
        Route::get('/progreso/{id}', [ImportacionAlmacenController::class, 'progreso'])
            ->middleware('role_or_permission:gestion_interna.productos.gestionar|gestion_interna.productos.importar|almacenes.productos.gestionar|almacenes.inventarios.importar|almacenes.costos.importar|catalogos.gestionar|reportes.ventas.importar')
            ->name('progreso');
        Route::get('/activo', [ImportacionAlmacenController::class, 'activo'])
            ->middleware('role_or_permission:gestion_interna.productos.gestionar|gestion_interna.productos.importar|almacenes.productos.gestionar|almacenes.inventarios.importar|almacenes.costos.importar|catalogos.gestionar|reportes.ventas.importar')
            ->name('activo');
        Route::delete('/{id}/cancelar', [ImportacionAlmacenController::class, 'cancelar'])
            ->middleware('role_or_permission:gestion_interna.productos.gestionar|gestion_interna.productos.importar|almacenes.productos.gestionar|almacenes.inventarios.importar|almacenes.costos.importar|catalogos.gestionar|reportes.ventas.importar')
            ->name('cancelar');
        Route::post('/{id}/continuar', [ImportacionAlmacenController::class, 'continuar'])
            ->middleware('role_or_permission:gestion_interna.productos.gestionar|gestion_interna.productos.importar|almacenes.productos.gestionar|almacenes.inventarios.importar|almacenes.costos.importar|catalogos.gestionar|reportes.ventas.importar')
            ->name('continuar');
    });

    Route::get('/importaciones/reporte-errores/{token}', [ImportacionAlmacenController::class, 'descargarReporteErrores'])
        ->middleware('role_or_permission:gestion_interna.productos.gestionar|gestion_interna.productos.importar|almacenes.productos.gestionar|almacenes.inventarios.importar|almacenes.costos.importar|catalogos.gestionar|reportes.ventas.importar')
        ->name('importaciones.reporte_errores');
});
