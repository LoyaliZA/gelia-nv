<?php

namespace App\Services\Permisos;

use App\Models\User;
use App\Models\UsuarioPermisoProcedencia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class AsignarPermisosUsuarioService
{
    /**
     * Asigna permisos directos al usuario y registra procedencia (superior + plantilla).
     *
     * @param  array<string>  $permisos  Nombres de permisos
     */
    public static function asignar(
        User $usuario,
        array $permisos,
        ?User $asignador = null,
        ?string $plantillaOrigen = null,
        ?array $plantillaPorPermiso = null
    ): void {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permisosUnicos = collect($permisos)->unique()->values()->all();
        $usuario->syncPermissions($permisosUnicos);

        $permissionIds = Permission::whereIn('name', $permisosUnicos)->pluck('id', 'name');

        UsuarioPermisoProcedencia::where('user_id', $usuario->id)
            ->whereNotIn('permission_id', $permissionIds->values())
            ->delete();

        $now = now();
        foreach ($permisosUnicos as $permisoName) {
            $permissionId = $permissionIds->get($permisoName);
            if (!$permissionId) {
                continue;
            }

            $origenPlantilla = is_array($plantillaPorPermiso)
                ? ($plantillaPorPermiso[$permisoName] ?? $plantillaOrigen)
                : $plantillaOrigen;

            UsuarioPermisoProcedencia::updateOrCreate(
                [
                    'user_id' => $usuario->id,
                    'permission_id' => $permissionId,
                ],
                [
                    'asignado_por_id' => $asignador?->id,
                    'plantilla_origen' => $origenPlantilla,
                    'updated_at' => $now,
                ]
            );
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
