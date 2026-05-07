<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegistroController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Solicitudes\SolicitudController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\CatalogoController;
use App\Http\Controllers\ProfileController;

// CORRECCIÓN 1: Cierre correcto de la ruta raíz
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
    // 1. Ver y Exportar
    Route::middleware(['role:Super Admin|Administrador|Vendedor|Encargada de TAGS|Auxiliar|Contador'])->group(function () {
        Route::get('/solicitudes', [SolicitudController::class, 'index'])->name('solicitudes.index');
        Route::get('/solicitudes/exportar', [SolicitudController::class, 'exportar'])->name('solicitudes.exportar');
    });

    // 2. Crear Solicitudes
    Route::middleware(['role:Super Admin|Administrador|Vendedor'])->group(function () {
        Route::post('/solicitudes', [SolicitudController::class, 'store'])->name('solicitudes.store');
    });

    // 3. Confirmar Pagos
    Route::middleware(['role:Super Admin|Administrador|Contador'])->group(function () {
        Route::put('/solicitudes/{solicitud}/confirmar-pago', [SolicitudController::class, 'confirmarPago'])->name('solicitudes.confirmar_pago');
    });

    // 4. Cambiar Estados
    Route::middleware(['role:Super Admin|Administrador|Encargada de TAGS|Auxiliar'])->group(function () {
        Route::put('/solicitudes/{solicitud}/estado', [SolicitudController::class, 'actualizarEstado'])->name('solicitudes.actualizar_estado');
    });

    // --- MÓDULO DE CATÁLOGOS DINÁMICOS ---
    Route::middleware(['role:Super Admin|Administrador'])->prefix('admin/catalogos')->name('admin.catalogos.')->group(function () {
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
    });

    // --- API INTERNA (Dentro del middleware auth) ---
    Route::prefix('api')->name('api.')->group(function () {
        // Nueva ruta para listar y buscar en el menú desplegable
        Route::get('/clientes', [\App\Http\Controllers\Api\ClienteApiController::class, 'index'])->name('clientes.index');
        
        // Ruta anterior para buscar por número exacto
        Route::get('/clientes/{numero}', [\App\Http\Controllers\Api\ClienteApiController::class, 'show'])->name('clientes.show');
    });
});