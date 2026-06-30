<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permisos = [
            'almacenes.ver',
            'almacenes.productos.ver',
            'almacenes.productos.gestionar',
            'almacenes.inventarios.ver',
            'almacenes.inventarios.gestionar',
            'almacenes.inventarios.importar',
            'almacenes.costos.ver',
            'almacenes.costos.gestionar',
            'almacenes.costos.importar',
        ];

        foreach ($permisos as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }

        $rolesConAcceso = Role::whereIn('name', ['Super Admin', 'Administrador', 'Gerente'])
            ->where('guard_name', 'web')
            ->get();

        foreach ($rolesConAcceso as $role) {
            $role->givePermissionTo($permisos);
        }

        Role::whereHas('permissions', fn ($q) => $q->where('name', 'catalogos.gestionar'))
            ->where('guard_name', 'web')
            ->each(fn (Role $role) => $role->givePermissionTo($permisos));

        User::permission('catalogos.gestionar')->each(
            fn (User $user) => $user->givePermissionTo($permisos)
        );
    }

    public function down(): void
    {
        // Sin reversión: los permisos pueden seguir asignados manualmente.
    }
};
