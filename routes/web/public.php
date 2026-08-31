<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Activos\ActivoController;
use App\Http\Controllers\Clientes\Direcciones\SolicitudDireccionPublicaController;
use App\Http\Controllers\ControlPedidos\PedidoBmaEvidenciaPublicaController;
use App\Http\Controllers\ControlPedidos\PedidoBmaEvidenciaTiendaPublicaController;
use App\Http\Controllers\Facturas\DatosFiscalesPublicosController;
use App\Http\Controllers\TiendanubeWebhookController;
use App\Http\Middleware\HardenSolicitudDireccionPublica;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::middleware('throttle:60,1')->group(function () {
    Route::get('/activos/consulta/{token}', [ActivoController::class, 'consultaPublica'])->name('activos.consulta.publica');
    Route::get('/activos/consulta/{token}/qr.svg', [ActivoController::class, 'consultaQr'])->name('activos.consulta.qr');
});

Route::middleware(['throttle:30,1', HardenSolicitudDireccionPublica::class])->group(function () {
    Route::get('/direcciones-envio/confirmacion/{folio}', [SolicitudDireccionPublicaController::class, 'confirmacion'])
        ->name('direcciones.publicas.confirmacion');
    Route::get('/direcciones-envio/{codigo}', [SolicitudDireccionPublicaController::class, 'show'])
        ->where('codigo', '[A-Za-z0-9]{6,64}')
        ->name('direcciones.publicas.show');
    Route::get('/direcciones-envio', [SolicitudDireccionPublicaController::class, 'show'])
        ->name('direcciones.publicas.form');
    Route::post('/direcciones-envio', [SolicitudDireccionPublicaController::class, 'store'])
        ->name('direcciones.publicas.store');

    Route::get('/datos-fiscales/confirmacion/{folio}', [DatosFiscalesPublicosController::class, 'confirmacion'])
        ->name('datos_fiscales.publicas.confirmacion');
    Route::get('/datos-fiscales/{codigo}', [DatosFiscalesPublicosController::class, 'show'])
        ->where('codigo', '[A-Za-z0-9]{6,64}')
        ->name('datos_fiscales.publicas.show');
    Route::get('/datos-fiscales', [DatosFiscalesPublicosController::class, 'show'])
        ->name('datos_fiscales.publicas.form');
    Route::post('/datos-fiscales', [DatosFiscalesPublicosController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('datos_fiscales.publicas.store');

    Route::get('/cedis-evidencia/{codigo}', [PedidoBmaEvidenciaPublicaController::class, 'show'])
        ->where('codigo', '[A-Za-z0-9]{6,64}')
        ->name('cedis_evidencia.publicas.show');
    Route::get('/cedis-evidencia/{codigo}/estado', [PedidoBmaEvidenciaPublicaController::class, 'estado'])
        ->where('codigo', '[A-Za-z0-9]{6,64}')
        ->middleware('throttle:60,1')
        ->name('cedis_evidencia.publicas.estado');
    Route::post('/cedis-evidencia/{codigo}/fotos', [PedidoBmaEvidenciaPublicaController::class, 'subir'])
        ->where('codigo', '[A-Za-z0-9]{6,64}')
        ->middleware('throttle:30,1')
        ->name('cedis_evidencia.publicas.fotos');
    Route::get('/cedis-evidencia/{codigo}/fotos/{foto}', [PedidoBmaEvidenciaPublicaController::class, 'foto'])
        ->where('codigo', '[A-Za-z0-9]{6,64}')
        ->name('cedis_evidencia.publicas.foto');

    Route::get('/tienda-evidencia/{codigo}', [PedidoBmaEvidenciaTiendaPublicaController::class, 'show'])
        ->where('codigo', '[A-Za-z0-9]{6,64}')
        ->name('tienda_evidencia.publicas.show');
    Route::get('/tienda-evidencia/{codigo}/estado', [PedidoBmaEvidenciaTiendaPublicaController::class, 'estado'])
        ->where('codigo', '[A-Za-z0-9]{6,64}')
        ->middleware('throttle:60,1')
        ->name('tienda_evidencia.publicas.estado');
    Route::post('/tienda-evidencia/{codigo}/fotos', [PedidoBmaEvidenciaTiendaPublicaController::class, 'subir'])
        ->where('codigo', '[A-Za-z0-9]{6,64}')
        ->middleware('throttle:30,1')
        ->name('tienda_evidencia.publicas.fotos');
    Route::get('/tienda-evidencia/{codigo}/fotos/{foto}', [PedidoBmaEvidenciaTiendaPublicaController::class, 'foto'])
        ->where('codigo', '[A-Za-z0-9]{6,64}')
        ->name('tienda_evidencia.publicas.foto');
});

Route::post('/webhooks/tiendanube', TiendanubeWebhookController::class)
    ->middleware('throttle:120,1')
    ->name('webhooks.tiendanube');
