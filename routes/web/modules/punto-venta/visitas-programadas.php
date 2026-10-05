<?php

use App\Http\Controllers\PuntoVenta\VisitasProgramadas\BandejaVisitasProgramadasPdvController;
use App\Services\PuntoVenta\PuntoVentaModulo;
use Illuminate\Support\Facades\Route;

Route::middleware(['pdv.piso', 'pdv.permiso:'.PuntoVentaModulo::PERMISO_VISITAS_PROGRAMADAS_VER])
    ->prefix('visitas-programadas')
    ->name('visitas_programadas.')
    ->group(function () {
        Route::get('/', [BandejaVisitasProgramadasPdvController::class, 'index'])->name('index');
        Route::get('/datos', [BandejaVisitasProgramadasPdvController::class, 'datos'])->name('datos');
    });

Route::middleware(['pdv.piso', 'pdv.permiso:'.PuntoVentaModulo::PERMISO_VISITAS_PROGRAMADAS_CONFIRMAR_LLEGADA])
    ->prefix('visitas-programadas')
    ->name('visitas_programadas.')
    ->group(function () {
        Route::post('/{visitaClienteProgramada}/llegada', [BandejaVisitasProgramadasPdvController::class, 'confirmarLlegada'])
            ->name('llegada');
    });
