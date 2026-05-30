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
    /**
     * @param  array<string>|null  $permisosActualizarProcedencia  Si se define, solo esos permisos actualizan asignado_por/plantilla.
     */
    public static function asignar(
        User $usuario,
        array $permisos,
        ?User $asignador = null,
        ?string $plantillaOrigen = null,
        ?array $plantillaPorPermiso = null,
        ?array $permisosActualizarProcedencia = null
    ): void {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $usuario->loadMissing('permissions');
        $existentesAntes = $usuario->permissions->pluck('name')->all();

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

            $esNuevo = !in_array($permisoName, $existentesAntes, true);
            $debeActualizarProcedencia = $permisosActualizarProcedencia === null
                ? $esNuevo
                : ($esNuevo && in_array($permisoName, $permisosActualizarProcedencia, true));

            $attrs = ['updated_at' => $now];
            if ($debeActualizarProcedencia) {
                $attrs['asignado_por_id'] = $asignador?->id;
                $attrs['plantilla_origen'] = $origenPlantilla;
            }

            UsuarioPermisoProcedencia::updateOrCreate(
                [
                    'user_id' => $usuario->id,
                    'permission_id' => $permissionId,
                ],
                $attrs
            );
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
