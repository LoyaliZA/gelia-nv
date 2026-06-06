<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        $permisos = [
            'cobranza.ver',
            'cobranza.importar_reporte',
            'cobranza.ejecutar_llamadas',
            'cobranza.editar_credito',
        ];

        foreach ($permisos as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }

        // Asignar al rol "Super Admin" únicamente
        $superAdmin = Role::where('name', 'Super Admin')->where('guard_name', 'web')->first();
        if ($superAdmin) {
            $superAdmin->givePermissionTo($permisos);
        }
    }

    public function down(): void
    {
        $permisos = [
            'cobranza.ver',
            'cobranza.importar_reporte',
            'cobranza.ejecutar_llamadas',
            'cobranza.editar_credito',
        ];

        Permission::whereIn('name', $permisos)->where('guard_name', 'web')->delete();
    }
};
