<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegistroController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Solicitudes\SolicitudController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\CatalogoController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\ProfileController;

// ══════════════════════════════════════════════════════════════════════
// 1. REDIRECCIÓN INICIAL
// ══════════════════════════════════════════════════════════════════════
Route::get('/', function () {
    return redirect()->route('login');
});

// ══════════════════════════════════════════════════════════════════════
// 2. RUTAS PÚBLICAS (GUEST)
// ══════════════════════════════════════════════════════════════════════
Route::middleware('guest')->group(function () {
    // Login
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');

    // Registro Seguro (Vía enlace firmado) - CORRECCIÓN APLICADA AQUÍ
    Route::get('/registro/colaborador', [RegistroController::class, 'mostrarFormulario'])
        ->name('registro.formulario')
        ->middleware('signed');

    Route::post('/registro/colaborador', [RegistroController::class, 'almacenar'])
        ->name('registro.store')
        ->middleware('signed');
});

// ══════════════════════════════════════════════════════════════════════
// 3. RUTAS PROTEGIDAS (Requieren Autenticación)
// ══════════════════════════════════════════════════════════════════════
Route::middleware(['auth'])->group(function () {

    // --- ACCIONES BASE Y SESIÓN ---
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    // --- DASHBOARD ---
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::put('/dashboard/preferencias', [DashboardController::class, 'actualizarPreferencias'])->name('dashboard.preferencias');

    // --- PERFIL DE USUARIO Y NOTIFICACIONES ---
    Route::get('/perfil', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::post('/perfil', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/notificaciones/{id}/leer', [AdminController::class, 'marcarNotificacionLeida'])->name('notifications.read');


    // ══════════════════════════════════════════════════════════════════════
    // MÓDULO OPERATIVO: SOLICITUDES
    // ══════════════════════════════════════════════════════════════════════

    // ══════════════════════════════════════════════════════════════════════
    // MÓDULO: MIS CLIENTES
    // ══════════════════════════════════════════════════════════════════════
    Route::middleware(['can:mis_clientes.gestionar'])->group(function () {
        Route::get('/mis-clientes', [ClienteController::class, 'misClientes'])->name('mis_clientes.index');
        Route::post('/mis-clientes/rapido', [ClienteController::class, 'registroRapido'])->name('mis_clientes.rapido');
    });

    Route::middleware(['can:solicitudes.ver_listado'])->group(function () {
        Route::get('/solicitudes', [SolicitudController::class, 'index'])->name('solicitudes.index');
        Route::get('/solicitudes/exportar', [SolicitudController::class, 'exportar'])->name('solicitudes.exportar');
    });

    Route::middleware(['can:solicitudes.crear'])->group(function () {
        Route::post('/solicitudes', [SolicitudController::class, 'store'])->name('solicitudes.store');
    });

    // Control de pagos (Limitado)
    // CORRECCIÓN: Se actualizó el middleware para exigir el permiso atómico correcto
    Route::put('/solicitudes/{solicitud}/confirmar-pago', [SolicitudController::class, 'confirmarPago'])
        ->middleware('can:solicitudes.confirmar_pago')
        ->name('solicitudes.confirmar_pago');

    // Control de estados (Verificación mediante Gate en el Controlador)
    Route::put('/solicitudes/{solicitud}', [SolicitudController::class, 'update'])->name('solicitudes.update');
    Route::put('/solicitudes/{solicitud}/rechazar-pago', [SolicitudController::class, 'rechazarPago'])->name('solicitudes.rechazar_pago');
    // AGREGA ESTA LÍNEA: Ruta específica para la revisión administrativa
    Route::put('/solicitudes/{solicitud}/estado', [SolicitudController::class, 'actualizarEstado'])->name('solicitudes.actualizar_estado');
    Route::delete('/solicitudes/{solicitud}', [SolicitudController::class, 'destroy'])->name('solicitudes.destroy');


    // ══════════════════════════════════════════════════════════════════════
    // MÓDULO DE ADMINISTRACIÓN (GELIANV CORE)
    // ══════════════════════════════════════════════════════════════════════
    Route::prefix('admin')->name('admin.')->group(function () {

        // --- 1. Gestión de Usuarios y Accesos ---
        Route::middleware(['can:usuarios.gestionar'])->group(function () {
            Route::get('/usuarios', [AdminController::class, 'usuarios'])->name('usuarios');
            Route::post('/usuarios', [AdminController::class, 'storeUsuario'])->name('usuarios.store');
            Route::put('/usuarios/{user}', [AdminController::class, 'updateUsuario'])->name('usuarios.update');

            Route::get('/enlaces', [AdminController::class, 'enlaces'])->name('enlaces');
            Route::post('/generar-enlace-registro', [RegistroController::class, 'generarEnlaceInvitacion'])->name('enlaces.generar');
        });

        // --- 2. Base de Clientes ---
        Route::middleware(['can:clientes.ver'])->group(function () {
            Route::get('/clientes', [AdminController::class, 'clientes'])->name('clientes');
            Route::post('/clientes/importar', [AdminController::class, 'importarClientes'])->name('clientes.importar');
            Route::get('/clientes/{cliente}/historial', [AdminController::class, 'historialCliente'])->name('clientes.historial');
            Route::post('/clientes', [ClienteController::class, 'store'])->name('clientes.store');
            Route::put('/clientes/{cliente}', [ClienteController::class, 'update'])->name('clientes.update');
        });

        // --- 3. Catálogos Globales (Acceso Estricto Administrativo) ---
        Route::middleware(['can:catalogos.gestionar'])->group(function () {
            Route::get('/catalogos', [AdminController::class, 'catalogos'])->name('catalogos');

            Route::prefix('catalogos')->name('catalogos.')->group(function () {
                // Departamentos
                Route::post('/departamentos', [CatalogoController::class, 'storeDepartamento'])->name('departamentos.store');
                Route::put('/departamentos/{id}', [CatalogoController::class, 'updateDepartamento'])->name('departamentos.update');
                Route::delete('/departamentos/{id}', [CatalogoController::class, 'destroyDepartamento'])->name('departamentos.destroy');

                // Áreas
                Route::post('/areas', [CatalogoController::class, 'storeArea'])->name('areas.store');
                Route::put('/areas/{id}', [CatalogoController::class, 'updateArea'])->name('areas.update');
                Route::delete('/areas/{id}', [CatalogoController::class, 'destroyArea'])->name('areas.destroy');

                // Procesos
                Route::post('/procesos', [CatalogoController::class, 'storeProceso'])->name('procesos.store');
                Route::put('/procesos/{id}', [CatalogoController::class, 'updateProceso'])->name('procesos.update');
                Route::delete('/procesos/{id}', [CatalogoController::class, 'destroyProceso'])->name('procesos.destroy');

                // Listas
                Route::post('/listas', [CatalogoController::class, 'storeLista'])->name('listas.store');
                Route::put('/listas/{id}', [CatalogoController::class, 'updateLista'])->name('listas.update');
                Route::delete('/listas/{id}', [CatalogoController::class, 'destroyLista'])->name('listas.destroy');

                // Estados
                Route::post('/estados', [CatalogoController::class, 'storeEstado'])->name('estados.store');
                Route::put('/estados/{id}', [CatalogoController::class, 'updateEstado'])->name('estados.update');
                Route::delete('/estados/{id}', [CatalogoController::class, 'destroyEstado'])->name('estados.destroy');

                // Tipo de Clientes
                Route::post('/tipo-clientes', [CatalogoController::class, 'storeTipoCliente'])->name('tipo_clientes.store');
                Route::put('/tipo-clientes/{id}', [CatalogoController::class, 'updateTipoCliente'])->name('tipo_clientes.update');
                Route::delete('/tipo-clientes/{id}', [CatalogoController::class, 'destroyTipoCliente'])->name('tipo_clientes.destroy');
            });
        });

        // --- 4. Comisiones ---
        Route::middleware(['can:comisiones.gestionar'])->group(function () {
            Route::get('/comisiones', [AdminController::class, 'comisiones'])->name('comisiones');
            Route::put('/comisiones/{id}', [AdminController::class, 'actualizarComision'])->name('comisiones.update');
        });
    });

    // ══════════════════════════════════════════════════════════════════════
    // API INTERNA DEL SISTEMA
    // ══════════════════════════════════════════════════════════════════════
    Route::prefix('api')->name('api.')->group(function () {
        Route::get('/clientes', [\App\Http\Controllers\Api\ClienteApiController::class, 'index'])->name('clientes.index');
        Route::get('/clientes/{numero}', [\App\Http\Controllers\Api\ClienteApiController::class, 'show'])->name('clientes.show');
    });
});
