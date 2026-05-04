<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegistroController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Solicitudes\SolicitudController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\ProfileController; // Nuevo para el perfil

Route::get('/', function () {
    return redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');

    Route::get('/registro/{rol}', [RegistroController::class, 'mostrarFormulario'])
        ->name('registro.formulario')
        ->middleware('signed');
        
    Route::post('/registro/{rol}', [RegistroController::class, 'almacenar'])
        ->name('registro.store')
        ->middleware('signed');
});

Route::middleware(['auth'])->group(function () {
    
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // --- RUTA DE PERFIL (Para todos los usuarios) ---
    Route::get('/perfil', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::post('/perfil', [ProfileController::class, 'update'])->name('profile.update');

    // --- RUTA DE NOTIFICACIONES ---
    Route::post('/notificaciones/{id}/leer', [AdminController::class, 'marcarNotificacionLeida'])->name('notifications.read');

    // --- PROTECCIÓN PARA ADMINS Y SUPER ADMINS ---
    Route::middleware(['role:Super Admin|Administrador'])->group(function () {
        Route::post('/generar-enlace-registro', [RegistroController::class, 'generarEnlaceInvitacion'])
            ->name('registro.generar_enlace');
    });

    // --- MÓDULOS DE ADMINISTRACIÓN (GELIANV CORE) ---
    Route::middleware(['role:Super Admin|Administrador'])->prefix('admin')->name('admin.')->group(function () {
        Route::get('/enlaces', [AdminController::class, 'enlaces'])->name('enlaces');
        Route::get('/catalogos', [AdminController::class, 'catalogos'])->name('catalogos');
        Route::get('/usuarios', [AdminController::class, 'usuarios'])->name('usuarios');

        // RUTAS PARA CLIENTES (Carga Masiva e Historial)
        Route::get('/clientes', [AdminController::class, 'clientes'])->name('clientes');
        Route::post('/clientes/importar', [AdminController::class, 'importarClientes'])->name('clientes.importar');
        Route::get('/clientes/{cliente}/historial', [AdminController::class, 'historialCliente'])->name('clientes.historial');

        // RUTAS PARA COMISIONES (Tabulador)
        Route::get('/comisiones', [AdminController::class, 'comisiones'])->name('comisiones');
        Route::put('/comisiones/{id}', [AdminController::class, 'actualizarComision'])->name('comisiones.update');
    });

    // --- MÓDULO CENTRAL DE SOLICITUDES ---
    // 1. Ver y Exportar: Todos los roles operativos pueden ver el panel (el controlador filtrará qué ven)
    Route::middleware(['role:Super Admin|Administrador|Vendedor|Encargada de TAGS|Auxiliar|Contador'])->group(function () {
        Route::get('/solicitudes', [SolicitudController::class, 'index'])->name('solicitudes.index');
        Route::get('/solicitudes/exportar', [SolicitudController::class, 'exportar'])->name('solicitudes.exportar');
    });

    // 2. Crear Solicitudes: Solo Vendedoras y Admins pueden subir nuevas solicitudes[cite: 1, 2]
    Route::middleware(['role:Super Admin|Administrador|Vendedor'])->group(function () {
        Route::post('/solicitudes', [SolicitudController::class, 'store'])->name('solicitudes.store');
    });

    // 3. Confirmar Pagos: Solo Contabilidad y Admins
    Route::middleware(['role:Super Admin|Administrador|Contador'])->group(function () {
        Route::put('/solicitudes/{solicitud}/confirmar-pago', [SolicitudController::class, 'confirmarPago'])->name('solicitudes.confirmar_pago');
    });

    // 4. Cambiar Estados (Aprobar, Reportar, Verificar): Encargada de TAGS, Auxiliar y Admins[cite: 1, 2]
    Route::middleware(['role:Super Admin|Administrador|Encargada de TAGS|Auxiliar'])->group(function () {
        Route::put('/solicitudes/{solicitud}/estado', [SolicitudController::class, 'actualizarEstado'])->name('solicitudes.actualizar_estado');
    });
    
    // Acciones operativas (Empatan con la BD de tu amigo)
    Route::put('/solicitudes/{solicitud}/confirmar-pago', [SolicitudController::class, 'confirmarPago'])->name('solicitudes.confirmar_pago');
    Route::put('/solicitudes/{solicitud}/estado', [SolicitudController::class, 'actualizarEstado'])->name('solicitudes.actualizar_estado');

    // Endpoint de exportación
    Route::get('/solicitudes/exportar', [SolicitudController::class, 'exportar'])->name('solicitudes.exportar');
});