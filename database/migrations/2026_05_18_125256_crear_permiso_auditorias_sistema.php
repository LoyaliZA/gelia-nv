<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        // Limpiar caché de Spatie para evitar fallos de registro
        app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        // 1. Creación del nuevo permiso global
        $permiso = Permission::firstOrCreate([
            'name' => 'sistema.auditorias.ver',
            'guard_name' => 'web'
        ]);

        // 2. Asignación inmediata a roles gerenciales o administrativos
        $rolesAdministrativos = Role::whereIn('name', ['Super Admin', 'Administrador'])->get();
        
        foreach ($rolesAdministrativos as $rol) {
            $rol->givePermissionTo($permiso);
        }
    }

    public function down(): void
    {
        app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $permiso = Permission::where('name', 'sistema.auditorias.ver')->first();
        
        if ($permiso) {
            // La cascada de la base de datos se encarga de desvincularlo de los roles
            $permiso->delete();
        }
    }
};