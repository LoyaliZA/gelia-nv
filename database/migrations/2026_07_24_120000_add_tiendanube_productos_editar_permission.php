<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    private array $permisos = [
        'tiendanube.productos.editar',
    ];

    public function up(): void
    {
        foreach ($this->permisos as $permiso) {
            Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
        }

        foreach (['Administrador', 'Super Admin'] as $roleName) {
            $role = Role::where('name', $roleName)->first();
            if ($role) {
                $role->givePermissionTo($this->permisos);
            }
        }
    }

    public function down(): void
    {
        foreach ($this->permisos as $permiso) {
            Permission::where('name', $permiso)->delete();
        }
    }
};
