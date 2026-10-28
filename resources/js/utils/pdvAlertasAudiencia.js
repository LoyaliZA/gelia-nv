import {
    PDV_TIPOS_AUDIO_PERSONAL,
    PDV_TIPOS_AUDIO_TERMINAL,
    mensajeTtsTerminalPdv,
} from './pdvAlertasCatalog';
import { mensajeTtsPersonalPdv } from './pdvSpeechUtils';

export const PDV_AUDIENCIA_CANAL = {
    usuario: 'usuario',
    sucursal: 'sucursal',
    publico: 'publico',
};

/**
 * Eventos personales: canal usuario dirigido al vendedor asignado.
 */
export function esEventoPersonalPdv(envelope, userId = null) {
    if (envelope?.audiencia !== PDV_AUDIENCIA_CANAL.usuario) return false;
    const tipo = String(envelope?.tipo || '');
    if (!PDV_TIPOS_AUDIO_PERSONAL.has(tipo)) return false;
    if (userId == null) return true;
    const asignado = envelope?.datos?.atencion?.user_id;
    return Number(asignado) === Number(userId);
}

/**
 * Eventos audibles de sucursal: solo terminal activa y vigente.
 */
export function esEventoSucursalAudiblePdv(envelope, terminalActiva = false) {
    if (!terminalActiva) return false;
    if (envelope?.audiencia !== PDV_AUDIENCIA_CANAL.sucursal) return false;
    const tipo = String(envelope?.tipo || '');
    return PDV_TIPOS_AUDIO_TERMINAL.has(tipo);
}

/**
 * Toda audiencia sucursal refresca vistas; el audio se filtra aparte.
 */
export function esEventoSucursalVisualPdv(envelope) {
    return envelope?.audiencia === PDV_AUDIENCIA_CANAL.sucursal;
}

export function debeReproducirSonidoPersonalPdv(envelope, prefsUsuario, silencioTerminal, userId) {
    if (!esEventoPersonalPdv(envelope, userId)) return false;
    return prefsUsuario?.canales?.sonido === true && !silencioTerminal;
}

export function debeReproducirSonidoTerminalPdv(envelope, prefsUsuario, silencioTerminal, terminalActiva) {
    if (!esEventoSucursalAudiblePdv(envelope, terminalActiva)) return false;
    return prefsUsuario?.canales?.sonido === true && !silencioTerminal;
}

export function debeAnunciarVozPersonalPdv(envelope, prefsUsuario, silencioTerminal, userId) {
    if (!esEventoPersonalPdv(envelope, userId)) return false;
    if (!prefsUsuario?.canales?.voz || silencioTerminal) return false;
    return mensajeTtsPersonalPdv(envelope) !== null;
}

export function debeAnunciarVozTerminalPdv(envelope, prefsUsuario, silencioTerminal, terminalActiva) {
    if (!esEventoSucursalAudiblePdv(envelope, terminalActiva)) return false;
    if (!prefsUsuario?.canales?.voz || silencioTerminal) return false;
    return mensajeTtsTerminalPdv(envelope) !== null;
}

export function resolverModoAudioPdv(envelope, {
    userId = null,
    terminalActiva = false,
    prefsUsuario,
    silencioTerminal = false,
    audioYaAnunciado = false,
}) {
    if (audioYaAnunciado) return null;

    if (debeAnunciarVozPersonalPdv(envelope, prefsUsuario, silencioTerminal, userId)
        || debeReproducirSonidoPersonalPdv(envelope, prefsUsuario, silencioTerminal, userId)) {
        return 'personal';
    }

    if (debeAnunciarVozTerminalPdv(envelope, prefsUsuario, silencioTerminal, terminalActiva)
        || debeReproducirSonidoTerminalPdv(envelope, prefsUsuario, silencioTerminal, terminalActiva)) {
        return 'terminal';
    }

    return null;
}

const ESTADOS_ALERTAS_VIGENTES = new Set([
    'terminal_activa',
    'otra_terminal_activa',
    'conexion_perdida',
]);

/**
 * La designación de alertas sigue vigente aunque este navegador no sea quien la opera.
 * `designacionVigente` cubre a quien consulta el estado sin permiso de alertas:
 * la API deja la designación y el estado de UI en no autorizada.
 */
export function alertasDesignacionVigentePdv({
    estado = null,
    designacionVigente = false,
} = {}) {
    if (designacionVigente === true) return true;
    return ESTADOS_ALERTAS_VIGENTES.has(String(estado || ''));
}

/**
 * Alertas vigente: solo la pestaña líder de esa terminal.
 * Sin designación de alertas: la pestaña líder de la terminal general.
 */
export function terminalSucursalPuedeAnunciar({
    alertasLocalLider = false,
    generalLocalLider = false,
    estadoAlertas = null,
    designacionAlertasVigente = false,
} = {}) {
    if (alertasDesignacionVigentePdv({
        estado: estadoAlertas,
        designacionVigente: designacionAlertasVigente,
    })) {
        return Boolean(alertasLocalLider);
    }

    return Boolean(generalLocalLider);
}

/** El llamado del puesto anuncia este sobre. El provider no lo reclama. */
export function audioPersonalDelegadoPdv(envelope, userId = null) {
    return esEventoPersonalPdv(envelope, userId);
}

/**
 * Mismo llamado, canal de sucursal, en el navegador de quien atiende.
 * No se repite aquí; otra terminal de sucursal sí lo anuncia.
 */
export function esLlamadoPropioEnSucursalPdv(envelope, userId = null) {
    if (envelope?.audiencia !== PDV_AUDIENCIA_CANAL.sucursal) return false;
    if (!PDV_TIPOS_AUDIO_PERSONAL.has(String(envelope?.tipo || ''))) return false;
    if (userId == null) return false;
    const asignado = envelope?.datos?.atencion?.user_id;
    return Number(asignado) === Number(userId);
}

/**
 * Decisión del provider. No depende del orden entre canal usuario y sucursal.
 * `terminal` solo si este navegador debe anunciar sucursal y el texto o el tono aplican.
 */
export function anuncioTerminalEnNavegadorPdv(envelope, opciones = {}) {
    const userId = opciones.userId ?? null;
    if (opciones.audioYaAnunciado) return null;
    if (audioPersonalDelegadoPdv(envelope, userId)) return null;
    if (esLlamadoPropioEnSucursalPdv(envelope, userId)) return null;

    return resolverModoAudioPdv(envelope, opciones) === 'terminal' ? 'terminal' : null;
}
