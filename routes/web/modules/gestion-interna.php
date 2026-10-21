<?php

use App\Http\Controllers\GestionInterna\ComisionesEmpaqueController;
use App\Http\Controllers\GestionInterna\DirectorioController;
use App\Http\Controllers\GestionInterna\ProductoController as GestionInternaProductoController;
use App\Http\Controllers\GestionInterna\ProductoImagenController;
use Illuminate\Support\Facades\Route;

Route::prefix('gestion-interna')->name('gestion_interna.')->group(function () {
    // Lookup catálogo (CEDIS pesaje + almacén); fuera del grupo "ver productos" para no bloquear CEDIS.
    Route::get('/productos/buscar', [GestionInternaProductoController::class, 'buscar'])
        ->middleware('role_or_permission:gestion_interna.productos.ver|almacenes.productos.ver|almacenes.inventarios.ver|almacenes.costos.ver|catalogos.gestionar|reportes.ventas.ver|control_pedidos.cedis|control_pedidos.tienda.apartado.separar|control_pedidos.tienda.responder|control_pedidos.preparacion.solicitar')
        ->name('productos.buscar');

    Route::middleware(['role_or_permission:gestion_interna.productos.ver|catalogos.gestionar|almacenes.productos.ver'])->prefix('productos')->name('productos.')->group(function () {
        Route::get('/', [GestionInternaProductoController::class, 'index'])->name('index');
        $gestionarImagenes = 'role_or_permission:gestion_interna.productos.gestionar|catalogos.gestionar';
        Route::post('/imagenes/lotes', [ProductoImagenController::class, 'analizar'])->middleware($gestionarImagenes)->name('imagenes.lotes.analizar');
        Route::get('/imagenes/lotes/{lote}', [ProductoImagenController::class, 'mostrar'])->middleware($gestionarImagenes)->name('imagenes.lotes.mostrar');
        Route::post('/imagenes/lotes/{lote}/confirmar', [ProductoImagenController::class, 'confirmar'])->middleware($gestionarImagenes)->name('imagenes.lotes.confirmar');
        Route::post('/{producto}/imagenes', [ProductoImagenController::class, 'store'])->middleware($gestionarImagenes)->name('imagenes.store');
        Route::post('/{producto}/imagenes/orden', [ProductoImagenController::class, 'orden'])->middleware($gestionarImagenes)->name('imagenes.orden');
        Route::post('/{producto}/imagenes/{imagen}/mover', [ProductoImagenController::class, 'mover'])->middleware($gestionarImagenes)->name('imagenes.mover');
        Route::get('/{producto}/imagenes/{imagen}/archivo', [ProductoImagenController::class, 'archivo'])->name('imagenes.archivo');
        Route::post('/{producto}/imagenes/{imagen}/reemplazar', [ProductoImagenController::class, 'reemplazar'])->middleware($gestionarImagenes)->name('imagenes.reemplazar');
        Route::post('/{producto}/imagenes/{imagen}/principal', [ProductoImagenController::class, 'principal'])->middleware($gestionarImagenes)->name('imagenes.principal');
        Route::post('/{producto}/imagenes/{imagen}/miniatura', [ProductoImagenController::class, 'miniatura'])->middleware($gestionarImagenes)->name('imagenes.miniatura');
        Route::delete('/{producto}/imagenes/{imagen}', [ProductoImagenController::class, 'destroy'])->middleware($gestionarImagenes)->name('imagenes.destruir');
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

    Route::middleware(['can:gestion_interna.comisiones_empaque.ver'])->prefix('comisiones-empaque')->name('comisiones_empaque.')->group(function () {
        Route::get('/', [ComisionesEmpaqueController::class, 'index'])->name('index');
        Route::post('/sincronizar', [ComisionesEmpaqueController::class, 'sincronizar'])->name('sincronizar');
        Route::post('/manual', [ComisionesEmpaqueController::class, 'manual'])->name('manual');
        Route::post('/carga/revisar', [ComisionesEmpaqueController::class, 'revisarCarga'])->name('carga.revisar');
        Route::post('/carga', [ComisionesEmpaqueController::class, 'guardarCarga'])->name('carga.store');
        Route::get('/pedido', [ComisionesEmpaqueController::class, 'pedido'])->name('pedido');
        Route::put('/configuracion/alcance', [ComisionesEmpaqueController::class, 'guardarAlcance'])->name('alcance');
        Route::get('/unidades/{unidad}/pedido', [ComisionesEmpaqueController::class, 'pedidoInformativo'])->name('pedido.informativo');
        Route::post('/unidades/{unidad}/revisar', [ComisionesEmpaqueController::class, 'revisar'])->name('revisar');
        Route::post('/unidades/{unidad}/clasificar', [ComisionesEmpaqueController::class, 'clasificar'])->name('clasificar');
        Route::post('/unidades/{unidad}/decision', [ComisionesEmpaqueController::class, 'decision'])->name('decision');
        Route::post('/unidades/{unidad}/resolver', [ComisionesEmpaqueController::class, 'resolver'])->name('resolver');
        Route::put('/equipo', [ComisionesEmpaqueController::class, 'guardarEquipo'])->name('equipo');
        Route::post('/periodos', [ComisionesEmpaqueController::class, 'guardarPeriodo'])->name('periodos.store');
        Route::put('/periodos/{periodo}', [ComisionesEmpaqueController::class, 'guardarPeriodo'])->name('periodos.update');
        Route::post('/cortes/borrador', [ComisionesEmpaqueController::class, 'borrador'])->name('borrador');
        Route::post('/cortes/confirmar', [ComisionesEmpaqueController::class, 'confirmar'])->name('confirmar');
        Route::post('/cortes/{corte}/descartar', [ComisionesEmpaqueController::class, 'descartar'])->name('descartar');
        Route::post('/pagos', [ComisionesEmpaqueController::class, 'pagar'])->name('pagar');
        Route::post('/pagos/{pago}/revertir', [ComisionesEmpaqueController::class, 'revertirPago'])->name('pagos.revertir');
        Route::post('/ajustes', [ComisionesEmpaqueController::class, 'ajustar'])->name('ajustar');
        Route::put('/configuracion/fecha-inicio', [ComisionesEmpaqueController::class, 'fechaInicio'])->name('fecha_inicio');
        Route::post('/configuracion/areas', [ComisionesEmpaqueController::class, 'guardarArea'])->name('areas.store');
        Route::put('/configuracion/areas/{area}', [ComisionesEmpaqueController::class, 'actualizarArea'])->name('areas.update');
        Route::post('/configuracion/participantes', [ComisionesEmpaqueController::class, 'guardarParticipante'])->name('participantes.store');
        Route::post('/configuracion/tarifas', [ComisionesEmpaqueController::class, 'guardarTarifa'])->name('tarifas.store');
        Route::post('/configuracion/repartos', [ComisionesEmpaqueController::class, 'guardarReparto'])->name('repartos.store');
        Route::get('/configuracion/usuarios', [ComisionesEmpaqueController::class, 'buscarUsuarios'])->name('usuarios');
        Route::get('/exportar/acumulado', [ComisionesEmpaqueController::class, 'exportarAcumulado'])->name('exportar.acumulado');
        Route::get('/exportar/tipos', [ComisionesEmpaqueController::class, 'exportarTipos'])->name('exportar.tipos');
        Route::get('/exportar/cortes/{corte}', [ComisionesEmpaqueController::class, 'exportarCorte'])->name('exportar.corte');
        Route::get('/exportar/cortes/{corte}/reparto', [ComisionesEmpaqueController::class, 'exportarReparto'])->name('exportar.reparto');
        Route::get('/exportar/pagos', [ComisionesEmpaqueController::class, 'exportarPagos'])->name('exportar.pagos');
    });
});
