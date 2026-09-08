<?php

use App\Http\Controllers\PuntoVenta\Operacion\AbrirJornadaPdvController;
use App\Http\Controllers\PuntoVenta\Operacion\ActualizarHorarioCierreSucursalPdvController;
use App\Http\Controllers\PuntoVenta\Operacion\AmpliarHorarioSucursalPdvController;
use App\Http\Controllers\PuntoVenta\Operacion\CerrarJornadaPdvController;
use App\Http\Controllers\PuntoVenta\Operacion\CierreManualSucursalPdvController;
use App\Http\Controllers\PuntoVenta\Operacion\EstadoOperativoPdvController;
use App\Http\Controllers\PuntoVenta\Operacion\FinalizarPausaPdvController;
use App\Http\Controllers\PuntoVenta\Operacion\GestionEquipoPdvController;
use App\Http\Controllers\PuntoVenta\Operacion\GestionVendedoresPdvController;
use App\Http\Controllers\PuntoVenta\Operacion\IniciarPausaPdvController;
use App\Http\Controllers\PuntoVenta\Operacion\ReabrirSucursalPdvController;
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

Route::middleware(['pdv.piso', 'pdv.permiso:'.PuntoVentaModulo::PERMISO_OPERACION_EQUIPO_VER])
    ->prefix('operacion')
    ->name('operacion.')
    ->group(function () {
        Route::get('/vendedores', [GestionVendedoresPdvController::class, 'index'])->name('vendedores.index');
        Route::get('/vendedores/datos', [GestionVendedoresPdvController::class, 'datos'])->name('vendedores.datos');
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

        Route::post('/jornada/reabrir-sucursal', ReabrirSucursalPdvController::class)
            ->middleware('pdv.permiso:'.PuntoVentaModulo::PERMISO_OPERACION_JORNADA_CERRAR_SUCURSAL)
            ->name('jornada.reabrir_sucursal');

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

        Route::post('/equipo/{user}/activar', [GestionEquipoPdvController::class, 'activar'])
            ->middleware('pdv.permiso:'.PuntoVentaModulo::PERMISO_OPERACION_EQUIPO_GESTIONAR)
            ->name('equipo.activar');

        Route::post('/equipo/{user}/no-llego', [GestionEquipoPdvController::class, 'marcarNoLlego'])
            ->middleware('pdv.permiso:'.PuntoVentaModulo::PERMISO_OPERACION_EQUIPO_GESTIONAR)
            ->name('equipo.no_llego');

        Route::post('/equipo/{user}/desactivar', [GestionEquipoPdvController::class, 'desactivar'])
            ->middleware('pdv.permiso:'.PuntoVentaModulo::PERMISO_OPERACION_EQUIPO_GESTIONAR)
            ->name('equipo.desactivar');

        Route::post('/equipo/{user}/pausa/iniciar', [GestionEquipoPdvController::class, 'iniciarPausa'])
            ->middleware('pdv.permiso:'.PuntoVentaModulo::PERMISO_OPERACION_EQUIPO_GESTIONAR)
            ->name('equipo.pausa.iniciar');

        Route::post('/equipo/{user}/pausa/finalizar', [GestionEquipoPdvController::class, 'finalizarPausa'])
            ->middleware('pdv.permiso:'.PuntoVentaModulo::PERMISO_OPERACION_EQUIPO_GESTIONAR)
            ->name('equipo.pausa.finalizar');

        Route::post('/equipo/{user}/cerrar-jornada', [GestionEquipoPdvController::class, 'cerrarJornada'])
            ->middleware('pdv.permiso:'.PuntoVentaModulo::PERMISO_OPERACION_EQUIPO_GESTIONAR)
            ->name('equipo.cerrar_jornada');

        Route::post('/equipo/{user}/reactivar', [GestionEquipoPdvController::class, 'reactivar'])
            ->middleware('pdv.permiso:'.PuntoVentaModulo::PERMISO_OPERACION_EQUIPO_GESTIONAR)
            ->name('equipo.reactivar');

        Route::post('/equipo/{user}/cancelar-cierre-pendiente', [GestionEquipoPdvController::class, 'cancelarCierrePendiente'])
            ->middleware('pdv.permiso:'.PuntoVentaModulo::PERMISO_OPERACION_EQUIPO_GESTIONAR)
            ->name('equipo.cancelar_cierre_pendiente');
    });
