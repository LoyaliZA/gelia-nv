<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        Permission::findOrCreate('clientes.correccion_emergencia', 'web');

        $superAdmin = Role::where('name', 'Super Admin')->where('guard_name', 'web')->first();
        if ($superAdmin) {
            $superAdmin->givePermissionTo('clientes.correccion_emergencia');
        }
    }

    public function down(): void
    {
        Permission::where('name', 'clientes.correccion_emergencia')->where('guard_name', 'web')->delete();
    }
};
