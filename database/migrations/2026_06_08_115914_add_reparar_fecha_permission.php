<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permiso = Permission::firstOrCreate([
            'name' => 'cobranza.reparar_fecha',
            'guard_name' => 'web'
        ]);

        $role = Role::where('name', 'Super Admin')->first();
        if ($role) {
            $role->givePermissionTo($permiso);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        
        $permiso = Permission::where('name', 'cobranza.reparar_fecha')->where('guard_name', 'web')->first();
        if ($permiso) {
            $permiso->delete();
        }
    }
};
