<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    private const PERMISOS = [
        'facturas.ver_listado',
        'facturas.crear',
        'facturas.responder',
        'facturas.verificar',
        'facturas.eliminar',
        'facturas.gestionar_datos_fiscales',
        'facturas.exportar',
    ];

    public function up(): void
    {
        foreach (self::PERMISOS as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }

        Permission::findOrCreate('solicitudes.eliminar', 'web');

        $mapa = [
            'solicitudes.crear' => ['facturas.crear', 'facturas.ver_listado'],
            'solicitudes.reportar' => ['facturas.responder', 'facturas.ver_listado', 'facturas.exportar'],
            'solicitudes.verificar' => ['facturas.verificar', 'facturas.ver_listado'],
            'solicitudes.eliminar' => ['facturas.eliminar'],
            'clientes.ver' => ['facturas.gestionar_datos_fiscales'],
        ];

        foreach ($mapa as $permisoOrigen => $permisosNuevos) {
            if (!Permission::where('name', $permisoOrigen)->where('guard_name', 'web')->exists()) {
                continue;
            }

            $usuarios = User::permission($permisoOrigen)->get();
            foreach ($usuarios as $usuario) {
                foreach ($permisosNuevos as $permisoNuevo) {
                    if (!$usuario->hasPermissionTo($permisoNuevo)) {
                        $usuario->givePermissionTo($permisoNuevo);
                    }
                }
            }
        }

        if (\Spatie\Permission\Models\Role::where('name', 'Super Admin')->where('guard_name', 'web')->exists()) {
            User::role('Super Admin')->each(function (User $usuario) {
                foreach (self::PERMISOS as $permiso) {
                    if (!$usuario->hasPermissionTo($permiso)) {
                        $usuario->givePermissionTo($permiso);
                    }
                }
            });
        }
    }

    public function down(): void
    {
        Permission::whereIn('name', self::PERMISOS)->delete();
    }
};
