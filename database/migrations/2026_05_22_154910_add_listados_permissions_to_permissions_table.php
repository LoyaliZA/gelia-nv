<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        // Limpiamos caché de Spatie para evitar conflictos
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permisos = [
            'listados.ver',
            'listados.crear',
            'listados.editar',
            'listados.eliminar',
            'listados.configurar_porcentajes'
        ];

        foreach ($permisos as $permiso) {
            Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
        }

        // Opcional: Asignar estos permisos automáticamente al rol de Super Admin si existe
        $superAdmin = Role::where('name', 'Super Admin')->first();
        if ($superAdmin) {
            $superAdmin->givePermissionTo($permisos);
        }
    }

    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permisos = [
            'listados.ver',
            'listados.crear',
            'listados.editar',
            'listados.eliminar',
            'listados.configurar_porcentajes'
        ];

        Permission::whereIn('name', $permisos)->delete();
    }
};