<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\{AdminController, ClienteController};
use App\Http\Controllers\Admin\AuditoriaMontosClienteController;
use App\Http\Controllers\Clientes\Direcciones\{ClienteDireccionController, SolicitudDireccionRevisionController};

Route::middleware(['can:clientes.ver'])->group(function () {
    Route::get('/clientes', [AdminController::class, 'clientes'])->name('clientes');
    Route::post('/clientes/importar', [AdminController::class, 'importarClientes'])->name('clientes.importar');

    Route::get('/clientes/especiales/protegidos', [ClienteController::class, 'obtenerEspeciales'])->name('clientes.especiales');
    Route::post('/clientes/toggle-bloqueo', [ClienteController::class, 'toggleBloqueoLista'])->name('clientes.toggle_bloqueo');
    Route::post('/clientes/toggle-bloqueo-masivo', [ClienteController::class, 'toggleBloqueoMasivo'])->name('clientes.toggle_bloqueo_masivo');

    Route::get('/clientes/auditoria/datos', [AuditoriaMontosClienteController::class, 'datosAuditoria'])->name('clientes.auditoria.datos');
    Route::get('/clientes/importaciones/{importacion}/auditoria', [AuditoriaMontosClienteController::class, 'auditoriaImportacion'])->name('clientes.importaciones.auditoria');
    Route::get('/clientes/importaciones/{importacion}/archivo', [AuditoriaMontosClienteController::class, 'descargarArchivo'])->name('clientes.importaciones.archivo');
    Route::get('/clientes/{cliente}/historial', [AdminController::class, 'historialCliente'])->name('clientes.historial');
    Route::post('/clientes', [ClienteController::class, 'store'])->name('clientes.store');
    Route::put('/clientes/{cliente}', [ClienteController::class, 'update'])->name('clientes.update');

    Route::middleware(['can:clientes.direcciones.ver'])->group(function () {
        Route::get('/clientes/{cliente}/direcciones', [ClienteDireccionController::class, 'index'])
            ->name('clientes.direcciones.index');
    });
    Route::middleware(['can:clientes.direcciones.crear'])->group(function () {
        Route::post('/clientes/{cliente}/direcciones', [ClienteDireccionController::class, 'store'])
            ->name('clientes.direcciones.store');
    });
    Route::middleware(['can:clientes.direcciones.editar'])->group(function () {
        Route::put('/clientes/{cliente}/direcciones/{direccion}', [ClienteDireccionController::class, 'update'])
            ->name('clientes.direcciones.update');
        Route::post('/clientes/{cliente}/direcciones/{direccion}/principal', [ClienteDireccionController::class, 'marcarPrincipal'])
            ->name('clientes.direcciones.principal');
    });
    Route::middleware(['can:clientes.direcciones.desactivar'])->group(function () {
        Route::post('/clientes/{cliente}/direcciones/{direccion}/desactivar', [ClienteDireccionController::class, 'desactivar'])
            ->name('clientes.direcciones.desactivar');
    });
    Route::middleware(['can:clientes.direcciones.generar_enlace'])->group(function () {
        Route::post('/clientes/{cliente}/direcciones/enlace', [ClienteDireccionController::class, 'generarEnlace'])
            ->name('clientes.direcciones.enlace');
        Route::post('/clientes/{cliente}/enlaces/{enlace}/revocar', [ClienteDireccionController::class, 'revocarEnlace'])
            ->name('clientes.direcciones.enlace.revocar');
    });
});

Route::middleware(['can:clientes.direcciones.revisar_solicitudes'])->prefix('clientes/direcciones')->name('clientes.direcciones.')->group(function () {
    Route::get('/solicitudes', [SolicitudDireccionRevisionController::class, 'index'])->name('solicitudes.index');
    Route::get('/solicitudes/{solicitud}', [SolicitudDireccionRevisionController::class, 'show'])->name('solicitudes.show');
    Route::get('/solicitudes/{solicitud}/remision', [SolicitudDireccionRevisionController::class, 'descargarRemision'])->name('solicitudes.remision');
    Route::post('/solicitudes/{solicitud}/aprobar', [SolicitudDireccionRevisionController::class, 'aprobar'])->name('solicitudes.aprobar');
    Route::post('/solicitudes/{solicitud}/rechazar', [SolicitudDireccionRevisionController::class, 'rechazar'])->name('solicitudes.rechazar');
    Route::post('/solicitudes/{solicitud}/correccion', [SolicitudDireccionRevisionController::class, 'solicitarCorreccion'])->name('solicitudes.correccion');
    Route::post('/solicitudes/{solicitud}/vincular', [SolicitudDireccionRevisionController::class, 'vincularCliente'])->name('solicitudes.vincular');
});
