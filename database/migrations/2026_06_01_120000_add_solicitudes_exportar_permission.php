<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    private const PERMISO = 'solicitudes.exportar';

    public function up(): void
    {
        Permission::findOrCreate(self::PERMISO);

        $mapa = [
            'solicitudes.reportar' => [self::PERMISO],
            'solicitudes.verificar' => [self::PERMISO],
        ];

        foreach ($mapa as $permisoOrigen => $permisosNuevos) {
            if (!Permission::where('name', $permisoOrigen)->where('guard_name', 'web')->exists()) {
                continue;
            }

            $usuarios = User::permission($permisoOrigen)->get();
            foreach ($usuarios as $usuario) {
                foreach ($permisosNuevos as $permisoNuevo) {
                    if (! $usuario->hasPermissionTo($permisoNuevo)) {
                        $usuario->givePermissionTo($permisoNuevo);
                    }
                }
            }
        }

        foreach (['Gerente', 'Administrador', 'Super Admin'] as $nombreRol) {
            $role = Role::where('name', $nombreRol)->first();
            if ($role && ! $role->hasPermissionTo(self::PERMISO)) {
                $role->givePermissionTo(self::PERMISO);
            }
        }

        if (Role::where('name', 'Super Admin')->where('guard_name', 'web')->exists()) {
            User::role('Super Admin')->each(function (User $usuario) {
                if (! $usuario->hasPermissionTo(self::PERMISO)) {
                    $usuario->givePermissionTo(self::PERMISO);
                }
            });
        }
    }

    public function down(): void
    {
        Permission::where('name', self::PERMISO)->delete();
    }
};
