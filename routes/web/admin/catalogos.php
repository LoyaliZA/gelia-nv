<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\{AdminController, CatalogoController, CatalogoProductoEspecificacionesController};
use App\Http\Controllers\Activos\{CategoriaActivoController, TipoActivoController};

Route::middleware(['can:catalogos.gestionar'])->group(function () {
    Route::get('/catalogos', [AdminController::class, 'catalogos'])->name('catalogos');

    Route::redirect('/catalogo-maestro', '/almacenes/inventarios')->name('catalogo-maestro.index');
    Route::redirect('/catalogo-maestro/import-preview', '/almacenes/inventarios');
    Route::redirect('/catalogo-maestro/import-process', '/almacenes/inventarios');

    Route::prefix('catalogos')->name('catalogos.')->group(function () {
        Route::post('/departamentos', [CatalogoController::class, 'storeDepartamento'])->name('departamentos.store');
        Route::put('/departamentos/{id}', [CatalogoController::class, 'updateDepartamento'])->name('departamentos.update');
        Route::delete('/departamentos/{id}', [CatalogoController::class, 'destroyDepartamento'])->name('departamentos.destroy');

        Route::post('/areas', [CatalogoController::class, 'storeArea'])->name('areas.store');
        Route::put('/areas/{id}', [CatalogoController::class, 'updateArea'])->name('areas.update');
        Route::delete('/areas/{id}', [CatalogoController::class, 'destroyArea'])->name('areas.destroy');

        Route::post('/procesos', [CatalogoController::class, 'storeProceso'])->name('procesos.store');
        Route::put('/procesos/{id}', [CatalogoController::class, 'updateProceso'])->name('procesos.update');
        Route::delete('/procesos/{id}', [CatalogoController::class, 'destroyProceso'])->name('procesos.destroy');

        Route::post('/listas', [CatalogoController::class, 'storeLista'])->name('listas.store');
        Route::put('/listas/{id}', [CatalogoController::class, 'updateLista'])->name('listas.update');
        Route::delete('/listas/{id}', [CatalogoController::class, 'destroyLista'])->name('listas.destroy');

        Route::post('/estados', [CatalogoController::class, 'storeEstado'])->name('estados.store');
        Route::put('/estados/{id}', [CatalogoController::class, 'updateEstado'])->name('estados.update');
        Route::delete('/estados/{id}', [CatalogoController::class, 'destroyEstado'])->name('estados.destroy');

        Route::post('/tipo-clientes', [CatalogoController::class, 'storeTipoCliente'])->name('tipo_clientes.store');
        Route::put('/tipo-clientes/{id}', [CatalogoController::class, 'updateTipoCliente'])->name('tipo_clientes.update');
        Route::delete('/tipo-clientes/{id}', [CatalogoController::class, 'destroyTipoCliente'])->name('tipo_clientes.destroy');

        Route::put('/zonas-entrega/{id}', [CatalogoController::class, 'updateZonaEntrega'])->name('zonas_entrega.update')->middleware('can:entregas.configurar_zonas');
        Route::delete('/zonas-entrega/{id}', [CatalogoController::class, 'destroyZonaEntrega'])->name('zonas_entrega.destroy')->middleware('can:entregas.configurar_zonas');

        Route::post('/horarios-entrega', [CatalogoController::class, 'storeHorarioEntrega'])->name('horarios_entrega.store')->middleware('can:entregas.configurar_zonas');
        Route::put('/horarios-entrega/{id}', [CatalogoController::class, 'updateHorarioEntrega'])->name('horarios_entrega.update')->middleware('can:entregas.configurar_zonas');
        Route::delete('/horarios-entrega/{id}', [CatalogoController::class, 'destroyHorarioEntrega'])->name('horarios_entrega.destroy')->middleware('can:entregas.configurar_zonas');

        Route::post('/horarios-traspaso', [CatalogoController::class, 'storeHorarioTraspaso'])->name('horarios_traspaso.store');
        Route::put('/horarios-traspaso/{id}', [CatalogoController::class, 'updateHorarioTraspaso'])->name('horarios_traspaso.update');
        Route::delete('/horarios-traspaso/{id}', [CatalogoController::class, 'destroyHorarioTraspaso'])->name('horarios_traspaso.destroy');

        Route::post('/porcentajes-escalonamiento', [CatalogoController::class, 'storePorcentajeEscalonamiento'])->name('porcentajes_escalonamiento.store');
        Route::put('/porcentajes-escalonamiento/{id}', [CatalogoController::class, 'updatePorcentajeEscalonamiento'])->name('porcentajes_escalonamiento.update');
        Route::delete('/porcentajes-escalonamiento/{id}', [CatalogoController::class, 'destroyPorcentajeEscalonamiento'])->name('porcentajes_escalonamiento.destroy');

        Route::post('/porcentajes-listado', [CatalogoController::class, 'storePorcentajeListado'])->name('porcentajes_listado.store');
        Route::put('/porcentajes-listado/{id}', [CatalogoController::class, 'updatePorcentajeListado'])->name('porcentajes_listado.update');
        Route::delete('/porcentajes-listado/{id}', [CatalogoController::class, 'destroyPorcentajeListado'])->name('porcentajes_listado.destroy');

        Route::post('/bancos', [CatalogoController::class, 'storeBanco'])->name('bancos.store');
        Route::put('/bancos/{id}', [CatalogoController::class, 'updateBanco'])->name('bancos.update');
        Route::delete('/bancos/{id}', [CatalogoController::class, 'destroyBanco'])->name('bancos.destroy');

        Route::post('/regimenes-fiscales', [CatalogoController::class, 'storeRegimenFiscal'])->name('regimenes_fiscales.store');
        Route::put('/regimenes-fiscales/{id}', [CatalogoController::class, 'updateRegimenFiscal'])->name('regimenes_fiscales.update');
        Route::delete('/regimenes-fiscales/{id}', [CatalogoController::class, 'destroyRegimenFiscal'])->name('regimenes_fiscales.destroy');

        Route::post('/usos-cfdi', [CatalogoController::class, 'storeUsoCfdi'])->name('usos_cfdi.store');
        Route::put('/usos-cfdi/{id}', [CatalogoController::class, 'updateUsoCfdi'])->name('usos_cfdi.update');
        Route::delete('/usos-cfdi/{id}', [CatalogoController::class, 'destroyUsoCfdi'])->name('usos_cfdi.destroy');

        Route::middleware(['can:control_pedidos.configurar_catalogos'])->group(function () {
            Route::post('/estatus-pedidos', [CatalogoController::class, 'storeEstatusPedido'])->name('estatus_pedidos.store');
            Route::put('/estatus-pedidos/{id}', [CatalogoController::class, 'updateEstatusPedido'])->name('estatus_pedidos.update');
            Route::delete('/estatus-pedidos/{id}', [CatalogoController::class, 'destroyEstatusPedido'])->name('estatus_pedidos.destroy');

            Route::post('/paqueterias-pedido', [CatalogoController::class, 'storePaqueteriaPedido'])->name('paqueterias_pedido.store');
            Route::put('/paqueterias-pedido/{id}', [CatalogoController::class, 'updatePaqueteriaPedido'])->name('paqueterias_pedido.update');
            Route::delete('/paqueterias-pedido/{id}', [CatalogoController::class, 'destroyPaqueteriaPedido'])->name('paqueterias_pedido.destroy');

            Route::post('/tipos-caja-pedido', [CatalogoController::class, 'storeTipoCajaPedido'])->name('tipos_caja_pedido.store');
            Route::put('/tipos-caja-pedido/{id}', [CatalogoController::class, 'updateTipoCajaPedido'])->name('tipos_caja_pedido.update');
            Route::delete('/tipos-caja-pedido/{id}', [CatalogoController::class, 'destroyTipoCajaPedido'])->name('tipos_caja_pedido.destroy');

            Route::post('/tipos-guia-pedido', [CatalogoController::class, 'storeTipoGuiaPedido'])->name('tipos_guia_pedido.store');
            Route::put('/tipos-guia-pedido/{id}', [CatalogoController::class, 'updateTipoGuiaPedido'])->name('tipos_guia_pedido.update');
            Route::delete('/tipos-guia-pedido/{id}', [CatalogoController::class, 'destroyTipoGuiaPedido'])->name('tipos_guia_pedido.destroy');

            Route::post('/zonas-pedido', [CatalogoController::class, 'storeZonaPedido'])->name('zonas_pedido.store');
            Route::put('/zonas-pedido/{id}', [CatalogoController::class, 'updateZonaPedido'])->name('zonas_pedido.update');
            Route::delete('/zonas-pedido/{id}', [CatalogoController::class, 'destroyZonaPedido'])->name('zonas_pedido.destroy');

            Route::post('/reexpedicion-pedido', [CatalogoController::class, 'storeReexpedicionPedido'])->name('reexpedicion_pedido.store');
            Route::put('/reexpedicion-pedido/{id}', [CatalogoController::class, 'updateReexpedicionPedido'])->name('reexpedicion_pedido.update');
            Route::delete('/reexpedicion-pedido/{id}', [CatalogoController::class, 'destroyReexpedicionPedido'])->name('reexpedicion_pedido.destroy');
            Route::get('/reexpedicion-pedido/plantilla', [CatalogoController::class, 'plantillaReexpedicionPedido'])->name('reexpedicion_pedido.plantilla');
            Route::post('/reexpedicion-pedido/importar', [CatalogoController::class, 'importarReexpedicionPedido'])->name('reexpedicion_pedido.importar');

            Route::post('/envios-tienda', [CatalogoController::class, 'storeEnvioTienda'])->name('envios_tienda.store');
            Route::put('/envios-tienda/{id}', [CatalogoController::class, 'updateEnvioTienda'])->name('envios_tienda.update');
            Route::delete('/envios-tienda/{id}', [CatalogoController::class, 'destroyEnvioTienda'])->name('envios_tienda.destroy');

            Route::post('/origenes-pedido', [CatalogoController::class, 'storeOrigenPedido'])->name('origenes_pedido.store');
            Route::put('/origenes-pedido/{id}', [CatalogoController::class, 'updateOrigenPedido'])->name('origenes_pedido.update');
            Route::delete('/origenes-pedido/{id}', [CatalogoController::class, 'destroyOrigenPedido'])->name('origenes_pedido.destroy');
        });

        Route::get('/sucursales/plantilla-importacion', [CatalogoController::class, 'descargarPlantillaImportacion'])->defaults('tipo', 'sucursales')->name('sucursales.plantilla_importacion');
        Route::post('/sucursales/importar', [CatalogoController::class, 'importarCatalogoAlmacen'])->defaults('tipo', 'sucursales')->name('sucursales.importar');
        Route::post('/sucursales', [CatalogoController::class, 'storeSucursal'])->name('sucursales.store');
        Route::put('/sucursales/{id}', [CatalogoController::class, 'updateSucursal'])->name('sucursales.update');
        Route::delete('/sucursales/{id}', [CatalogoController::class, 'destroySucursal'])->name('sucursales.destroy');

        Route::get('/tipos-almacen/plantilla-importacion', [CatalogoController::class, 'descargarPlantillaImportacion'])->defaults('tipo', 'tipos_almacen')->name('tipos_almacen.plantilla_importacion');
        Route::post('/tipos-almacen/importar', [CatalogoController::class, 'importarCatalogoAlmacen'])->defaults('tipo', 'tipos_almacen')->name('tipos_almacen.importar');
        Route::post('/tipos-almacen', [CatalogoController::class, 'storeTipoAlmacen'])->name('tipos_almacen.store');
        Route::put('/tipos-almacen/{id}', [CatalogoController::class, 'updateTipoAlmacen'])->name('tipos_almacen.update');
        Route::delete('/tipos-almacen/{id}', [CatalogoController::class, 'destroyTipoAlmacen'])->name('tipos_almacen.destroy');

        Route::get('/marcas-producto/plantilla-importacion', [CatalogoController::class, 'descargarPlantillaImportacion'])->defaults('tipo', 'marcas_producto')->name('marcas_producto.plantilla_importacion');
        Route::post('/marcas-producto/importar', [CatalogoController::class, 'importarCatalogoAlmacen'])->defaults('tipo', 'marcas_producto')->name('marcas_producto.importar');
        Route::post('/marcas-producto', [CatalogoController::class, 'storeMarcaProducto'])->name('marcas_producto.store');
        Route::put('/marcas-producto/{id}', [CatalogoController::class, 'updateMarcaProducto'])->name('marcas_producto.update');
        Route::delete('/marcas-producto/{id}', [CatalogoController::class, 'destroyMarcaProducto'])->name('marcas_producto.destroy');

        Route::get('/almacenes/plantilla-importacion', [CatalogoController::class, 'descargarPlantillaImportacion'])->defaults('tipo', 'almacenes')->name('almacenes.plantilla_importacion');
        Route::post('/almacenes/importar', [CatalogoController::class, 'importarCatalogoAlmacen'])->defaults('tipo', 'almacenes')->name('almacenes.importar');
        Route::post('/almacenes', [CatalogoController::class, 'storeAlmacen'])->name('almacenes.store');
        Route::put('/almacenes/{id}', [CatalogoController::class, 'updateAlmacen'])->name('almacenes.update');
        Route::delete('/almacenes/{id}', [CatalogoController::class, 'destroyAlmacen'])->name('almacenes.destroy');

        Route::get('/categorias-producto/plantilla-importacion', [CatalogoController::class, 'descargarPlantillaImportacion'])->defaults('tipo', 'categorias_producto')->name('categorias_producto.plantilla_importacion');
        Route::post('/categorias-producto/importar', [CatalogoController::class, 'importarCatalogoAlmacen'])->defaults('tipo', 'categorias_producto')->name('categorias_producto.importar');
        Route::post('/categorias-producto', [CatalogoController::class, 'storeCategoriaProducto'])->name('categorias_producto.store');
        Route::put('/categorias-producto/{id}', [CatalogoController::class, 'updateCategoriaProducto'])->name('categorias_producto.update');
        Route::delete('/categorias-producto/{id}', [CatalogoController::class, 'destroyCategoriaProducto'])->name('categorias_producto.destroy');

        Route::post('/atributos-producto', [CatalogoProductoEspecificacionesController::class, 'storeAtributo'])->name('atributos_producto.store');
        Route::put('/atributos-producto/{id}', [CatalogoProductoEspecificacionesController::class, 'updateAtributo'])->name('atributos_producto.update');
        Route::delete('/atributos-producto/{id}', [CatalogoProductoEspecificacionesController::class, 'destroyAtributo'])->name('atributos_producto.destroy');
        Route::post('/unidades-medida', [CatalogoProductoEspecificacionesController::class, 'storeUnidad'])->name('unidades_medida.store');
        Route::put('/unidades-medida/{id}', [CatalogoProductoEspecificacionesController::class, 'updateUnidad'])->name('unidades_medida.update');
        Route::delete('/unidades-medida/{id}', [CatalogoProductoEspecificacionesController::class, 'destroyUnidad'])->name('unidades_medida.destroy');
        Route::post('/notas-olfativas', [CatalogoProductoEspecificacionesController::class, 'storeNotaOlfativa'])->name('notas_olfativas.store');
        Route::put('/notas-olfativas/{id}', [CatalogoProductoEspecificacionesController::class, 'updateNotaOlfativa'])->name('notas_olfativas.update');
        Route::delete('/notas-olfativas/{id}', [CatalogoProductoEspecificacionesController::class, 'destroyNotaOlfativa'])->name('notas_olfativas.destroy');
        Route::put('/extensiones-producto/{id}', [CatalogoProductoEspecificacionesController::class, 'updateExtensionProducto'])->name('extensiones_producto.update');

        Route::post('/tipos-activo', [TipoActivoController::class, 'store'])->name('tipos_activo.store')->middleware('can:activos.configurar_tipos');
        Route::put('/tipos-activo/{id}', [TipoActivoController::class, 'update'])->name('tipos_activo.update')->middleware('can:activos.configurar_tipos');
        Route::delete('/tipos-activo/{id}', [TipoActivoController::class, 'destroy'])->name('tipos_activo.destroy')->middleware('can:activos.configurar_tipos');

        Route::post('/categorias-activo', [CategoriaActivoController::class, 'store'])->name('categorias_activo.store')->middleware('can:activos.configurar_tipos');
        Route::put('/categorias-activo/{id}', [CategoriaActivoController::class, 'update'])->name('categorias_activo.update')->middleware('can:activos.configurar_tipos');
        Route::delete('/categorias-activo/{id}', [CategoriaActivoController::class, 'destroy'])->name('categorias_activo.destroy')->middleware('can:activos.configurar_tipos');
    });
});
