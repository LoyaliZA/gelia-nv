<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AromasListasController;
use App\Http\Controllers\FuncionesOperativas\AsistenciaController;
use App\Http\Controllers\FuncionesOperativas\AvisosController;
use App\Http\Controllers\FuncionesOperativas\GastosController;
use App\Http\Controllers\FuncionesOperativas\LimpiezaArchivosController;
use App\Http\Controllers\FuncionesOperativas\TransaccionesController;
use App\Http\Controllers\Herramientas\EjercicioEscalonamientoController;

Route::middleware(['can:funciones.asistencia'])->group(function () {
    Route::get('/funciones/asistencia', [AsistenciaController::class, 'index'])->name('funciones.asistencia.index');
    Route::post('/funciones/asistencia/procesar', [AsistenciaController::class, 'procesar'])->name('funciones.asistencia.procesar');
});

Route::middleware(['can:funciones.avisos'])->group(function () {
    Route::get('/funciones/avisos', [AvisosController::class, 'index'])->name('funciones.avisos.index');
    Route::post('/funciones/avisos/procesar', [AvisosController::class, 'procesar'])->name('funciones.avisos.procesar');
});

Route::middleware(['can:funciones.gastos'])->group(function () {
    Route::get('/funciones/gastos', [GastosController::class, 'index'])->name('funciones.gastos.index');
    Route::post('/funciones/gastos/procesar', [GastosController::class, 'procesar'])->name('funciones.gastos.procesar');
});

Route::middleware(['can:funciones.limpieza_archivos'])->group(function () {
    Route::get('/funciones/limpieza-archivos', [LimpiezaArchivosController::class, 'index'])->name('funciones.limpieza_archivos.index');
    Route::post('/funciones/limpieza-archivos/procesar', [LimpiezaArchivosController::class, 'procesar'])->name('funciones.limpieza_archivos.procesar');
});

Route::middleware(['can:funciones.transacciones'])->group(function () {
    Route::get('/funciones/transacciones', [TransaccionesController::class, 'index'])->name('funciones.transacciones.index');
    Route::post('/funciones/transacciones/procesar', [TransaccionesController::class, 'procesar'])->name('funciones.transacciones.procesar');
});

Route::middleware(['can:listados.ver'])->prefix('funciones/listados')->name('listados.')->group(function () {
    Route::get('/', [AromasListasController::class, 'index'])->name('index');
    Route::post('/generar', [AromasListasController::class, 'generar'])->name('generar');
    Route::post('/generar/confirmar', [AromasListasController::class, 'confirmarGeneracion'])->name('generar.confirmar');
    Route::get('/descargar-temporal', [AromasListasController::class, 'descargarTemporal'])->name('descargar_temporal');

    Route::get('/generados/{id}/descargar', [AromasListasController::class, 'descargarGenerado'])->name('generados.descargar')->middleware('can:listados.visualizar');
    Route::delete('/generados/{id}', [AromasListasController::class, 'eliminarGenerado'])->name('generados.eliminar')->middleware('can:listados.guardar_generado');
    Route::post('/destinatarios', [AromasListasController::class, 'guardarDestinatarios'])->name('destinatarios.guardar')->middleware('can:listados.enviar');

    Route::post('/guardar', [AromasListasController::class, 'guardarLista'])->name('guardar')->middleware('can:listados.crear');
    Route::post('/{id}/actualizar', [AromasListasController::class, 'actualizarLista'])->name('actualizar')->middleware('can:listados.editar');
    Route::delete('/{id}', [AromasListasController::class, 'eliminarLista'])->name('eliminar')->middleware('can:listados.eliminar');

    Route::get('/configuracion', [AromasListasController::class, 'obtenerConfiguracion'])->name('config.obtener')->middleware('can:listados.configurar_porcentajes');
    Route::post('/configuracion', [AromasListasController::class, 'guardarConfiguracion'])->name('config.guardar')->middleware('can:listados.configurar_porcentajes');
});

Route::middleware(['can:ejercicio_escalonamiento.ver'])->prefix('funciones/ejercicio-escalonamiento')->name('ejercicio_escalonamiento.')->group(function () {
    Route::get('/', [EjercicioEscalonamientoController::class, 'index'])->name('index');
});
