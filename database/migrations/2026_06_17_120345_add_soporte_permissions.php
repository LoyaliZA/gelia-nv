<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        // Limpiar caché de spatie
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permisos = [
            'soporte.reportar',
            'soporte.gestionar',
            'soporte.administrar'
        ];

        $roleSuperAdmin = Role::where('name', 'Super Admin')->first();

        foreach ($permisos as $permiso) {
            $p = Permission::findOrCreate($permiso);
            if ($roleSuperAdmin) {
                $roleSuperAdmin->givePermissionTo($p);
            }
        }
    }

    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        
        $permisos = [
            'soporte.reportar',
            'soporte.gestionar',
            'soporte.administrar'
        ];

        Permission::whereIn('name', $permisos)->delete();
    }
};
