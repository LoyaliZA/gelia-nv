<?php

use App\Services\PuntoVenta\AlcancePdv;
use Illuminate\Support\Facades\Route;

Route::middleware(['pdv.permiso:'.AlcancePdv::PERMISO_ALCANCE_GLOBAL])
    ->prefix('reportes')
    ->name('reportes.')
    ->group(function () {
        Route::get('/', fn () => response()->noContent())->name('index');
    });
