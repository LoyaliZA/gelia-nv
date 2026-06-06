<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        Permission::findOrCreate('facturas.reportar_error', 'web');

        if (Permission::where('name', 'facturas.responder')->where('guard_name', 'web')->exists()) {
            foreach (User::permission('facturas.responder')->get() as $usuario) {
                if (!$usuario->hasPermissionTo('facturas.reportar_error')) {
                    $usuario->givePermissionTo('facturas.reportar_error');
                }
            }
        }

        if (Permission::where('name', 'solicitudes.reportar')->where('guard_name', 'web')->exists()) {
            foreach (User::permission('solicitudes.reportar')->get() as $usuario) {
                if (!$usuario->hasPermissionTo('facturas.reportar_error')) {
                    $usuario->givePermissionTo('facturas.reportar_error');
                }
            }
        }

        $superAdmin = Role::where('name', 'Super Admin')->where('guard_name', 'web')->first();
        if ($superAdmin) {
            $superAdmin->givePermissionTo('facturas.reportar_error');
        }
    }

    public function down(): void
    {
        Permission::where('name', 'facturas.reportar_error')->where('guard_name', 'web')->delete();
    }
};
