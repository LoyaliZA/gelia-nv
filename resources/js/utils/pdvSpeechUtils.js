/** Llamados que comparten el mismo guion breve (sin decir reatención). */
export const PDV_TTS_TIPOS = new Set([
    'turno.asignado',
    'turno.reatencion',
    'turno.transferido',
    'atencion.prorroga',
]);

export const PDV_TTS_VOZ_PREFERIDA = 'es-MX';

export const PDV_TTS_RATE = 0.95;

export const PDV_TTS_STORAGE_SILENCIO = 'pdv_terminal_tts_silenciado';

const IDIOMAS_LATAM = new Set(['es-MX', 'es-US', 'es-419']);

const VOCES_PREFERIDAS = [
    'Microsoft Renata Online (Natural) - Spanish (Mexico)',
    'Microsoft Dalia Online (Natural) - Spanish (Mexico)',
    'Microsoft Sabina Online (Natural) - Spanish (Mexico)',
    'Microsoft Renata - Spanish (Mexico)',
    'Microsoft Dalia - Spanish (Mexico)',
    'Microsoft Sabina - Spanish (Mexico)',
    'Paulina',
    'Monica',
    'Mónica',
    'Spanish (Mexico) Female',
    'Google español de Estados Unidos',
];

const PATRON_ROBOTICA = /espeak|festival|android|compact/i;
const PATRON_MASCULINA = /\b(jorge|raul|raúl|diego|juan|carlos|male|masculin)\b/i;

export const PDV_TTS_ESTADO = {
    listo: 'listo',
    bloqueado: 'bloqueado',
    silenciado: 'silenciado',
    no_soportado: 'no_soportado',
    sin_voz: 'sin_voz',
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
    if (estadoTts === PDV_TTS_ESTADO.sin_voz) {
        return {
            titulo: 'Voz no disponible',
            etiqueta: 'Este equipo no tiene una voz femenina en español latinoamericano.',
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
 * Frases cortas de prioridad. La sala pública solo dice prioridad de Diamante.
 */
export function frasesPrioridadTtsPdv(datos, { publico = false } = {}) {
    if (!datos || typeof datos !== 'object') return [];

    const frases = [];
    if (datos.prioridad_diamante === true) {
        frases.push('Tiene prioridad.');
    }
    if (publico) return frases;

    if (datos.prioridad_discapacidad === true) {
        frases.push('Cliente con discapacidad.');
    }
    if (datos.prioridad_adulto_mayor === true) {
        frases.push('Cliente de la tercera edad.');
    }
    if (datos.prioridad_vip === true) {
        frases.push('Cliente VIP.');
    }

    return frases;
}

/**
 * Guion de llamado: Turno {folio}. {cliente}. [prioridad]. Pase con {vendedor}.
 * No dice reatención ni la palabra lista.
 */
export function mensajeTtsLlamadoPdv(datos, { publico = false } = {}) {
    if (!datos || typeof datos !== 'object') return null;

    const folio = String(datos.folio || '').trim();
    const nombreCliente = String(datos.snapshot_nombre_llamado || '').trim();
    if (!folio || !nombreCliente) return null;

    const vendedor = primerNombreAtencionPdv(datos) || 'el vendedor asignado';
    const partes = [
        `Turno ${folio}.`,
        `${nombreCliente}.`,
        ...frasesPrioridadTtsPdv(datos, { publico }),
        `Pase con ${vendedor}.`,
    ];

    return partes.join(' ');
}

export function mensajeTtsProrrogaPdv(datos) {
    const folio = String(datos?.folio || '').trim();
    if (!folio) return null;
    return `Prórroga iniciada. Turno ${folio}.`;
}

export function mensajeTtsPersonalPdv(envelope) {
    const datos = envelope?.datos;
    if (!datos || typeof datos !== 'object') return null;

    const tipo = String(envelope?.tipo || '');
    if (tipo === 'atencion.prorroga') {
        return mensajeTtsProrrogaPdv(datos);
    }

    if (tipo && !PDV_TTS_TIPOS.has(tipo)) return null;

    return mensajeTtsLlamadoPdv(datos, { publico: envelope?.audiencia === 'publico' });
}

/** @deprecated usar mensajeTtsPersonalPdv */
export function mensajeTtsPdv(envelope) {
    return mensajeTtsPersonalPdv(envelope);
}

export function primerNombreAtencionPdv(datos) {
    const candidatos = [
        datos?.atencion?.primer_nombre,
        datos?.atencion_primer_nombre,
        datos?.primer_nombre,
        datos?.jornada?.primer_nombre,
    ];

    for (const candidato of candidatos) {
        const nombre = String(candidato || '').trim();
        if (nombre) return nombre;
    }

    return null;
}

export function vozDescartadaPdv(voz) {
    const nombre = String(voz?.name || '');
    const lang = String(voz?.lang || '');
    if (!lang.startsWith('es')) return true;
    if (PATRON_ROBOTICA.test(nombre)) return true;
    if (PATRON_MASCULINA.test(nombre)) return true;
    return false;
}

export function seleccionarVozPdv(voces = []) {
    if (!Array.isArray(voces) || voces.length === 0) return null;

    const candidatas = voces.filter((voz) => !vozDescartadaPdv(voz));
    if (candidatas.length === 0) return null;

    for (const nombre of VOCES_PREFERIDAS) {
        const coincidencia = candidatas.find((voz) => voz.name === nombre);
        if (coincidencia) return coincidencia;
    }

    return candidatas.find((voz) => IDIOMAS_LATAM.has(String(voz.lang || ''))) ?? null;
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
