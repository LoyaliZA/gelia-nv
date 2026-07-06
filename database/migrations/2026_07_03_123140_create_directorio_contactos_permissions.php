<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $permissions = [
            'gestion_interna.directorio.ver',
            'gestion_interna.directorio.gestionar',
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
            'gestion_interna.directorio.ver',
            'gestion_interna.directorio.gestionar',
        ];

        foreach ($permissions as $perm) {
            $permission = Permission::where('name', $perm)->first();
            if ($permission) {
                $permission->delete();
            }
        }
    }
};
