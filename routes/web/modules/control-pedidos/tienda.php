<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ControlPedidos\PedidoBmaTiendaController;

Route::middleware(['can:control_pedidos.tienda.ver'])
    ->prefix('control-pedidos/tienda')
    ->name('control_pedidos.tienda.')
    ->group(function () {
        Route::get('/', [PedidoBmaTiendaController::class, 'index'])->name('index');
        Route::get('/listado', [PedidoBmaTiendaController::class, 'listado'])->name('listado');
        Route::get('/tareas/{tarea}', [PedidoBmaTiendaController::class, 'show'])->name('show');
        Route::post('/tareas/{tarea}/tomar', [PedidoBmaTiendaController::class, 'tomar'])->middleware('can:control_pedidos.tienda.tomar')->name('tomar');
        Route::post('/tareas/{tarea}/responder', [PedidoBmaTiendaController::class, 'responder'])->middleware('can:control_pedidos.tienda.responder')->name('responder');
        Route::post('/tareas/{tarea}/confirmar-salida', [PedidoBmaTiendaController::class, 'confirmarSalida'])->middleware('can:control_pedidos.tienda.trasladar')->name('confirmar_salida');
        Route::post('/tareas/{tarea}/regenerar-traspaso', [PedidoBmaTiendaController::class, 'regenerarTraspaso'])->middleware('can:control_pedidos.tienda.trasladar')->name('regenerar_traspaso');
        Route::post('/tareas/{tarea}/caratula/generar', [PedidoBmaTiendaController::class, 'generarCaratula'])->middleware('can:control_pedidos.tienda.generar_caratula')->name('caratula.generar');
        Route::post('/tareas/{tarea}/caratula/regenerar', [PedidoBmaTiendaController::class, 'regenerarCaratula'])->middleware('can:control_pedidos.tienda.regenerar_caratula')->name('caratula.regenerar');
        Route::post('/tareas/{tarea}/caratula/confirmar-colocacion', [PedidoBmaTiendaController::class, 'confirmarCaratula'])->middleware('can:control_pedidos.tienda.confirmar_caratula')->name('caratula.confirmar');
        Route::get('/tareas/{tarea}/caratula/{caratula}/pdf', [PedidoBmaTiendaController::class, 'descargarCaratula'])->middleware('can:control_pedidos.tienda.imprimir_caratula')->name('caratula.pdf');
        Route::post('/tareas/{tarea}/documento-municipal', [PedidoBmaTiendaController::class, 'subirDocumentoMunicipal'])->middleware('can:control_pedidos.tienda.cargar_identificacion')->name('documento_municipal.store');
        Route::get('/tareas/{tarea}/evidencia/{tareaDocumento}/descargar', [PedidoBmaTiendaController::class, 'descargarDocumentoTarea'])->name('evidencia.descargar');
        Route::post('/tareas/{tarea}/reportar-incidencia', [PedidoBmaTiendaController::class, 'reportarIncidencia'])->middleware('can:control_pedidos.tienda.reportar_error')->name('reportar_incidencia');
        Route::post('/tareas/{tarea}/liberar', [PedidoBmaTiendaController::class, 'liberar'])->middleware('can:control_pedidos.tienda.liberar')->name('liberar');
        Route::post('/tareas/{tarea}/evidencia', [PedidoBmaTiendaController::class, 'subirEvidencia'])->middleware('can:control_pedidos.tienda.evidencias')->name('evidencia.store');
        Route::delete('/tareas/{tarea}/evidencia/{tareaDocumento}', [PedidoBmaTiendaController::class, 'eliminarEvidencia'])->middleware('can:control_pedidos.tienda.evidencias')->name('evidencia.destroy');
        Route::post('/tareas/{tarea}/sesion-evidencia', [PedidoBmaTiendaController::class, 'crearSesionEvidencia'])->middleware('can:control_pedidos.tienda.evidencias')->name('sesion_evidencia.store');
        Route::get('/tareas/{tarea}/sesion-evidencia', [PedidoBmaTiendaController::class, 'mostrarSesionEvidencia'])->middleware('can:control_pedidos.tienda.evidencias')->name('sesion_evidencia.show');
        Route::post('/tareas/{tarea}/sesion-evidencia/cancelar', [PedidoBmaTiendaController::class, 'cancelarSesionEvidencia'])->middleware('can:control_pedidos.tienda.evidencias')->name('sesion_evidencia.cancelar');
        Route::post('/tareas/{tarea}/sesion-evidencia/promover', [PedidoBmaTiendaController::class, 'promoverSesionEvidencia'])->middleware('can:control_pedidos.tienda.evidencias')->name('sesion_evidencia.promover');
        Route::post('/tareas/{tarea}/apartado/separar', [PedidoBmaTiendaController::class, 'separarApartado'])->middleware('can:control_pedidos.tienda.apartado.separar')->name('apartado.separar');
        Route::post('/tareas/{tarea}/apartado/devolucion', [PedidoBmaTiendaController::class, 'solicitarDevolucionApartado'])->middleware('can:control_pedidos.tienda.apartado.separar')->name('apartado.devolucion');
        Route::post('/tareas/{tarea}/apartado/confirmar-devolucion', [PedidoBmaTiendaController::class, 'confirmarDevolucionApartado'])->middleware('can:control_pedidos.tienda.apartado.confirmar_devolucion')->name('apartado.confirmar_devolucion');
        Route::post('/tareas/{tarea}/apartado/prorroga', [PedidoBmaTiendaController::class, 'prorrogarApartado'])->middleware('can:control_pedidos.tienda.apartado.aprobar_prorroga')->name('apartado.prorroga');
        Route::post('/tareas/{tarea}/salida/pago', [PedidoBmaTiendaController::class, 'confirmarPagoSalida'])->middleware('can:control_pedidos.salida.validar_pago')->name('salida.pago');
        Route::post('/tareas/{tarea}/salida/autorizar', [PedidoBmaTiendaController::class, 'autorizarSalida'])->middleware('can:control_pedidos.salida.autorizar')->name('salida.autorizar');
        Route::post('/tareas/{tarea}/salida/entrega', [PedidoBmaTiendaController::class, 'confirmarEntregaRecogeHoy'])->middleware('can:control_pedidos.salida.confirmar_entrega')->name('salida.entrega');
        Route::post('/tareas/{tarea}/municipio/evidencia-empaque', [PedidoBmaTiendaController::class, 'subirEvidenciaEmpaqueMunicipal'])->middleware('can:control_pedidos.tienda.empacar_municipio')->name('municipio.evidencia_empaque');
        Route::post('/tareas/{tarea}/municipio/empacar', [PedidoBmaTiendaController::class, 'confirmarEmpaqueMunicipal'])->middleware('can:control_pedidos.tienda.empacar_municipio')->name('municipio.empacar');
        Route::post('/tareas/{tarea}/municipio/despachar', [PedidoBmaTiendaController::class, 'confirmarDespachoMunicipal'])->middleware('can:control_pedidos.tienda.despachar_municipio')->name('municipio.despachar');
    });

Route::middleware(['can:control_pedidos.preparacion.corregir'])
    ->prefix('control-pedidos/tareas-preparacion')
    ->name('control_pedidos.preparacion.')
    ->group(function () {
        Route::post('/{tarea}/corregir', [PedidoBmaTiendaController::class, 'corregir'])->name('corregir');
    });

Route::prefix('control-pedidos/tareas-preparacion')
    ->name('control_pedidos.preparacion.')
    ->group(function () {
        Route::post('/{tarea}/cambiar-modalidad', [PedidoBmaTiendaController::class, 'cambiarModalidad'])->name('cambiar_modalidad');
    });
