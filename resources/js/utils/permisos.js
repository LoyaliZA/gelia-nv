/** @deprecated Use permisoDePlantilla — mantiene compatibilidad */
export function permisoHeredado(permisoName, rolesAsignados, roles) {
    return permisoDePlantilla(permisoName, rolesAsignados, roles);
}

export function permisoDePlantilla(permisoName, plantillasActivas, roles) {
    return (roles || [])
        .filter((r) => (plantillasActivas || []).includes(r.name))
        .some((r) => (r.permissions || []).some((p) => p.name === permisoName));
}

export function plantillasDePermiso(permisoName, plantillasActivas, roles) {
    return (roles || [])
        .filter((r) => (plantillasActivas || []).includes(r.name))
        .filter((r) => (r.permissions || []).some((p) => p.name === permisoName))
        .map((r) => r.name);
}

/** @deprecated Use plantillasDePermiso */
export function rolesHeredantes(permisoName, rolesAsignados, roles) {
    return plantillasDePermiso(permisoName, rolesAsignados, roles);
}

export function permisosDePlantilla(plantillasActivas, roles) {
    const nombres = new Set();
    (roles || [])
        .filter((r) => (plantillasActivas || []).includes(r.name))
        .forEach((r) => (r.permissions || []).forEach((p) => nombres.add(p.name)));
    return [...nombres];
}

/** @deprecated Use permisosDePlantilla */
export function permisosHeredadosDeRoles(rolesAsignados, roles) {
    return permisosDePlantilla(rolesAsignados, roles);
}

export function deduplicarPermisos(permisosIndividuales) {
    return [...new Set(permisosIndividuales || [])];
}

/** @deprecated Ya no filtra plantillas — solo deduplica */
export function filtrarPermisosDirectos(permisosIndividuales) {
    return deduplicarPermisos(permisosIndividuales);
}

export function usuarioPuedeAsignarPermiso(permisoName, permisosUsuario, esSuperAdmin) {
    if (esSuperAdmin) return true;
    return (permisosUsuario || []).includes(permisoName);
}

export function filtrarRolesAsignables(roles, _permisosUsuario, esSuperAdmin) {
    return roles || [];
}

export function filtrarPermisosAsignables(todosLosPermisos, permisosUsuario, esSuperAdmin) {
    if (esSuperAdmin) return todosLosPermisos || [];
    return (todosLosPermisos || []).filter((p) => (permisosUsuario || []).includes(p.name));
}

export function construirPlantillaPorPermiso(permisos, plantillaNombre, roles) {
    if (!plantillaNombre) return {};
    const permisosPlantilla = permisosDePlantilla([plantillaNombre], roles);
    const map = {};
    (permisos || []).forEach((p) => {
        if (permisosPlantilla.includes(p)) {
            map[p] = plantillaNombre;
        }
    });
    return map;
}
