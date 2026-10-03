import { mensajeTtsLlamadoPdv, mensajeTtsProrrogaPdv, primerNombreAtencionPdv } from './pdvSpeechUtils';

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
        guion: '{vendedor}, tienes un nuevo cliente: {cliente}.',
    },
    'turno.reatencion': {
        prioridad: 'alta',
        tonoConfigurable: false,
        guion: '{vendedor}, tienes un nuevo cliente: {cliente}.',
    },
    'turno.transferido': {
        prioridad: 'alta',
        tonoConfigurable: false,
        guion: '{vendedor}, tienes un nuevo cliente: {cliente}.',
    },
    'atencion.espera_proximo_vencer': {
        prioridad: 'critica',
        tonoConfigurable: false,
        guion: 'Turno {folio} próximo a vencer',
    },
    'atencion.prorroga': {
        prioridad: 'alta',
        tonoConfigurable: true,
        guion: 'Prórroga iniciada. Turno {folio}.',
    },
    'atencion.prorroga_proximo_vencer': {
        prioridad: 'critica',
        tonoConfigurable: false,
        guion: 'Prórroga del turno {folio} próxima a vencer',
    },
    'pausa.iniciada': {
        prioridad: 'alta',
        tonoConfigurable: false,
        guion: 'Pausa activa. {vendedor}.',
    },
    'resguardo.recepcion_esperada_creada': {
        prioridad: 'alta',
        tonoConfigurable: false,
        guion: 'Nuevo resguardo pendiente de aprobación. {referencia}.',
    },
    'resguardo.registro_manual_creado': {
        prioridad: 'alta',
        tonoConfigurable: false,
        guion: 'Nuevo resguardo pendiente de aprobación. {referencia}.',
    },
};

export const PDV_TIPOS_AUDIO_PERSONAL = new Set([
    'turno.asignado',
    'turno.reatencion',
    'turno.transferido',
    'atencion.prorroga',
]);

const PDV_TIPOS_LLAMADO = new Set([
    'turno.asignado',
    'turno.reatencion',
    'turno.transferido',
]);

const PDV_TIPOS_RESGUARDO_NUEVO = new Set([
    'resguardo.recepcion_esperada_creada',
    'resguardo.registro_manual_creado',
]);

export const PDV_TIPOS_AUDIO_TERMINAL = new Set(Object.keys(PDV_ALERTAS_CATALOGO_TURNOS));

export function prioridadNumericaPdv(prioridad) {
    return PDV_ALERTA_PRIORIDAD[prioridad] ?? PDV_ALERTA_PRIORIDAD.normal;
}

export function definicionAlertaPdv(tipo) {
    return PDV_ALERTAS_CATALOGO_TURNOS[String(tipo || '')] ?? null;
}

export function primerNombreDesdeDatosPdv(datos) {
    return primerNombreAtencionPdv(datos);
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

    if (PDV_TIPOS_LLAMADO.has(tipo)) {
        return mensajeTtsLlamadoPdv(datos, { publico: false });
    }

    if (tipo === 'atencion.prorroga') {
        return mensajeTtsProrrogaPdv(datos);
    }

    if (tipo === 'pausa.iniciada') {
        const vendedor = primerNombreDesdeDatosPdv(datos);
        return vendedor ? `Pausa activa. ${vendedor}.` : 'Pausa activa.';
    }

    if (PDV_TIPOS_RESGUARDO_NUEVO.has(tipo)) {
        const referencia = String(datos.folio || datos.snapshot_cliente_nombre || '').trim();
        if (!referencia) return null;
        return `Nuevo resguardo pendiente de aprobación. ${referencia}.`;
    }

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
