/** Prioridades de alertas audibles (alineado con CatalogoAlertasTurnosPdv). */
export const PDV_ALERTA_PRIORIDAD = {
    critica: 0,
    alta: 1,
    normal: 2,
};

/**
 * Catálogo central de tipos audibles de turnos para terminal de sucursal.
 * Los guiones viven aquí; no duplicar en componentes.
 */
export const PDV_ALERTAS_CATALOGO_TURNOS = {
    'turno.alta': {
        prioridad: 'normal',
        tonoConfigurable: true,
        guion: 'Nuevo turno {folio}',
        guionVip: 'Nuevo turno VIP, {folio}',
        guionDiamante: 'Nuevo turno Diamante, {folio}',
    },
    'turno.asignado': {
        prioridad: 'alta',
        tonoConfigurable: false,
        guion: 'Turno {folio}, pasar con {vendedor}',
    },
    'turno.reatencion': {
        prioridad: 'alta',
        tonoConfigurable: false,
        guion: 'Re-atención {folio}, pasar con {vendedor}',
    },
    'turno.transferido': {
        prioridad: 'alta',
        tonoConfigurable: false,
        guion: 'Turno {folio} transferido a {vendedor}',
    },
    'atencion.espera_proximo_vencer': {
        prioridad: 'critica',
        tonoConfigurable: false,
        guion: 'Turno {folio} próximo a vencer',
    },
    'atencion.prorroga': {
        prioridad: 'alta',
        tonoConfigurable: true,
        guion: 'Turno {folio} en prórroga',
    },
    'atencion.prorroga_proximo_vencer': {
        prioridad: 'critica',
        tonoConfigurable: false,
        guion: 'Prórroga del turno {folio} próxima a vencer',
    },
};

export const PDV_TIPOS_AUDIO_PERSONAL = new Set([
    'turno.asignado',
    'turno.reatencion',
    'turno.transferido',
]);

export const PDV_TIPOS_AUDIO_TERMINAL = new Set(Object.keys(PDV_ALERTAS_CATALOGO_TURNOS));

export function prioridadNumericaPdv(prioridad) {
    return PDV_ALERTA_PRIORIDAD[prioridad] ?? PDV_ALERTA_PRIORIDAD.normal;
}

export function definicionAlertaPdv(tipo) {
    return PDV_ALERTAS_CATALOGO_TURNOS[String(tipo || '')] ?? null;
}

export function primerNombreDesdeDatosPdv(datos) {
    const desdeAtencion = datos?.atencion?.primer_nombre;
    if (desdeAtencion) return String(desdeAtencion).trim() || null;
    const desdePublico = datos?.atencion_primer_nombre;
    if (desdePublico) return String(desdePublico).trim() || null;
    return null;
}

function clasificacionAltaPdv(datos) {
    if (datos?.prioridad_diamante === true) return 'diamante';
    if (datos?.prioridad_vip === true) return 'vip';
    return null;
}

/**
 * Guion de terminal con clasificación operativa autorizada (VIP/Diamante en alta).
 */
export function mensajeTtsTerminalPdv(envelope) {
    const tipo = String(envelope?.tipo || '');
    const definicion = definicionAlertaPdv(tipo);
    const datos = envelope?.datos;
    if (!definicion || !datos || typeof datos !== 'object') return null;

    const folio = String(datos.folio || '').trim();
    if (!folio) return null;

    if (tipo === 'turno.alta') {
        const clasificacion = clasificacionAltaPdv(datos);
        if (clasificacion === 'diamante') {
            return definicion.guionDiamante.replace('{folio}', folio);
        }
        if (clasificacion === 'vip') {
            return definicion.guionVip.replace('{folio}', folio);
        }
    }

    const vendedor = primerNombreDesdeDatosPdv(datos) || 'el vendedor asignado';
    return definicion.guion
        .replace('{folio}', folio)
        .replace('{vendedor}', vendedor);
}

export function prioridadAlertaPdv(envelope) {
    const definicion = definicionAlertaPdv(envelope?.tipo);
    return prioridadNumericaPdv(definicion?.prioridad ?? 'normal');
}

export function tonoConfigurableAlertaPdv(envelope) {
    const definicion = definicionAlertaPdv(envelope?.tipo);
    return definicion?.tonoConfigurable === true;
}
