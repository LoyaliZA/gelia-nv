<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\{AdminController, Auth\RegistroController};

Route::middleware(['can:usuarios.gestionar'])->group(function () {
    Route::get('/usuarios', [AdminController::class, 'usuarios'])->name('usuarios');
    Route::post('/usuarios', [AdminController::class, 'storeUsuario'])->name('usuarios.store');
    Route::put('/usuarios/{user}', [AdminController::class, 'updateUsuario'])->name('usuarios.update');

    Route::put('/roles/{role}/permisos-heredados', [AdminController::class, 'updateRolePermisosHerencia'])->name('roles.permisos.update');
    Route::post('/roles/grupos', [AdminController::class, 'storeGrupoPredefinido'])->name('roles.grupos.store');
});

Route::middleware(['can:usuarios.archivar'])->group(function () {
    Route::delete('/usuarios/{user}/archivar', [AdminController::class, 'archivarUsuario'])->name('usuarios.archivar');
});

Route::middleware(['can:usuarios.generar_permisos'])->group(function () {
    Route::get('/enlaces', [AdminController::class, 'enlaces'])->name('enlaces');
    Route::post('/generar-enlace-registro', [RegistroController::class, 'generarEnlaceInvitacion'])->name('enlaces.generar');
});
