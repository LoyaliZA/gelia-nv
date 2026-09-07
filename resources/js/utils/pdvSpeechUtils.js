/** Eventos con guion TTS aprobado (CONTRATO_PANTALLAS_PDV §5–§7). */
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

export function debeAnunciarTtsPdv(envelope) {
    const tipo = String(envelope?.tipo || '');
    if (!PDV_TTS_TIPOS.has(tipo)) return false;
    return mensajeTtsPdv(envelope) !== null;
}

/**
 * Guion neutro: Turno {folio}. {nombre}. Favor de pasar con {primer nombre}.
 * No nombra VIP, Diamante ni categorías (CONTRATO_PANTALLAS_PDV §5).
 */
export function mensajeTtsPdv(envelope) {
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
