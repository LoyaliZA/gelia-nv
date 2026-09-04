<?php

use App\Http\Controllers\PuntoVenta\Operacion\AbrirJornadaPdvController;
use App\Http\Controllers\PuntoVenta\Operacion\ActualizarHorarioCierreSucursalPdvController;
use App\Http\Controllers\PuntoVenta\Operacion\AmpliarHorarioSucursalPdvController;
use App\Http\Controllers\PuntoVenta\Operacion\CerrarJornadaPdvController;
use App\Http\Controllers\PuntoVenta\Operacion\CierreManualSucursalPdvController;
use App\Http\Controllers\PuntoVenta\Operacion\EstadoOperativoPdvController;
use App\Http\Controllers\PuntoVenta\Operacion\FinalizarPausaPdvController;
use App\Http\Controllers\PuntoVenta\Operacion\IniciarPausaPdvController;
use App\Http\Controllers\PuntoVenta\Operacion\OperacionPdvController;
use App\Services\PuntoVenta\PuntoVentaModulo;
use Illuminate\Support\Facades\Route;

Route::middleware(['pdv.piso', 'pdv.permiso:'.PuntoVentaModulo::PERMISO_TURNOS_VER])
    ->prefix('operacion')
    ->name('operacion.')
    ->group(function () {
        Route::get('/', [OperacionPdvController::class, 'index'])->name('index');
        Route::get('/datos', [OperacionPdvController::class, 'datos'])->name('datos');
        Route::get('/estado', EstadoOperativoPdvController::class)->name('estado');
    });

Route::middleware(['pdv.piso'])
    ->prefix('operacion')
    ->name('operacion.')
    ->group(function () {
        Route::post('/jornada/abrir', AbrirJornadaPdvController::class)
            ->middleware('pdv.permiso:'.PuntoVentaModulo::PERMISO_OPERACION_JORNADA_ABRIR)
            ->name('jornada.abrir');

        Route::post('/jornada/cerrar', CerrarJornadaPdvController::class)
            ->middleware('pdv.permiso:'.PuntoVentaModulo::PERMISO_OPERACION_JORNADA_CERRAR)
            ->name('jornada.cerrar');

        Route::post('/jornada/cerrar-sucursal', CierreManualSucursalPdvController::class)
            ->middleware('pdv.permiso:'.PuntoVentaModulo::PERMISO_OPERACION_JORNADA_CERRAR_SUCURSAL)
            ->name('jornada.cerrar_sucursal');

        Route::post('/jornada/ampliar', AmpliarHorarioSucursalPdvController::class)
            ->middleware('pdv.permiso:'.PuntoVentaModulo::PERMISO_OPERACION_JORNADA_AMPLIAR)
            ->name('jornada.ampliar');

        Route::post('/pausa/iniciar', IniciarPausaPdvController::class)
            ->middleware('pdv.permiso:'.PuntoVentaModulo::PERMISO_OPERACION_PAUSA)
            ->name('pausa.iniciar');

        Route::post('/pausa/finalizar', FinalizarPausaPdvController::class)
            ->middleware('pdv.permiso:'.PuntoVentaModulo::PERMISO_OPERACION_PAUSA)
            ->name('pausa.finalizar');

        Route::put('/configuracion/horario-cierre', ActualizarHorarioCierreSucursalPdvController::class)
            ->middleware('pdv.permiso:'.PuntoVentaModulo::PERMISO_OPERACION_JORNADA_AMPLIAR)
            ->name('configuracion.horario_cierre');
    });
