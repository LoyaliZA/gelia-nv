<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Eliminar permiso antiguo si existe
        $oldPermission = Permission::where('name', 'funciones.plantilla_bellaroma')->first();
        if ($oldPermission) {
            $oldPermission->delete();
        }

        // Crear permisos granulares
        $permissions = [
            'plantilla_pedidos.ver',
            'plantilla_pedidos.generar',
            'plantilla_pedidos.configurar',
            'plantilla_pedidos.descargar',
            'plantilla_pedidos.visualizar',
            'plantilla_pedidos.eliminar',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $permissions = [
            'plantilla_pedidos.ver',
            'plantilla_pedidos.generar',
            'plantilla_pedidos.configurar',
            'plantilla_pedidos.descargar',
            'plantilla_pedidos.visualizar',
            'plantilla_pedidos.eliminar',
        ];

        foreach ($permissions as $perm) {
            $permission = Permission::where('name', $perm)->first();
            if ($permission) {
                $permission->delete();
            }
        }

        // Restaurar permiso antiguo
        Permission::firstOrCreate(['name' => 'funciones.plantilla_bellaroma', 'guard_name' => 'web']);
    }
};
