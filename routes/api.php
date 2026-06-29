<?php

use App\Http\Controllers\Api\V1\AuthTokenController;
use App\Http\Controllers\Api\V1\ClienteExternoController;
use App\Http\Controllers\Api\V1\HealthController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', HealthController::class);

    Route::middleware(['require.json'])->group(function () {
        Route::post('/auth/token', [AuthTokenController::class, 'store']);
    });

    Route::middleware([
        'require.json',
        'auth:sanctum',
        'api.app',
        'log.api',
        'throttle:api-externa',
    ])->group(function () {
        Route::middleware('api.resource:clientes,read')->group(function () {
            Route::get('/clientes', [ClienteExternoController::class, 'index']);
            Route::get('/clientes/{numeroCliente}', [ClienteExternoController::class, 'show']);
        });

        Route::middleware('api.resource:clientes,write')->group(function () {
            Route::post('/clientes', [ClienteExternoController::class, 'store']);
            Route::put('/clientes/{numeroCliente}', [ClienteExternoController::class, 'update']);
        });
    });
});
