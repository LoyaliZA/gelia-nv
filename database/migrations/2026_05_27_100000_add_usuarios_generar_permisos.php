<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $permiso = Permission::firstOrCreate([
            'name' => 'usuarios.generar_permisos',
            'guard_name' => 'web',
        ]);

        $rolesAdministrativos = Role::whereIn('name', ['Super Admin', 'Administrador'])->get();

        foreach ($rolesAdministrativos as $rol) {
            $rol->givePermissionTo($permiso);
        }
    }

    public function down(): void
    {
        app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $permiso = Permission::where('name', 'usuarios.generar_permisos')->first();

        if ($permiso) {
            $permiso->delete();
        }
    }
};
