<?php

use App\Http\Controllers\PuntoVenta\Resguardos\ExportacionResguardoPdvController;
use App\Http\Controllers\PuntoVenta\Resguardos\AuditoriaResguardoPdvController;
use App\Http\Controllers\PuntoVenta\Resguardos\BandejaResguardoPdvController;
use App\Http\Controllers\PuntoVenta\Resguardos\ConfirmarDevolucionResguardoPdvController;
use App\Http\Controllers\PuntoVenta\Resguardos\CorregirResguardoPdvController;
use App\Http\Controllers\PuntoVenta\Resguardos\DetalleResguardoPdvController;
use App\Http\Controllers\PuntoVenta\Resguardos\EntregaMultipleResguardoPdvController;
use App\Http\Controllers\PuntoVenta\Resguardos\EntregaResguardoPdvController;
use App\Http\Controllers\PuntoVenta\Resguardos\EtiquetasResguardoPdvController;
use App\Http\Controllers\PuntoVenta\Resguardos\FormularioEntregaMultipleResguardoPdvController;
use App\Http\Controllers\PuntoVenta\Resguardos\FormularioEntregaResguardoPdvController;
use App\Http\Controllers\PuntoVenta\Resguardos\FormularioRecepcionFisicaResguardoPdvController;
use App\Http\Controllers\PuntoVenta\Resguardos\RecepcionFisicaResguardoPdvController;
use App\Http\Controllers\PuntoVenta\Resguardos\RegistrarIncidenciaResguardoPdvController;
use App\Http\Controllers\PuntoVenta\Resguardos\ReponerVencidoResguardoPdvController;
use App\Http\Controllers\PuntoVenta\Resguardos\ResolverIncidenciaResguardoPdvController;
use App\Services\PuntoVenta\AlcancePdv;
use App\Services\PuntoVenta\PuntoVentaModulo;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'pdv.permiso:'.AlcancePdv::PERMISO_ALCANCE_GLOBAL,
    'pdv.permiso:'.PuntoVentaModulo::PERMISO_REPORTES_EXPORTAR,
])
    ->prefix('resguardos/exportaciones')
    ->name('resguardos.exportaciones.')
    ->group(function () {
        Route::post('/', [ExportacionResguardoPdvController::class, 'store'])->name('store');
        Route::get('/{exportacion}', [ExportacionResguardoPdvController::class, 'show'])->name('show');
        Route::get('/{exportacion}/descargar', [ExportacionResguardoPdvController::class, 'descargar'])
            ->name('descargar');
    });

Route::middleware(['pdv.piso', 'pdv.permiso:'.PuntoVentaModulo::PERMISO_RESGUARDOS_ENTREGAR])
    ->prefix('resguardos')
    ->name('resguardos.')
    ->group(function () {
        Route::get('/entregas-multiples', [FormularioEntregaMultipleResguardoPdvController::class, 'show'])
            ->name('entregas_multiples.create');
        Route::post('/entregas-multiples', EntregaMultipleResguardoPdvController::class)
            ->name('entregas_multiples.store');
    });

Route::middleware(['pdv.piso', 'pdv.permiso:'.PuntoVentaModulo::PERMISO_RESGUARDOS_VER])
    ->prefix('resguardos')
    ->name('resguardos.')
    ->group(function () {
        Route::get('/', [BandejaResguardoPdvController::class, 'index'])->name('index');
        Route::get('/listado', [BandejaResguardoPdvController::class, 'listado'])->name('listado');
        Route::get('/etiquetas/resolver/{codigo}', [EtiquetasResguardoPdvController::class, 'resolver'])
            ->name('etiquetas.resolver');
        Route::get('/{resguardo}/auditoria', AuditoriaResguardoPdvController::class)->name('auditoria');
        Route::get('/{resguardo}', [DetalleResguardoPdvController::class, 'show'])->name('show');
        Route::get('/{resguardo}/etiquetas', [EtiquetasResguardoPdvController::class, 'descargar'])
            ->name('etiquetas.descargar');
    });

Route::middleware(['pdv.piso', 'pdv.permiso:'.PuntoVentaModulo::PERMISO_RESGUARDOS_RECIBIR])
    ->prefix('resguardos')
    ->name('resguardos.')
    ->group(function () {
        Route::get('/{resguardo}/recepcion', [FormularioRecepcionFisicaResguardoPdvController::class, 'show'])
            ->name('recepcion.create');
        Route::put('/{resguardo}/recepcion', RecepcionFisicaResguardoPdvController::class)->name('recepcion');
    });

Route::middleware(['pdv.piso', 'pdv.permiso:'.PuntoVentaModulo::PERMISO_RESGUARDOS_ENTREGAR])
    ->prefix('resguardos')
    ->name('resguardos.')
    ->group(function () {
        Route::get('/{resguardo}/entrega', [FormularioEntregaResguardoPdvController::class, 'show'])
            ->name('entrega.create');
        Route::put('/{resguardo}/entrega', EntregaResguardoPdvController::class)->name('entrega');
    });

Route::middleware(['pdv.piso'])
    ->prefix('resguardos')
    ->name('resguardos.')
    ->group(function () {
        Route::post('/{resguardo}/incidencias', RegistrarIncidenciaResguardoPdvController::class)
            ->name('incidencias.store');
        Route::put('/{resguardo}/incidencias/{incidenciaResguardo}/resolver', ResolverIncidenciaResguardoPdvController::class)
            ->name('incidencias.resolver');
        Route::put('/{resguardo}/devolucion', ConfirmarDevolucionResguardoPdvController::class)
            ->name('devolucion');
        Route::put('/{resguardo}/correccion', CorregirResguardoPdvController::class)
            ->name('correccion');
        Route::put('/{resguardo}/reponer-vencido', ReponerVencidoResguardoPdvController::class)
            ->name('reponer_vencido');
    });
