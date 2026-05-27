<?php

namespace App\Services\Permisos;

use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class ValidarAsignacionPermisosService
{
    public static function esSuperAdmin(User $user): bool
    {
        return $user->hasRole('Super Admin');
    }

    public static function permisosDelUsuario(User $user): array
    {
        if (self::esSuperAdmin($user)) {
            return Permission::pluck('name')->all();
        }

        return $user->getAllPermissions()->pluck('name')->all();
    }

    /**
     * Permisos definidos en plantillas de roles/grupos (sin efecto de acceso).
     */
    public static function permisosDePlantilla(array $rolesNames): array
    {
        return self::permisosHeredadosDeRoles($rolesNames);
    }

    /**
     * @deprecated Use permisosDePlantilla() — mantiene compatibilidad con código existente.
     */
    public static function permisosHeredadosDeRoles(array $rolesNames): array
    {
        if (empty($rolesNames)) {
            return [];
        }

        return Role::with('permissions')
            ->whereIn('name', $rolesNames)
            ->get()
            ->flatMap(fn (Role $rol) => $rol->permissions->pluck('name'))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Valida que el asignador pueda delegar los permisos indicados.
     */
    public static function assertPuedeAsignar(User $asignador, array $rolesNames, array $permisosIndividuales = []): void
    {
        if (self::esSuperAdmin($asignador)) {
            return;
        }

        $permisosPermitidos = collect(self::permisosDelUsuario($asignador));

        foreach ($permisosIndividuales as $permiso) {
            if (!$permisosPermitidos->contains($permiso)) {
                abort(403, "No puedes asignar el permiso «{$permiso}» porque no lo posees.");
            }
        }
    }

    /**
     * Filtra roles asignables según jerarquía (sin filtrar por permisos de plantilla).
     */
    public static function filtrarRolesAsignables($roles, User $user)
    {
        if (self::esSuperAdmin($user)) {
            return $roles;
        }

        if ($user->hasRole('Gerente')) {
            return collect($roles)->filter(
                fn ($rol) => !in_array($rol->name ?? $rol['name'] ?? '', ['Administrador', 'Super Admin'], true)
            )->values();
        }

        return collect($roles);
    }
}
