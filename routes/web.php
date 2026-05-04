<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegistroController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Solicitudes\SolicitudController;

/**
 * Redirección base del sistema
 */
Route::get('/', function () {
    return redirect()->route('login');
});

/**
 * Zona Pública: Accesible solo para usuarios no autenticados
 */
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');

    // Rutas de Registro con URL Firmada (Públicas, pero validadas criptográficamente por Laravel)
    Route::get('/registro/{rol}', [RegistroController::class, 'mostrarFormulario'])
        ->name('registro.formulario')
        ->middleware('signed');
        
    Route::post('/registro/{rol}', [RegistroController::class, 'almacenar'])
        ->name('registro.store')
        ->middleware('signed');
});

/**
 * Zona Privada: Protegida por el middleware de autenticación
 */
Route::middleware(['auth'])->group(function () {
    
    // Controlador de cierre de sesión
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    // Enrutador inteligente (Distribuye la vista según el rol)
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Generación de invitaciones (Exclusivo para Super Admin y Administrador)
    Route::middleware(['role:Super Admin|Administrador'])->group(function () {
        Route::post('/generar-enlace-registro', [RegistroController::class, 'generarEnlaceInvitacion'])
            ->name('registro.generar_enlace');
    });

    // Módulo central de Solicitudes
    Route::get('/solicitudes', [SolicitudController::class, 'index'])->name('solicitudes.index');
    Route::post('/solicitudes', [SolicitudController::class, 'store'])->name('solicitudes.store');
});