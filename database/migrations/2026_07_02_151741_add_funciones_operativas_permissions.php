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
        $permissions = [
            'funciones.asistencia',
            'funciones.avisos',
            'funciones.gastos',
            'funciones.limpieza_archivos',
            'funciones.transacciones',
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
            'funciones.asistencia',
            'funciones.avisos',
            'funciones.gastos',
            'funciones.limpieza_archivos',
            'funciones.transacciones',
        ];

        foreach ($permissions as $perm) {
            $permission = Permission::where('name', $perm)->first();
            if ($permission) {
                $permission->delete();
            }
        }
    }
};
