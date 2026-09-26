<?php

use App\Http\Controllers\Almacenes\AlmacenCatalogoController;
use App\Http\Controllers\Almacenes\AlmacenesResumenController;
use App\Http\Controllers\Almacenes\CoberturaController;
use App\Http\Controllers\Almacenes\EstablecerSucursalActivaAlmacenesController;
use App\Http\Controllers\Almacenes\CostoController as AlmacenCostoController;
use App\Http\Controllers\Almacenes\ImportacionAlmacenController;
use App\Http\Controllers\Almacenes\ImportacionLoteController;
use App\Http\Controllers\Almacenes\InventarioController as AlmacenInventarioController;
use Illuminate\Support\Facades\Route;

$permisoImportar = 'gestion_interna.productos.gestionar|gestion_interna.productos.importar|almacenes.productos.gestionar|almacenes.inventarios.importar|almacenes.costos.importar|catalogos.gestionar|reportes.ventas.importar';

Route::prefix('almacenes')->name('almacenes.')->group(function () use ($permisoImportar) {
    Route::permanentRedirect('/productos', '/gestion-interna/productos');

    Route::get('/', [AlmacenesResumenController::class, 'index'])
        ->middleware('role_or_permission:almacenes.inventarios.ver|almacenes.costos.ver|gestion_interna.productos.ver|almacenes.productos.ver|catalogos.gestionar')
        ->name('index');

    Route::put('/contexto/sucursal-activa', EstablecerSucursalActivaAlmacenesController::class)
        ->middleware('role_or_permission:almacenes.inventarios.ver|almacenes.costos.ver|catalogos.gestionar')
        ->name('contexto.sucursal_activa');

    Route::middleware(['role_or_permission:almacenes.inventarios.ver|almacenes.costos.ver|catalogos.gestionar'])->prefix('catalogo')->name('catalogo.')->group(function () {
        Route::get('/', [AlmacenCatalogoController::class, 'index'])->name('index');
        Route::post('/', [AlmacenCatalogoController::class, 'store'])
            ->middleware('role_or_permission:almacenes.inventarios.gestionar|catalogos.gestionar')
            ->name('store');
        Route::put('/{almacen}', [AlmacenCatalogoController::class, 'update'])
            ->middleware('role_or_permission:almacenes.inventarios.gestionar|catalogos.gestionar')
            ->name('update');
    });

    Route::middleware(['role_or_permission:almacenes.inventarios.ver|catalogos.gestionar'])->prefix('cobertura')->name('cobertura.')->group(function () {
        Route::get('/', [CoberturaController::class, 'index'])->name('index');
        Route::post('/asignar', [CoberturaController::class, 'asignar'])
            ->middleware('role_or_permission:almacenes.inventarios.gestionar|catalogos.gestionar')
            ->name('asignar');
    });

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

    Route::prefix('importaciones')->name('importaciones.')->group(function () use ($permisoImportar) {
        Route::middleware("role_or_permission:{$permisoImportar}")->group(function () {
            Route::get('/', [ImportacionLoteController::class, 'index'])->name('index');
            Route::post('/analizar', [ImportacionLoteController::class, 'analizar'])->name('analizar');
            Route::get('/lotes/{lote}', [ImportacionLoteController::class, 'show'])->name('show');
            Route::post('/lotes/{lote}/simular', [ImportacionLoteController::class, 'simular'])->name('simular');
            Route::post('/lotes/{lote}/aplicar', [ImportacionLoteController::class, 'aplicar'])->name('aplicar');
            Route::get('/lotes/{lote}/errores', [ImportacionLoteController::class, 'errores'])->name('errores');
        });

        Route::get('/activo', [ImportacionAlmacenController::class, 'activo'])
            ->middleware("role_or_permission:{$permisoImportar}")
            ->name('activo');
        Route::get('/progreso/{id}', [ImportacionAlmacenController::class, 'progreso'])
            ->middleware("role_or_permission:{$permisoImportar}")
            ->name('progreso');
        Route::delete('/{id}/cancelar', [ImportacionAlmacenController::class, 'cancelar'])
            ->middleware("role_or_permission:{$permisoImportar}")
            ->name('cancelar');
        Route::post('/{id}/continuar', [ImportacionAlmacenController::class, 'continuar'])
            ->middleware("role_or_permission:{$permisoImportar}")
            ->name('continuar');
    });

    Route::get('/importaciones/reporte-errores/{token}', [ImportacionAlmacenController::class, 'descargarReporteErrores'])
        ->middleware("role_or_permission:{$permisoImportar}")
        ->name('importaciones.reporte_errores');
});
