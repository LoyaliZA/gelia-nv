/** Eventos con guion TTS personal aprobado (CONTRATO_PANTALLAS_PDV §5–§7). */
export const PDV_TTS_TIPOS = new Set([
    'turno.asignado',
    'turno.reatencion',
    'turno.transferido',
]);

export const PDV_TTS_VOZ_PREFERIDA = 'es-MX';

export const PDV_TTS_STORAGE_SILENCIO = 'pdv_terminal_tts_silenciado';

const VOCES_PREFERIDAS = [
    'Microsoft Renata Online (Natural) - Spanish (Mexico)',
    'Microsoft Dalia Online (Natural) - Spanish (Mexico)',
    'Microsoft Sabina Online (Natural) - Spanish (Mexico)',
    'Microsoft Renata - Spanish (Mexico)',
    'Microsoft Sabina - Spanish (Mexico)',
    'Paulina',
    'Monica',
    'Spanish (Mexico) Female',
    'Google español de Estados Unidos',
    'Google español',
];

export const PDV_TTS_ESTADO = {
    listo: 'listo',
    bloqueado: 'bloqueado',
    silenciado: 'silenciado',
    no_soportado: 'no_soportado',
};

export function resolverEstadoTtsPdv({ soportado, silenciado, audioDesbloqueado }) {
    if (!soportado) return PDV_TTS_ESTADO.no_soportado;
    if (silenciado) return PDV_TTS_ESTADO.silenciado;
    if (audioDesbloqueado) return PDV_TTS_ESTADO.listo;
    return PDV_TTS_ESTADO.bloqueado;
}

export function etiquetaAudioIndicadorPdv(estadoTts, { silenciado = false, canalesActivos = true } = {}) {
    if (!canalesActivos) {
        return { titulo: 'Audio desactivado', etiqueta: 'Audio desactivado en preferencias' };
    }
    if (silenciado) {
        return { titulo: 'Silenciado en este equipo', etiqueta: 'Terminal silenciado' };
    }
    if (estadoTts === PDV_TTS_ESTADO.listo) {
        return { titulo: 'Audio activo', etiqueta: 'Audio listo' };
    }
    if (estadoTts === PDV_TTS_ESTADO.bloqueado) {
        return {
            titulo: 'Toca para activar audio',
            etiqueta: 'Audio bloqueado por el navegador. Toca para activar.',
        };
    }
    if (estadoTts === PDV_TTS_ESTADO.no_soportado) {
        return {
            titulo: 'Audio no disponible',
            etiqueta: 'Este navegador no reproduce anuncios por voz',
        };
    }
    return { titulo: 'Audio', etiqueta: 'Estado de audio' };
}

export function debeAnunciarTtsPersonalPdv(envelope) {
    const tipo = String(envelope?.tipo || '');
    if (!PDV_TTS_TIPOS.has(tipo)) return false;
    return mensajeTtsPersonalPdv(envelope) !== null;
}

/** @deprecated usar debeAnunciarTtsPersonalPdv */
export function debeAnunciarTtsPdv(envelope) {
    return debeAnunciarTtsPersonalPdv(envelope);
}

/**
 * Guion neutro personal: Turno {folio}. {nombre}. Favor de pasar con {primer nombre}.
 * No nombra VIP, Diamante ni categorías (CONTRATO_PANTALLAS_PDV §5).
 */
export function mensajeTtsPersonalPdv(envelope) {
    const datos = envelope?.datos;
    if (!datos || typeof datos !== 'object') return null;

    const folio = String(datos.folio || '').trim();
    const nombreCliente = String(datos.snapshot_nombre_llamado || '').trim();
    if (!folio || !nombreCliente) return null;

    const primerNombre = primerNombreAtencionPdv(datos);
    const base = `Turno ${folio}. ${nombreCliente}.`;

    if (primerNombre) {
        return `${base} Favor de pasar con ${primerNombre}.`;
    }

    return `${base} Favor de atender.`;
}

/** @deprecated usar mensajeTtsPersonalPdv */
export function mensajeTtsPdv(envelope) {
    return mensajeTtsPersonalPdv(envelope);
}

export function primerNombreAtencionPdv(datos) {
    const desdeAtencion = datos?.atencion?.primer_nombre;
    if (desdeAtencion) return String(desdeAtencion).trim() || null;

    const desdePublico = datos?.atencion_primer_nombre;
    if (desdePublico) return String(desdePublico).trim() || null;

    return null;
}

export function seleccionarVozPdv(voces = [], preferida = PDV_TTS_VOZ_PREFERIDA) {
    if (!Array.isArray(voces) || voces.length === 0) return null;

    for (const nombre of VOCES_PREFERIDAS) {
        const coincidencia = voces.find((voz) => voz.name === nombre);
        if (coincidencia) return coincidencia;
    }

    const exacta = voces.find((voz) => voz.lang === preferida);
    if (exacta) return exacta;

    const espanol = voces.find((voz) => String(voz.lang || '').startsWith('es'));
    if (espanol) return espanol;

    return null;
}

export function leerSilencioTtsPdv() {
    if (typeof window === 'undefined') return false;
    try {
        return window.localStorage.getItem(PDV_TTS_STORAGE_SILENCIO) === '1';
    } catch {
        return false;
    }
}

export function guardarSilencioTtsPdv(silenciado) {
    if (typeof window === 'undefined') return;
    try {
        window.localStorage.setItem(PDV_TTS_STORAGE_SILENCIO, silenciado ? '1' : '0');
    } catch {
        // ponytail: sin persistencia si el almacenamiento está bloqueado
    }
}
