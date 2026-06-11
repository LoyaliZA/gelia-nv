<?php

namespace App\Services\Permisos;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Las migraciones solo deben registrar permisos en el catálogo.
 * Nunca asignarlos a usuarios ni roles: eso lo hace un administrador.
 */
class PermisoCatalogoMigracion
{
    public static function registrar(string|array $permisos, string $guard = 'web'): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach ((array) $permisos as $permiso) {
            Permission::findOrCreate($permiso, $guard);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
