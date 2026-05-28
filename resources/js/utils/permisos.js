/** @deprecated Use permisoDePlantilla — mantiene compatibilidad */
export function permisoHeredado(permisoName, rolesAsignados, roles) {
    return permisoDePlantilla(permisoName, rolesAsignados, roles);
}

export const DESCRIPCIONES_PERMISOS = {
    'solicitudes.ver_listado': 'Permite acceder al listado general de solicitudes.',
    'solicitudes.ver_detalle': 'Permite ver el detalle completo de una solicitud.',
    'solicitudes.crear': 'Permite crear nuevas solicitudes de proceso.',
    'solicitudes.editar': 'Permite editar solicitudes en estado editable.',
    'solicitudes.verificar': 'Permite marcar solicitudes como verificadas (auxiliar).',
    'solicitudes.reportar': 'Permite aprobar procesos, reportar errores y responder consultas.',
    'solicitudes.consultar': 'Permite consultar TAG o lista sobre una solicitud respondida.',
    'solicitudes.confirmar_pago': 'Permite confirmar el pago de una solicitud (encargada).',
    'solicitudes.solicitar_cancelacion': 'Permite solicitar la cancelación de folios propios activos.',
    'solicitudes.cancelar': 'Permite confirmar cancelaciones pendientes y revertir cambios al cliente.',
    'solicitudes.confirmar_cambio_lista': 'Permite confirmar el ajuste de lista tras alerta de pago insuficiente.',
    'solicitudes.eliminar': 'Permite eliminar registros de solicitudes con respaldo en auditoría.',
    'clientes.ver': 'Permite consultar el catálogo de clientes.',
    'clientes.crear': 'Permite registrar clientes manualmente.',
    'clientes.carga_masiva': 'Permite importar clientes de forma masiva.',
    'configuracion.ver_auditoria': 'Permite consultar la bitácora de auditoría de solicitudes.',
    'usuarios.gestionar': 'Permite administrar usuarios del sistema.',
    'usuarios.generar_permisos': 'Permite asignar permisos a otros usuarios.',
    'catalogos.comisiones.ver': 'Permite consultar el catálogo de comisiones.',
    'catalogos.comisiones.gestionar': 'Permite editar el catálogo de comisiones.',
    'listados.ver': 'Permite ver listados de productos para resurtido.',
    'listados.crear': 'Permite crear listados de productos.',
    'listados.editar': 'Permite editar listados de productos.',
    'listados.eliminar': 'Permite eliminar listados de productos.',
    'personalizacion.gestionar': 'Permite gestionar temas y personalización visual.',
};

export function descripcionPermiso(permisoName) {
    return DESCRIPCIONES_PERMISOS[permisoName] || null;
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
