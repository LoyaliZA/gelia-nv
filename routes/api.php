<?php

use App\Http\Controllers\Api\V1\AuthTokenController;
use App\Http\Controllers\Api\V1\ClienteExternoController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\Mobile\MobileAuthController;
use App\Http\Controllers\Api\V1\Mobile\MobileClienteController;
use App\Http\Controllers\Api\V1\Mobile\MobileProfileController;
use App\Http\Controllers\Api\V1\Mobile\MobileSyncController;
use App\Http\Controllers\Api\V1\PasskeyController;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', HealthController::class);

    Route::middleware(['require.json'])->group(function () {
        Route::post('/auth/token', [AuthTokenController::class, 'store']);
        Route::post('/mobile/login', [MobileAuthController::class, 'login']);
    });

    $passkeySession = [
        EncryptCookies::class,
        AddQueuedCookiesToResponse::class,
        StartSession::class,
        'require.json',
        'webauthn.enabled',
    ];

    Route::prefix('passkeys')->middleware(array_merge($passkeySession, ['throttle:passkeys-login']))->group(function () {
        Route::post('/login/options', [PasskeyController::class, 'loginOptions']);
        Route::post('/login/verify', [PasskeyController::class, 'loginVerify']);
    });

    Route::prefix('passkeys')->middleware(array_merge($passkeySession, [
        'auth:sanctum',
        'throttle:20,1',
    ]))->group(function () {
        Route::post('/register/options', [PasskeyController::class, 'registerOptions']);
        Route::post('/register', [PasskeyController::class, 'register']);
        Route::get('/', [PasskeyController::class, 'index']);
        Route::patch('/{passkey}', [PasskeyController::class, 'update'])->where('passkey', '.*');
        Route::delete('/{passkey}', [PasskeyController::class, 'destroy'])->where('passkey', '.*');
    });

    Route::prefix('mobile')->middleware([
        'require.json',
        'auth:sanctum',
        'api.mobile',
        'throttle:api-mobile',
    ])->group(function () {
        Route::post('/logout', [MobileAuthController::class, 'logout']);
        Route::get('/me', [MobileAuthController::class, 'me']);
        Route::match(['patch', 'post'], '/profile', [MobileProfileController::class, 'update']);
        Route::get('/clientes', [MobileClienteController::class, 'index']);
        Route::get('/clientes/{numeroCliente}', [MobileClienteController::class, 'show']);

        Route::middleware('mobile.sync')->group(function () {
            Route::post('/sync/bootstrap', [MobileSyncController::class, 'storeBootstrap']);
            Route::get('/sync/bootstrap', [MobileSyncController::class, 'showBootstrap']);
            Route::post('/sync/bootstrap/complete', [MobileSyncController::class, 'completeBootstrap']);
            Route::get('/sync/changes', [MobileSyncController::class, 'changes']);
            Route::get('/sync/changes/head', [MobileSyncController::class, 'changesHead']);
        });
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
