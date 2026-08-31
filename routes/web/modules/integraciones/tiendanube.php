<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TiendanubeController;

Route::middleware(['can:tiendanube.ver'])
    ->prefix('tiendanube')
    ->name('tiendanube.')
    ->group(function () {
        Route::get('/', [TiendanubeController::class, 'index'])->name('index');
        Route::get('/imagenes', [TiendanubeController::class, 'imagenesIndex'])->name('imagenes.index')->middleware('can:tiendanube.productos.editar');
        Route::get('/progreso/{id}', [TiendanubeController::class, 'progreso'])->name('progreso');
        Route::get('/productos/{id}', [TiendanubeController::class, 'producto'])->name('productos.show');
        Route::post('/productos', [TiendanubeController::class, 'storeProducto'])->name('productos.store')->middleware('can:tiendanube.productos.editar');
        Route::put('/productos/{id}', [TiendanubeController::class, 'updateProducto'])->name('productos.update')->middleware('can:tiendanube.productos.editar');
        Route::post('/productos/{id}/imagenes', [TiendanubeController::class, 'storeImagen'])->name('productos.imagenes.store')->middleware('can:tiendanube.productos.editar');
        Route::get('/skus/resolver', [TiendanubeController::class, 'resolverSku'])->name('skus.resolver')->middleware('can:tiendanube.productos.editar');
        Route::post('/imagenes/importar', [TiendanubeController::class, 'importarImagenes'])->name('imagenes.importar')->middleware('can:tiendanube.productos.editar');
        Route::post('/imagenes/importar/archivos', [TiendanubeController::class, 'importarImagenesArchivos'])->name('imagenes.importar.archivos')->middleware('can:tiendanube.productos.editar');
        Route::get('/imagenes/importar/{id}', [TiendanubeController::class, 'progresoImportImagenes'])->name('imagenes.importar.progreso');
        Route::get('/imagenes/importar/{id}/reporte', [TiendanubeController::class, 'reporteImportImagenes'])->name('imagenes.importar.reporte');
        Route::get('/imagenes/importar/{id}/reporte-dimensiones', [TiendanubeController::class, 'reporteImportDimensiones'])->name('imagenes.importar.reporte_dimensiones');
        Route::get('/imagenes/reporte-alertas', [TiendanubeController::class, 'reporteAlertasImagenes'])->name('imagenes.reporte_alertas');
        Route::get('/imagenes/reporte-sin-foto', [TiendanubeController::class, 'reporteSinFoto'])->name('imagenes.reporte_sin_foto');
        Route::put('/configuracion', [TiendanubeController::class, 'guardarConfiguracion'])->name('configuracion.update')->middleware('can:tiendanube.configurar');
        Route::post('/configuracion/probar-conexion', [TiendanubeController::class, 'probarConexion'])->name('configuracion.probar_conexion')->middleware('can:tiendanube.configurar');
        Route::post('/catalogo/limpiar', [TiendanubeController::class, 'limpiarCatalogo'])->name('catalogo.limpiar')->middleware('can:tiendanube.configurar');
        Route::get('/webhooks', [TiendanubeController::class, 'listarWebhooks'])->name('webhooks.index')->middleware('can:tiendanube.configurar');
        Route::get('/webhooks/entregas', [TiendanubeController::class, 'listarEntregasWebhook'])->name('webhooks.entregas')->middleware('can:tiendanube.configurar');
        Route::post('/webhooks/aplicar-recomendados', [TiendanubeController::class, 'aplicarWebhooksRecomendados'])->name('webhooks.aplicar_recomendados')->middleware('can:tiendanube.configurar');
        Route::post('/webhooks', [TiendanubeController::class, 'crearWebhook'])->name('webhooks.store')->middleware('can:tiendanube.configurar');
        Route::put('/webhooks/{id}', [TiendanubeController::class, 'actualizarWebhook'])->name('webhooks.update')->middleware('can:tiendanube.configurar');
        Route::delete('/webhooks/{id}', [TiendanubeController::class, 'eliminarWebhook'])->name('webhooks.destroy')->middleware('can:tiendanube.configurar');
        Route::post('/sincronizar', [TiendanubeController::class, 'sincronizar'])->name('sincronizar')->middleware('can:tiendanube.sincronizar');
    });
