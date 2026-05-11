<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Creamos los nuevos permisos atómicos
        $permisosNuevos = [
            'catalogos.gestionar',
            'comisiones.gestionar'
        ];

        foreach ($permisosNuevos as $permiso) {
            Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
        }

        // 2. Se los asignamos al Super Admin automáticamente
        $superAdmin = Role::where('name', 'Super Admin')->first();
        if ($superAdmin) {
            $superAdmin->givePermissionTo($permisosNuevos);
        }
    }

    public function down(): void
    {
        Permission::whereIn('name', ['catalogos.gestionar', 'comisiones.gestionar'])->delete();
    }
};