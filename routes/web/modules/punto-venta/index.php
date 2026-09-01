<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['pdv.modulo'])
    ->prefix('punto-venta')
    ->name('punto_venta.')
    ->group(function () {
        require __DIR__.'/resguardos.php';
        require __DIR__.'/reportes.php';
    });
