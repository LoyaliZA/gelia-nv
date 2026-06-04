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
    'solicitudes.exportar': 'Permite exportar reportes de solicitudes financieras en PDF, Excel y CSV.',
    'clientes.ver': 'Permite consultar el catálogo de clientes.',
    'clientes.crear': 'Permite registrar clientes manualmente.',
    'clientes.carga_masiva': 'Permite importar clientes de forma masiva.',
    'mis_clientes.gestionar': 'Permite ver la cartera propia y registrar clientes en Mis Clientes.',
    'clientes.correccion_emergencia': 'Permite corregir número y nombre intercambiados o en conflicto de unicidad.',
    'configuracion.ver_auditoria': 'Permite consultar la bitácora de auditoría de solicitudes.',
    'usuarios.gestionar': 'Permite administrar usuarios del sistema.',
    'usuarios.generar_permisos': 'Permite asignar permisos a otros usuarios.',
    'catalogos.comisiones.ver': 'Permite consultar el catálogo de comisiones.',
    'catalogos.comisiones.gestionar': 'Permite editar el catálogo de comisiones.',
    'listados.ver': 'Permite ver listados de productos para resurtido.',
    'listados.crear': 'Permite crear listados de productos.',
    'listados.editar': 'Permite editar listados de productos.',
    'listados.eliminar': 'Permite eliminar listados de productos.',
    'activos.ver': 'Permite ver el listado y detalle de activos.',
    'activos.crear': 'Permite registrar nuevos activos.',
    'activos.editar': 'Permite editar activos existentes.',
    'activos.asignar': 'Permite asignar activos a usuarios Gelia.',
    'activos.transferir': 'Permite transferir activos entre departamentos.',
    'activos.cambiar_estado': 'Permite cambiar el estado del activo (mantenimiento, baja).',
    'activos.ver_todos': 'Permite ver activos de todos los departamentos.',
    'activos.configurar_tipos': 'Permite gestionar el catálogo de tipos de activo.',
    'activos.exportar': 'Permite exportar activos a Excel.',
    'personalizacion.gestionar': 'Permite gestionar temas y personalización visual.',
    'facturas.ver_listado': 'Permite acceder al módulo de solicitudes de facturas.',
    'facturas.crear': 'Permite crear y reparar solicitudes de factura propias (comprobante de pago).',
    'facturas.responder': 'Permite emitir factura (PDF/XML) en solicitudes pendientes.',
    'facturas.reportar_error': 'Permite reportar error en solicitudes de factura pendientes.',
    'facturas.verificar': 'Permite marcar solicitudes de factura como verificadas.',
    'facturas.eliminar': 'Permite eliminar solicitudes de factura con auditoría.',
    'facturas.gestionar_datos_fiscales': 'Permite administrar datos fiscales de clientes desde el módulo de facturas.',
    'facturas.exportar': 'Permite exportar el listado de solicitudes de factura.',
    'cancelaciones_cotizaciones.ver_listado': 'Permite acceder al módulo de cancelaciones y cotizaciones.',
    'cancelaciones_cotizaciones.crear': 'Permite crear solicitudes de cancelación de remisión/pedido o cotización sobre pedido.',
    'cancelaciones_cotizaciones.reportar': 'Permite aprobar o reportar errores en solicitudes operativas.',
    'cancelaciones_cotizaciones.verificar': 'Permite marcar solicitudes operativas como verificadas.',
    'cancelaciones_cotizaciones.solicitar_cancelacion': 'Permite solicitar la cancelación de folios operativos propios.',
    'cancelaciones_cotizaciones.cancelar': 'Permite confirmar cancelaciones de folios operativos pendientes.',
    'cancelaciones_cotizaciones.exportar': 'Permite exportar el listado de cancelaciones y cotizaciones.',
    'cancelaciones_cotizaciones.eliminar': 'Permite eliminar solicitudes operativas con auditoría.',
    'api_externa.gestionar': 'Permite administrar la API externa, aplicaciones, permisos y documentación.',
    'api_externa.ver_auditoria': 'Permite consultar la bitácora de uso de la API externa.',
};

export function descripcionPermiso(permisoName) {
    return DESCRIPCIONES_PERMISOS[permisoName] || null;
}

/** Normaliza permisos compartidos por Inertia (array o objeto indexado por id). */
export function permisosUsuario(auth) {
    const raw = auth?.user?.permissions;
    if (Array.isArray(raw)) return raw;
    if (raw && typeof raw === 'object') return Object.values(raw);
    return [];
}

/** Evalúa permiso atómico con bypass de Super Admin (mismo criterio que Sidebar). */
export function puedePermiso(auth, permiso) {
    const roles = auth?.user?.roles || [];
    if (roles.includes('Super Admin')) return true;
    return permisosUsuario(auth).includes(permiso);
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

/** Permisos que un gerente no puede otorgar a colaboradores (aunque los tenga en su cuenta). */
export const PERMISOS_NO_DELEGABLES_POR_GERENTE = new Set([
    'configuracion.ver_auditoria',
    'usuarios.gestionar',
    'usuarios.generar_permisos',
    'solicitudes.confirmar_pago',
    'personalizacion.gestionar',
    'api_externa.gestionar',
    'api_externa.ver_auditoria',
]);

export function permisoNoDelegablePorGerente(permisoName, esSuperAdmin) {
    if (esSuperAdmin) return false;
    return PERMISOS_NO_DELEGABLES_POR_GERENTE.has(permisoName);
}

export function permisoProtegidoParaEditor(meta, usuarioActualId, esSuperAdmin) {
    if (esSuperAdmin || !meta) return false;
    const asignador = meta.asignado_por;
    if (!asignador) return false;
    if (asignador.es_super_admin || asignador.es_administrador) return true;
    if (asignador.id != null && usuarioActualId != null && Number(asignador.id) !== Number(usuarioActualId)) {
        return true;
    }
    return false;
}

export function gerentePuedeMostrarPermisoInactivo(permisoName, permisosUsuario, esSuperAdmin) {
    if (esSuperAdmin) return true;
    if (!usuarioPuedeAsignarPermiso(permisoName, permisosUsuario, esSuperAdmin)) return false;
    if (permisoNoDelegablePorGerente(permisoName, esSuperAdmin)) return false;
    return true;
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
