<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ControlPedidos\DireccionesAuxiliarController;
use App\Http\Controllers\Clientes\Direcciones\ImportarDireccionesController;

// Submódulo Direcciones (Auxiliar) — sin acceso al módulo Clientes
Route::middleware(['can:clientes.direcciones.ver'])
    ->prefix('control-pedidos/direcciones')
    ->name('control_pedidos.direcciones.')
    ->group(function () {
        Route::get('/', [DireccionesAuxiliarController::class, 'index'])->name('index');
        Route::get('/listado', [DireccionesAuxiliarController::class, 'listado'])->name('listado');
        Route::get('/buscar-cliente', [DireccionesAuxiliarController::class, 'buscarCliente'])->name('buscar_cliente');
        Route::get('/cliente/{cliente}', [DireccionesAuxiliarController::class, 'cliente'])->name('cliente');

        Route::middleware(['can:clientes.direcciones.crear'])->group(function () {
            Route::get('/plantilla-importacion', [ImportarDireccionesController::class, 'plantilla'])->name('plantilla_importacion');
            Route::post('/importar', [ImportarDireccionesController::class, 'importar'])->name('importar');
            Route::post('/cliente/{cliente}/direcciones', [DireccionesAuxiliarController::class, 'store'])->name('store');
        });
        Route::middleware(['can:clientes.direcciones.editar'])->group(function () {
            Route::put('/cliente/{cliente}/direcciones/{direccion}', [DireccionesAuxiliarController::class, 'update'])->name('update');
            Route::post('/cliente/{cliente}/direcciones/{direccion}/principal', [DireccionesAuxiliarController::class, 'marcarPrincipal'])->name('principal');
        });
        Route::middleware(['can:clientes.direcciones.desactivar'])->group(function () {
            Route::post('/cliente/{cliente}/direcciones/{direccion}/desactivar', [DireccionesAuxiliarController::class, 'desactivar'])->name('desactivar');
        });
        Route::middleware(['can:clientes.direcciones.generar_enlace'])->group(function () {
            Route::post('/cliente/{cliente}/enlace', [DireccionesAuxiliarController::class, 'generarEnlace'])->name('enlace');
            Route::post('/cliente/{cliente}/enlace/{enlace}/revocar', [DireccionesAuxiliarController::class, 'revocarEnlace'])->name('enlace.revocar');
        });
    });

Route::middleware(['can:clientes.direcciones.revisar_solicitudes'])
    ->prefix('control-pedidos/direcciones')
    ->name('control_pedidos.direcciones.')
    ->group(function () {
        Route::get('/solicitudes', [DireccionesAuxiliarController::class, 'solicitudesIndex'])->name('solicitudes.index');
        Route::get('/solicitudes/{solicitud}', [DireccionesAuxiliarController::class, 'solicitudesShow'])->name('solicitudes.show');
        Route::get('/solicitudes/{solicitud}/remision', [DireccionesAuxiliarController::class, 'solicitudesRemision'])->name('solicitudes.remision');
        Route::post('/solicitudes/{solicitud}/aprobar', [DireccionesAuxiliarController::class, 'solicitudesAprobar'])->name('solicitudes.aprobar');
        Route::post('/solicitudes/{solicitud}/rechazar', [DireccionesAuxiliarController::class, 'solicitudesRechazar'])->name('solicitudes.rechazar');
        Route::post('/solicitudes/{solicitud}/correccion', [DireccionesAuxiliarController::class, 'solicitudesCorreccion'])->name('solicitudes.correccion');
        Route::post('/solicitudes/{solicitud}/vincular', [DireccionesAuxiliarController::class, 'solicitudesVincular'])->name('solicitudes.vincular');
    });
