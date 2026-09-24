<?php

use App\Http\Controllers\PuntoVenta\Resguardos\BuscarProductoRegistroManualResguardoPdvController;
use App\Http\Controllers\PuntoVenta\Resguardos\ExportacionResguardoPdvController;
use App\Http\Controllers\PuntoVenta\Resguardos\AuditoriaResguardoPdvController;
use App\Http\Controllers\PuntoVenta\Resguardos\BandejaResguardoPdvController;
use App\Http\Controllers\PuntoVenta\Resguardos\ConfirmarDevolucionResguardoPdvController;
use App\Http\Controllers\PuntoVenta\Resguardos\CorregirResguardoPdvController;
use App\Http\Controllers\PuntoVenta\Resguardos\DetalleResguardoPdvController;
use App\Http\Controllers\PuntoVenta\Resguardos\EvidenciaResguardoPdvController;
use App\Http\Controllers\PuntoVenta\Resguardos\EntregaMultipleResguardoPdvController;
use App\Http\Controllers\PuntoVenta\Resguardos\EntregaResguardoPdvController;
use App\Http\Controllers\PuntoVenta\Resguardos\EtiquetasResguardoPdvController;
use App\Http\Controllers\PuntoVenta\Resguardos\FormularioEntregaMultipleResguardoPdvController;
use App\Http\Controllers\PuntoVenta\Resguardos\FormularioEntregaResguardoPdvController;
use App\Http\Controllers\PuntoVenta\Resguardos\ConfirmacionCustodiaResguardoPdvController;
use App\Http\Controllers\PuntoVenta\Resguardos\FormularioConfirmacionCustodiaResguardoPdvController;
use App\Http\Controllers\PuntoVenta\Resguardos\FormularioRecepcionFisicaResguardoPdvController;
use App\Http\Controllers\PuntoVenta\Resguardos\HistorialEntregadosResguardoPdvController;
use App\Http\Controllers\PuntoVenta\Resguardos\PasarARecepcionResguardoPdvController;
use App\Http\Controllers\PuntoVenta\Resguardos\RecepcionFisicaResguardoPdvController;
use App\Http\Controllers\PuntoVenta\Resguardos\RegistrarIncidenciaResguardoPdvController;
use App\Http\Controllers\PuntoVenta\Resguardos\RegistrarResguardoManualPdvController;
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

Route::middleware(['pdv.piso', 'pdv.permiso:'.PuntoVentaModulo::PERMISO_RESGUARDOS_VER_HISTORIAL_ENTREGAS])
    ->prefix('resguardos/entregados')
    ->name('resguardos.entregados.')
    ->group(function () {
        Route::get('/', [HistorialEntregadosResguardoPdvController::class, 'index'])->name('index');
        Route::get('/listado', [HistorialEntregadosResguardoPdvController::class, 'listado'])->name('listado');
    });

Route::middleware(['pdv.piso', 'pdv.permiso:'.PuntoVentaModulo::PERMISO_RESGUARDOS_VER])
    ->prefix('resguardos')
    ->name('resguardos.')
    ->group(function () {
        Route::get('/', [BandejaResguardoPdvController::class, 'index'])->name('index');
        Route::get('/listado', [BandejaResguardoPdvController::class, 'listado'])->name('listado');
        Route::get('/productos/buscar', BuscarProductoRegistroManualResguardoPdvController::class)
            ->middleware('pdv.permiso:'.PuntoVentaModulo::PERMISO_RESGUARDOS_RECIBIR_GERENTE)
            ->name('productos.buscar');
        Route::get('/etiquetas/resolver/{codigo}', [EtiquetasResguardoPdvController::class, 'resolver'])
            ->name('etiquetas.resolver');
    });

Route::middleware(['pdv.piso', 'pdv.resguardos.lectura'])
    ->prefix('resguardos')
    ->name('resguardos.')
    ->group(function () {
        Route::get('/{resguardo}/auditoria', AuditoriaResguardoPdvController::class)->name('auditoria');
        Route::get('/{resguardo}/evidencias/{evidencia}', [EvidenciaResguardoPdvController::class, 'show'])
            ->name('evidencias.show');
        Route::get('/{resguardo}/etiquetas', [EtiquetasResguardoPdvController::class, 'descargar'])
            ->name('etiquetas.descargar');
        Route::get('/{resguardo}', [DetalleResguardoPdvController::class, 'show'])->name('show');
    });

Route::middleware(['pdv.piso', 'pdv.permiso:'.PuntoVentaModulo::PERMISO_RESGUARDOS_RECIBIR_GERENTE])
    ->prefix('resguardos')
    ->name('resguardos.')
    ->group(function () {
        Route::post('/', RegistrarResguardoManualPdvController::class)->name('store');
        Route::get('/{resguardo}/recepcion', [FormularioRecepcionFisicaResguardoPdvController::class, 'show'])
            ->name('recepcion.create');
        Route::put('/{resguardo}/recepcion', RecepcionFisicaResguardoPdvController::class)->name('recepcion');
        Route::put('/{resguardo}/pasar-recepcion', PasarARecepcionResguardoPdvController::class)->name('pasar_recepcion');
    });

Route::middleware(['pdv.piso', 'pdv.permiso:'.PuntoVentaModulo::PERMISO_RESGUARDOS_CONFIRMAR_CUSTODIA])
    ->prefix('resguardos')
    ->name('resguardos.')
    ->group(function () {
        Route::get('/{resguardo}/custodia', [FormularioConfirmacionCustodiaResguardoPdvController::class, 'show'])
            ->name('custodia.create');
        Route::put('/{resguardo}/custodia', ConfirmacionCustodiaResguardoPdvController::class)->name('custodia');
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
