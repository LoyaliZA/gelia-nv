<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
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

        $superAdmin = Role::where('name', 'Super Admin')->where('guard_name', 'web')->first();
        if ($superAdmin) {
            $superAdmin->givePermissionTo($permisos);
        }

        $roles = Role::whereHas('permissions', fn ($q) => $q->where('name', 'catalogos.gestionar'))->get();
        foreach ($roles as $role) {
            $role->givePermissionTo($permisos);
        }
    }

    public function down(): void
    {
        Permission::whereIn('name', [
            'almacenes.ver',
            'almacenes.productos.ver',
            'almacenes.productos.gestionar',
            'almacenes.inventarios.ver',
            'almacenes.inventarios.gestionar',
            'almacenes.inventarios.importar',
            'almacenes.costos.ver',
            'almacenes.costos.gestionar',
            'almacenes.costos.importar',
        ])->where('guard_name', 'web')->delete();
    }
};
