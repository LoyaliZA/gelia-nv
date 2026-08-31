<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\WebPushController;

Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
Route::put('/dashboard/preferencias', [DashboardController::class, 'actualizarPreferencias'])->name('dashboard.preferencias');

Route::get('/perfil', [ProfileController::class, 'index'])->name('profile.index');
Route::delete('/perfil/sesiones/otras', [ProfileController::class, 'destroyOtherSessions'])->name('profile.sessions.destroy-others');
Route::get('/perfil/preferencias', [ProfileController::class, 'edit'])->name('profile.preferencias');
Route::get('/perfil/novedades', [ProfileController::class, 'novedades'])->name('profile.novedades');
Route::post('/perfil', [ProfileController::class, 'update'])->name('profile.update');
Route::post('/notificaciones/{id}/leer', [AdminController::class, 'marcarNotificacionLeida'])->name('notifications.read');
Route::post('/notificaciones/limpiar', [AdminController::class, 'limpiarNotificaciones'])->name('notifications.clear');

Route::get('/push/vapid-public-key', [WebPushController::class, 'vapidPublicKey'])->name('push.vapid');
Route::post('/push/subscribe', [WebPushController::class, 'subscribe'])->name('push.subscribe');
Route::delete('/push/unsubscribe', [WebPushController::class, 'unsubscribe'])->name('push.unsubscribe');
