<?php

namespace App\Services\Permisos;

use App\Models\User;
use App\Models\UsuarioPermisoProcedencia;
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
     * Permisos efectivos al actualizar: conserva los que el colaborador ya tiene
     * y el asignador no puede delegar (p. ej. asignados por Super Admin).
     */
    /**
     * Permisos del colaborador que otro usuario ya asignó (el gerente no puede quitarlos).
     *
     * @return array<string>
     */
    public static function permisosProtegidosParaAsignador(User $asignador, User $colaborador): array
    {
        if (self::esSuperAdmin($asignador)) {
            return [];
        }

        return UsuarioPermisoProcedencia::query()
            ->where('user_id', $colaborador->id)
            ->whereNotNull('asignado_por_id')
            ->where('asignado_por_id', '!=', $asignador->id)
            ->with('permission')
            ->get()
            ->map(fn (UsuarioPermisoProcedencia $proc) => $proc->permission?->name)
            ->filter()
            ->values()
            ->all();
    }

    public static function resolverPermisosActualizacion(
        User $asignador,
        User $colaborador,
        array $permisosSolicitados
    ): array {
        if (self::esSuperAdmin($asignador)) {
            return collect($permisosSolicitados)->unique()->values()->all();
        }

        $colaborador->loadMissing('permissions');

        $asignable = collect(self::permisosDelUsuario($asignador));
        $existentes = $colaborador->permissions->pluck('name');

        $sinPermisoDelegar = $existentes->diff($asignable);
        $protegidos = collect(self::permisosProtegidosParaAsignador($asignador, $colaborador));
        $gestionados = collect($permisosSolicitados)
            ->intersect($asignable)
            ->diff($protegidos)
            ->values();

        return $sinPermisoDelegar
            ->merge($protegidos)
            ->merge($gestionados)
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
