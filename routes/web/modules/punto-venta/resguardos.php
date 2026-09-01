<?php

use App\Services\PuntoVenta\PuntoVentaModulo;
use Illuminate\Support\Facades\Route;

Route::middleware(['pdv.piso', 'pdv.permiso:'.PuntoVentaModulo::PERMISO_RESGUARDOS_VER])
    ->prefix('resguardos')
    ->name('resguardos.')
    ->group(function () {
        Route::get('/', fn () => response()->noContent())->name('index');
    });
