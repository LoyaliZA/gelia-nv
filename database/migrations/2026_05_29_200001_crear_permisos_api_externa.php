<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $permisos = [
            'api_externa.gestionar',
            'api_externa.ver_auditoria',
        ];

        foreach ($permisos as $nombre) {
            Permission::firstOrCreate([
                'name' => $nombre,
                'guard_name' => 'web',
            ]);
        }

        $rolesAdministrativos = Role::whereIn('name', ['Super Admin', 'Administrador'])->get();

        foreach ($rolesAdministrativos as $rol) {
            $rol->givePermissionTo($permisos);
        }
    }

    public function down(): void
    {
        app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::whereIn('name', ['api_externa.gestionar', 'api_externa.ver_auditoria'])->delete();
    }
};
