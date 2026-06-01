<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    private const PERMISOS = [
        'cancelaciones_cotizaciones.ver_listado',
        'cancelaciones_cotizaciones.crear',
        'cancelaciones_cotizaciones.reportar',
        'cancelaciones_cotizaciones.verificar',
        'cancelaciones_cotizaciones.solicitar_cancelacion',
        'cancelaciones_cotizaciones.cancelar',
        'cancelaciones_cotizaciones.exportar',
        'cancelaciones_cotizaciones.eliminar',
    ];

    public function up(): void
    {
        foreach (self::PERMISOS as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }

        Permission::findOrCreate('solicitudes.eliminar', 'web');

        $mapa = [
            'solicitudes.crear' => ['cancelaciones_cotizaciones.crear', 'cancelaciones_cotizaciones.ver_listado'],
            'solicitudes.reportar' => ['cancelaciones_cotizaciones.reportar', 'cancelaciones_cotizaciones.ver_listado', 'cancelaciones_cotizaciones.exportar'],
            'solicitudes.verificar' => ['cancelaciones_cotizaciones.verificar', 'cancelaciones_cotizaciones.ver_listado'],
            'solicitudes.solicitar_cancelacion' => ['cancelaciones_cotizaciones.solicitar_cancelacion'],
            'solicitudes.cancelar' => ['cancelaciones_cotizaciones.cancelar'],
            'solicitudes.eliminar' => ['cancelaciones_cotizaciones.eliminar'],
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

        User::role('Super Admin')->each(function (User $usuario) {
            foreach (self::PERMISOS as $permiso) {
                if (!$usuario->hasPermissionTo($permiso)) {
                    $usuario->givePermissionTo($permiso);
                }
            }
        });
    }

    public function down(): void
    {
        Permission::whereIn('name', self::PERMISOS)->delete();
    }
};
