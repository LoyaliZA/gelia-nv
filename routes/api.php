<?php

use App\Http\Controllers\Api\V1\AuthTokenController;
use App\Http\Controllers\Api\V1\ClienteExternoController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\Mobile\MobileAuthController;
use App\Http\Controllers\Api\V1\Mobile\MobileClienteController;
use App\Http\Controllers\Api\V1\Mobile\MobileProfileController;
use App\Http\Controllers\Api\V1\Mobile\MobileSyncController;
use App\Http\Controllers\Api\V1\Mobile\PuntoVenta\ArchivoResguardoPdvMobileController;
use App\Http\Controllers\Api\V1\Mobile\PuntoVenta\ContextoPuntoVentaMobileController;
use App\Http\Controllers\Api\V1\Mobile\PuntoVenta\ResguardoPdvMobileController;
use App\Http\Controllers\Api\V1\Mobile\Activos\ActivoMobileController;
use App\Http\Controllers\Api\V1\Mobile\Almacenes\AlmacenMobileController;
use App\Http\Controllers\Api\V1\Mobile\Comercial\VisitaProgramadaMobileController;
use App\Http\Controllers\Api\V1\Mobile\Mensajeria\MensajeriaMobileController;
use App\Http\Controllers\Api\V1\Mobile\Pedidos\PedidoMobileController;
use App\Http\Controllers\Api\V1\Mobile\PuntoVenta\TurnoPdvMobileController;
use App\Http\Controllers\Api\V1\Mobile\Rh\RhMobileController;
use App\Http\Controllers\Api\V1\Mobile\Cancelaciones\CancelacionCotizacionMobileController;
use App\Http\Controllers\Api\V1\Mobile\Facturas\FacturaMobileController;
use App\Http\Controllers\Api\V1\Mobile\Solicitudes\SolicitudMobileController;
use App\Http\Controllers\Api\V1\Mobile\Traspasos\TraspasoMobileController;
use App\Http\Controllers\Api\V1\Mobile\PuntoVenta\VisitaProgramadaPdvMobileController;
use App\Http\Controllers\Api\V1\PasskeyController;
use App\Services\PuntoVenta\PuntoVentaModulo;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', HealthController::class);

    Route::middleware(['require.json'])->group(function () {
        Route::post('/auth/token', [AuthTokenController::class, 'store']);
        Route::post('/mobile/login', [MobileAuthController::class, 'login'])
            ->middleware('throttle:mobile-login');
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

        Route::prefix('comercial')->group(function () {
            Route::get('/visitas-programadas', [VisitaProgramadaMobileController::class, 'index']);
            Route::get('/visitas-programadas/historial', [VisitaProgramadaMobileController::class, 'historial']);
            Route::post('/visitas-programadas', [VisitaProgramadaMobileController::class, 'store']);
            Route::get('/visitas-programadas/catalogos', [VisitaProgramadaMobileController::class, 'catalogos']);
        });

        Route::get('/pedidos', [PedidoMobileController::class, 'index']);
        Route::get('/pedidos/{pedidoBma}', [PedidoMobileController::class, 'show']);

        Route::prefix('rh')->group(function () {
            Route::get('/colaboradores', [RhMobileController::class, 'colaboradores']);
            Route::get('/colaboradores/{colaborador}', [RhMobileController::class, 'colaborador']);
            Route::get('/horas-extra', [RhMobileController::class, 'horasExtra']);
            Route::get('/salidas-personales', [RhMobileController::class, 'salidas']);
            Route::get('/incidencias', [RhMobileController::class, 'incidencias']);
            Route::get('/prestamos', [RhMobileController::class, 'prestamos']);
            Route::get('/banco-tiempo', [RhMobileController::class, 'bancoTiempo']);
        });

        Route::get('/almacenes', [AlmacenMobileController::class, 'index']);
        Route::get('/almacenes/{almacen}', [AlmacenMobileController::class, 'show']);
        Route::get('/inventarios', [AlmacenMobileController::class, 'inventarios']);
        Route::get('/inventarios/{inventario}', [AlmacenMobileController::class, 'inventario'])->whereNumber('inventario');

        Route::get('/activos/resolver-codigo', [ActivoMobileController::class, 'resolverCodigo']);
        Route::get('/activos/asignaciones/pendientes-firma', [ActivoMobileController::class, 'pendientesFirma']);
        Route::post('/activos/asignaciones/{asignacion}/firmar', [ActivoMobileController::class, 'firmar']);
        Route::get('/activos', [ActivoMobileController::class, 'index']);
        Route::get('/activos/{activo}', [ActivoMobileController::class, 'show']);

        Route::get('/solicitudes', [SolicitudMobileController::class, 'index']);
        Route::post('/solicitudes/{solicitud}/solicitar-cancelacion', [SolicitudMobileController::class, 'solicitarCancelacion']);
        Route::get('/solicitudes/{solicitud}', [SolicitudMobileController::class, 'show']);

        Route::get('/facturas', [FacturaMobileController::class, 'index']);
        Route::get('/facturas/{solicitudFactura}', [FacturaMobileController::class, 'show']);

        Route::get('/cancelaciones-cotizaciones', [CancelacionCotizacionMobileController::class, 'index']);
        Route::post('/cancelaciones-cotizaciones/{solicitud}/solicitar-cancelacion', [CancelacionCotizacionMobileController::class, 'solicitarCancelacion']);
        Route::get('/cancelaciones-cotizaciones/{solicitud}', [CancelacionCotizacionMobileController::class, 'show']);

        Route::get('/traspasos', [TraspasoMobileController::class, 'index']);
        Route::get('/traspasos/{traspaso}', [TraspasoMobileController::class, 'show']);

        Route::prefix('mensajeria')->group(function () {
            Route::get('/conversaciones', [MensajeriaMobileController::class, 'conversaciones']);
            Route::post('/conversaciones', [MensajeriaMobileController::class, 'storeConversacion']);
            Route::get('/usuarios', [MensajeriaMobileController::class, 'usuarios']);
            Route::get('/conversaciones/{conversacion}/mensajes', [MensajeriaMobileController::class, 'mensajes']);
            Route::post('/conversaciones/{conversacion}/mensajes', [MensajeriaMobileController::class, 'enviar']);
            Route::put('/conversaciones/{conversacion}/leer', [MensajeriaMobileController::class, 'leer']);
        });

        Route::middleware('mobile.sync')->group(function () {
            Route::post('/sync/bootstrap', [MobileSyncController::class, 'storeBootstrap']);
            Route::get('/sync/bootstrap', [MobileSyncController::class, 'showBootstrap']);
            Route::post('/sync/bootstrap/complete', [MobileSyncController::class, 'completeBootstrap']);
            Route::get('/sync/changes', [MobileSyncController::class, 'changes']);
            Route::get('/sync/changes/head', [MobileSyncController::class, 'changesHead']);
        });

        Route::prefix('punto-venta')->middleware('mobile.pdv')->group(function () {
            Route::get('/contexto', [ContextoPuntoVentaMobileController::class, 'show']);
            Route::put('/sucursal-activa', [ContextoPuntoVentaMobileController::class, 'establecerSucursal']);

            Route::middleware('mobile.pdv:operacion')->group(function () {
                Route::get('/resguardos', [ResguardoPdvMobileController::class, 'index'])
                    ->middleware('pdv.permiso:'.PuntoVentaModulo::PERMISO_RESGUARDOS_VER);
                Route::get('/resguardos/entregados', [ResguardoPdvMobileController::class, 'historialEntregados'])
                    ->middleware('pdv.permiso:'.PuntoVentaModulo::PERMISO_RESGUARDOS_VER_HISTORIAL_ENTREGAS);
                Route::get('/resguardos/etiquetas/resolver/{codigo}', [ResguardoPdvMobileController::class, 'resolverEtiqueta'])
                    ->middleware('pdv.permiso:'.PuntoVentaModulo::PERMISO_RESGUARDOS_VER);
                Route::get('/resguardos/productos/buscar', [ResguardoPdvMobileController::class, 'buscarProductos'])
                    ->middleware('pdv.permiso:'.PuntoVentaModulo::PERMISO_RESGUARDOS_REGISTRAR_MANUAL);
                Route::post('/resguardos', [ResguardoPdvMobileController::class, 'store'])
                    ->middleware('pdv.permiso:'.PuntoVentaModulo::PERMISO_RESGUARDOS_REGISTRAR_MANUAL);
                Route::post('/resguardos/{resguardo}/registro-manual/evidencia', [ResguardoPdvMobileController::class, 'adjuntarEvidenciaManual'])
                    ->middleware('pdv.permiso:'.PuntoVentaModulo::PERMISO_RESGUARDOS_REGISTRAR_MANUAL);
                Route::get('/resguardos/{resguardo}', [ResguardoPdvMobileController::class, 'show'])
                    ->middleware('pdv.permiso:'.PuntoVentaModulo::PERMISO_RESGUARDOS_VER);
                Route::get('/resguardos/{resguardo}/archivos/evidencias/{evidencia}', [ArchivoResguardoPdvMobileController::class, 'evidencia'])
                    ->middleware('pdv.permiso:'.PuntoVentaModulo::PERMISO_RESGUARDOS_VER);
                Route::get('/resguardos/{resguardo}/archivos/documentos/{documento}', [ArchivoResguardoPdvMobileController::class, 'documento'])
                    ->middleware('pdv.permiso:'.PuntoVentaModulo::PERMISO_RESGUARDOS_VER);
                Route::put('/resguardos/{resguardo}/recepcion', [ResguardoPdvMobileController::class, 'recepcion'])
                    ->middleware('pdv.permiso:'.PuntoVentaModulo::PERMISO_RESGUARDOS_CONFIRMAR_LLEGADA);
                Route::put('/resguardos/{resguardo}/pasar-recepcion', [ResguardoPdvMobileController::class, 'pasarRecepcion'])
                    ->middleware('pdv.permiso:'.PuntoVentaModulo::PERMISO_RESGUARDOS_ENVIAR_A_CUSTODIA);
                Route::get('/resguardos/{resguardo}/custodia', [ResguardoPdvMobileController::class, 'formularioCustodia'])
                    ->middleware('pdv.permiso:'.PuntoVentaModulo::PERMISO_RESGUARDOS_CONFIRMAR_CUSTODIA);
                Route::put('/resguardos/{resguardo}/custodia', [ResguardoPdvMobileController::class, 'custodia'])
                    ->middleware('pdv.permiso:'.PuntoVentaModulo::PERMISO_RESGUARDOS_CONFIRMAR_CUSTODIA);
                Route::post('/resguardos/{resguardo}/incidencias', [ResguardoPdvMobileController::class, 'registrarIncidencia']);
                Route::put('/resguardos/{resguardo}/incidencias/{incidenciaResguardo}/resolver', [ResguardoPdvMobileController::class, 'resolverIncidencia']);
                Route::put('/resguardos/{resguardo}/devolucion', [ResguardoPdvMobileController::class, 'devolucion'])
                    ->middleware('pdv.permiso:'.PuntoVentaModulo::PERMISO_RESGUARDOS_CONFIRMAR_DEVOLUCION);
                Route::put('/resguardos/{resguardo}/reponer-vencido', [ResguardoPdvMobileController::class, 'reponerVencido'])
                    ->middleware('pdv.permiso:'.PuntoVentaModulo::PERMISO_RESGUARDOS_REPONER_VENCIDO);

                Route::get('/turnos/recepcion', [TurnoPdvMobileController::class, 'recepcion'])
                    ->middleware('pdv.permiso:'.PuntoVentaModulo::PERMISO_TURNOS_VER);
                Route::post('/turnos', [TurnoPdvMobileController::class, 'store'])
                    ->middleware('pdv.permiso:'.PuntoVentaModulo::PERMISO_TURNOS_ALTA);

                Route::get('/visitas-programadas', [VisitaProgramadaPdvMobileController::class, 'index'])
                    ->middleware('pdv.permiso:'.PuntoVentaModulo::PERMISO_VISITAS_PROGRAMADAS_VER);
                Route::post('/visitas-programadas/{visitaClienteProgramada}/llegada', [VisitaProgramadaPdvMobileController::class, 'confirmarLlegada'])
                    ->middleware('pdv.permiso:'.PuntoVentaModulo::PERMISO_VISITAS_PROGRAMADAS_CONFIRMAR_LLEGADA);
            });
        });
    });

    Route::prefix('mobile/punto-venta')->middleware([
        'auth:sanctum',
        'api.mobile',
        'throttle:api-mobile',
        'mobile.pdv',
        'mobile.pdv:operacion',
        'pdv.permiso:'.PuntoVentaModulo::PERMISO_RESGUARDOS_ENTREGAR,
    ])->group(function () {
        Route::put('/resguardos/{resguardo}/entrega', [ResguardoPdvMobileController::class, 'entrega']);
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
