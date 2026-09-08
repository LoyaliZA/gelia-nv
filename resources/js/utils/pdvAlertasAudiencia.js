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
