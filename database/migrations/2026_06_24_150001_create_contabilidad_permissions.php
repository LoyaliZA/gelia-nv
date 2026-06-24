<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        $permisos = [
            'contabilidad.ver',
            'contabilidad.pedidos.crear',
            'contabilidad.pedidos.editar',
            'contabilidad.pedidos.eliminar',
            'contabilidad.retiros.confirmar',
            'contabilidad.plataformas.configurar',
            'contabilidad.importar',
            'contabilidad.exportar',
            'contabilidad.historial.editar_emergencia',
        ];

        foreach ($permisos as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }

        $superAdmin = Role::where('name', 'Super Admin')->where('guard_name', 'web')->first();
        if ($superAdmin) {
            $superAdmin->givePermissionTo($permisos);
        }
    }

    public function down(): void
    {
        $permisos = [
            'contabilidad.ver',
            'contabilidad.pedidos.crear',
            'contabilidad.pedidos.editar',
            'contabilidad.pedidos.eliminar',
            'contabilidad.retiros.confirmar',
            'contabilidad.plataformas.configurar',
            'contabilidad.importar',
            'contabilidad.exportar',
            'contabilidad.historial.editar_emergencia',
        ];

        Permission::whereIn('name', $permisos)->where('guard_name', 'web')->delete();
    }
};
