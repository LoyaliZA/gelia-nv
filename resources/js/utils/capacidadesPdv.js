const PERMISO_ALTA = 'pdv.turnos.alta';
const PERMISO_ATENDER = 'pdv.turnos.atender';
const PERMISO_TRANSFERIR = 'pdv.turnos.transferir';
const PERMISO_BAJA_COLA = 'pdv.turnos.baja_cola';
const PERMISO_TURNOS_VER = 'pdv.turnos.ver';
const PERMISO_RESGUARDOS_VER = 'pdv.resguardos.ver';
const PERMISO_ALCANCE_GLOBAL = 'pdv.alcance.global';

const EXCEPCIONES_PDV = [
    'pdv.resguardos.autorizar_entrega_incidencia',
    'pdv.resguardos.reponer_vencido',
    PERMISO_ALCANCE_GLOBAL,
];

function tienePermiso(permisos, nombre) {
    return (permisos || []).includes(nombre);
}

function resolverSucursalesAsignadas(sucursalesCatalogo, sucursalIds) {
    const ids = new Set((sucursalIds || []).map((id) => Number(id)));
    return (sucursalesCatalogo || []).filter((s) => ids.has(Number(s.id)));
}

function resolverSucursalPrincipal(sucursalesAsignadas, sucursalPrincipalId) {
    if (!sucursalesAsignadas.length) {
        return null;
    }

    const principalId = sucursalPrincipalId !== '' && sucursalPrincipalId != null
        ? Number(sucursalPrincipalId)
        : null;

    if (principalId) {
        const encontrada = sucursalesAsignadas.find((s) => Number(s.id) === principalId);
        if (encontrada) {
            return encontrada;
        }
    }

    if (sucursalesAsignadas.length === 1) {
        return sucursalesAsignadas[0];
    }

    return sucursalesAsignadas.find((s) => s.es_principal) ?? null;
}

/**
 * @param {{
 *   permisos?: string[],
 *   sucursalesCatalogo?: Array<{ id: number|string, nombre: string, es_principal?: boolean }>,
 *   sucursalIds?: Array<number|string>,
 *   sucursalPrincipalId?: number|string|null,
 * }} params
 */
export function calcularCapacidadesPdv({
    permisos = [],
    sucursalesCatalogo = [],
    sucursalIds = [],
    sucursalPrincipalId = null,
} = {}) {
    const sucursalesAsignadas = resolverSucursalesAsignadas(sucursalesCatalogo, sucursalIds);
    const sucursalPrincipal = resolverSucursalPrincipal(sucursalesAsignadas, sucursalPrincipalId);
    const tieneSucursal = sucursalesAsignadas.length > 0;
    const puedeAtender = tienePermiso(permisos, PERMISO_ATENDER);
    const puedeConsultarReportes = tienePermiso(permisos, PERMISO_TURNOS_VER)
        || (tienePermiso(permisos, PERMISO_RESGUARDOS_VER) && tienePermiso(permisos, PERMISO_ALCANCE_GLOBAL));
    const puedeAdministrarExcepciones = EXCEPCIONES_PDV.some((p) => tienePermiso(permisos, p));

    return {
        sucursalesAsignadas: sucursalesAsignadas.map((s) => s.nombre),
        sucursalPrincipal: sucursalPrincipal?.nombre ?? null,
        puedeRegistrarTurnos: tienePermiso(permisos, PERMISO_ALTA),
        puedeAtenderTurnos: puedeAtender,
        apareceEnGestionVendedores: puedeAtender && tieneSucursal,
        notaAtenderSinSucursal: puedeAtender && !tieneSucursal,
        puedeGestionarVendedores: tienePermiso(permisos, PERMISO_TRANSFERIR)
            || tienePermiso(permisos, PERMISO_BAJA_COLA),
        puedeConsultarReportes,
        puedeAdministrarExcepciones,
    };
}

export {
    PERMISO_ALTA,
    PERMISO_ATENDER,
    PERMISO_TRANSFERIR,
    PERMISO_BAJA_COLA,
    EXCEPCIONES_PDV,
};
