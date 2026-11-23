<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Permission::findOrCreate('sistema.pruebas_componentes.ver', 'web');
        $superAdmin = Role::where('name', 'Super Admin')->where('guard_name', 'web')->first();
        $superAdmin?->givePermissionTo('sistema.pruebas_componentes.ver');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $permiso = Permission::where('name', 'sistema.pruebas_componentes.ver')->where('guard_name', 'web')->first();
        if ($permiso) {
            $permiso->delete();
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
