<?php

namespace App\Services\Permisos;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

class RevocarPermisosAsignadosPorMigracionService
{
    public const ORIGEN_MIGRACION = 'sistema:migracion';

    /**
     * Quita permisos que el sistema asignó automáticamente (migraciones).
     * No toca permisos asignados manualmente por un administrador.
     *
     * @return array{revocados: int}
     */
    public static function ejecutar(): array
    {
        $asignaciones = DB::table('usuario_permiso_procedencia')
            ->where('plantilla_origen', self::ORIGEN_MIGRACION)
            ->get(['id', 'user_id', 'permission_id']);

        if ($asignaciones->isEmpty()) {
            return ['revocados' => 0];
        }

        foreach ($asignaciones as $row) {
            DB::table('model_has_permissions')
                ->where('model_id', $row->user_id)
                ->where('model_type', User::class)
                ->where('permission_id', $row->permission_id)
                ->delete();

            DB::table('usuario_permiso_procedencia')
                ->where('id', $row->id)
                ->delete();
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return ['revocados' => $asignaciones->count()];
    }
}
