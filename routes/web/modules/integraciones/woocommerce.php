<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WooCommerceController;

Route::middleware(['can:woocommerce.ver'])
    ->prefix('woocommerce')
    ->name('woocommerce.')
    ->group(function () {
        Route::get('/', [WooCommerceController::class, 'index'])->name('index');
        Route::get('/auditoria', [WooCommerceController::class, 'auditoria'])->name('auditoria')->middleware('can:woocommerce.auditoria');
        Route::get('/auditoria/{id}/descargar', [WooCommerceController::class, 'descargarAuditoria'])->name('auditoria.descargar')->middleware('can:woocommerce.auditoria');
        Route::get('/alertas', [WooCommerceController::class, 'alertas'])->name('alertas');
        Route::get('/progreso/{id}', [WooCommerceController::class, 'progreso'])->name('progreso');
        Route::get('/sync/activo', [WooCommerceController::class, 'syncActivo'])->name('sync.activo');
        Route::get('/templates/{id}/descargar', [WooCommerceController::class, 'descargar'])->name('descargar');
        Route::put('/configuracion', [WooCommerceController::class, 'guardarConfiguracion'])->name('configuracion.update')->middleware('can:woocommerce.configurar');
        Route::post('/configuracion/probar-conexion', [WooCommerceController::class, 'probarConexionApi'])->name('configuracion.probar_conexion')->middleware('can:woocommerce.configurar');
        Route::post('/import-preview', [WooCommerceController::class, 'importPreview'])->name('import_preview')->middleware('can:woocommerce.sincronizar');
        Route::post('/previsualizar-mapeo', [WooCommerceController::class, 'previsualizarMapeo'])->name('previsualizar_mapeo')->middleware('can:woocommerce.sincronizar');
        Route::post('/previsualizar', [WooCommerceController::class, 'previsualizar'])->name('previsualizar')->middleware('can:woocommerce.sincronizar');
        Route::post('/procesar', [WooCommerceController::class, 'procesar'])->name('procesar')->middleware('can:woocommerce.sincronizar');
        Route::post('/sincronizar', [WooCommerceController::class, 'sincronizar'])->name('sincronizar')->middleware('can:woocommerce.sincronizar');
        Route::post('/fetch-precios', [WooCommerceController::class, 'fetchPrecios'])->name('fetch_precios')->middleware('can:woocommerce.sincronizar');
        Route::post('/catalogo/sincronizar', [WooCommerceController::class, 'sincronizarCatalogo'])->name('catalogo.sincronizar')->middleware('can:woocommerce.sincronizar');
        Route::post('/precios-locales', [WooCommerceController::class, 'actualizarPreciosLocales'])->name('precios_locales')->middleware('can:woocommerce.sincronizar');
        Route::get('/productos/{id}/consultar', [WooCommerceController::class, 'consultarPrecioIndividual'])->name('productos.consultar')->middleware('can:woocommerce.sincronizar');
        Route::put('/productos/{id}', [WooCommerceController::class, 'actualizarPrecioIndividual'])->name('productos.update')->middleware('can:woocommerce.sincronizar');
        Route::post('/emergencia/ocultar', [WooCommerceController::class, 'emergenciaOcultar'])->name('emergencia.ocultar')->middleware('can:woocommerce.emergencia');
        Route::delete('/sync/fantasmas', [WooCommerceController::class, 'descartarTodosFantasmas'])->name('sync.descartar_todos')->middleware('can:woocommerce.sincronizar');
        Route::delete('/sync/{id}/descartar', [WooCommerceController::class, 'descartarSync'])->name('sync.descartar')->middleware('can:woocommerce.sincronizar');
        Route::delete('/sync/{id}/cancelar', [WooCommerceController::class, 'cancelarSync'])->name('sync.cancelar')->middleware('can:woocommerce.sincronizar');
        Route::post('/sync/{id}/continuar', [WooCommerceController::class, 'continuarSync'])->name('sync.continuar')->middleware('can:woocommerce.sincronizar');
        Route::post('/sync/{id}/reanudar', [WooCommerceController::class, 'reanudarSync'])->name('sync.reanudar')->middleware('can:woocommerce.sincronizar');
        Route::delete('/templates/{id}', [WooCommerceController::class, 'eliminar'])->name('templates.eliminar')->middleware('can:woocommerce.sincronizar');
    });
