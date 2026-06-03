<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    private const ROLES = ['Colaborador', 'Super Admin', 'Administrador', 'Gerente'];

    public function up(): void
    {
        Permission::findOrCreate('mis_clientes.gestionar', 'web');

        foreach (self::ROLES as $nombreRol) {
            $role = Role::where('name', $nombreRol)->where('guard_name', 'web')->first();
            if ($role) {
                $role->givePermissionTo('mis_clientes.gestionar');
            }
        }
    }

    public function down(): void
    {
        Permission::where('name', 'mis_clientes.gestionar')->where('guard_name', 'web')->delete();
    }
};
