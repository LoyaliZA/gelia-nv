<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Activos\ActivoController;

Route::middleware(['can:activos.ver'])->prefix('activos')->name('activos.')->group(function () {
    Route::get('/', [ActivoController::class, 'index'])->name('index');
    Route::get('/resolver-codigo', [ActivoController::class, 'resolverCodigo'])->name('resolver_codigo');
    Route::get('/exportar', [ActivoController::class, 'exportar'])->middleware('can:activos.exportar')->name('exportar');
    Route::get('/etiquetas', [ActivoController::class, 'etiquetas'])->middleware('can:activos.exportar')->name('etiquetas');
    Route::get('/etiquetas/contar', [ActivoController::class, 'etiquetasContar'])->middleware('can:activos.exportar')->name('etiquetas.contar');
    Route::get('/etiquetas/vista-previa', [ActivoController::class, 'etiquetasVistaPrevia'])->middleware('can:activos.exportar')->name('etiquetas.vista_previa');
    Route::get('/etiquetas/descargar', [ActivoController::class, 'etiquetasDescargar'])->middleware('can:activos.exportar')->name('etiquetas.descargar');
    Route::get('/alertas', [ActivoController::class, 'alertas'])->name('alertas');
    Route::get('/{activo}/qr.svg', [ActivoController::class, 'qr'])->name('qr');
    Route::get('/{activo}/qr.png', [ActivoController::class, 'qrPng'])->name('qr_png');
    Route::get('/{activo}', [ActivoController::class, 'show'])->name('show');

    Route::post('/', [ActivoController::class, 'store'])->middleware('can:activos.crear')->name('store');
    Route::put('/{activo}', [ActivoController::class, 'update'])->middleware('can:activos.editar')->name('update');
    Route::post('/{activo}/asignar', [ActivoController::class, 'asignar'])->middleware('can:activos.asignar')->name('asignar');
    Route::post('/{activo}/devolver', [ActivoController::class, 'devolver'])->middleware('can:activos.asignar')->name('devolver');
    Route::post('/asignaciones/{asignacion}/firmar', [ActivoController::class, 'firmar'])->name('asignaciones.firmar');
    Route::get('/asignaciones/{asignacion}/responsiva', [ActivoController::class, 'responsiva'])->name('asignaciones.responsiva');
    Route::get('/asignaciones/{asignacion}/responsiva/vista-previa', [ActivoController::class, 'responsivaVistaPrevia'])->name('asignaciones.responsiva_vista_previa');
    Route::post('/asignaciones/firmar-conjunto', [ActivoController::class, 'firmarConjunto'])->name('asignaciones.firmar_conjunto');
    Route::get('/usuarios/{usuario}/responsiva-conjunta', [ActivoController::class, 'responsivaConjunta'])->name('usuarios.responsiva_conjunta');
    Route::get('/usuarios/{usuario}/responsiva-conjunta/vista-previa', [ActivoController::class, 'responsivaConjuntaVistaPrevia'])->name('usuarios.responsiva_conjunta_vista_previa');
    Route::post('/configuracion', [ActivoController::class, 'guardarConfiguracion'])->middleware('can:activos.configurar_tipos')->name('configuracion.guardar');
    Route::post('/{activo}/transferir', [ActivoController::class, 'transferir'])->middleware('can:activos.transferir')->name('transferir');
    Route::post('/{activo}/estado', [ActivoController::class, 'cambiarEstado'])->middleware('can:activos.cambiar_estado')->name('estado');
    Route::post('/{activo}/mantenimiento', [ActivoController::class, 'programarMantenimiento'])->middleware('can:activos.cambiar_estado')->name('mantenimiento');
    Route::post('/{activo}/mantenimiento/{mantenimiento}/completar', [ActivoController::class, 'completarMantenimiento'])->middleware('can:activos.cambiar_estado')->name('mantenimiento.completar');
    Route::post('/{activo}/fotos', [ActivoController::class, 'subirFotos'])->middleware('can:activos.editar')->name('fotos.store');
    Route::delete('/{activo}/fotos/{foto}', [ActivoController::class, 'eliminarFoto'])->middleware('can:activos.editar')->name('fotos.destroy');
});
