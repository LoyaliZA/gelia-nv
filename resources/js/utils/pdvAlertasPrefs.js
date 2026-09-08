import { resolveTonoPath } from './alertasPrefs';
import { PDV_TTS_TIPOS, mensajeTtsPersonalPdv } from './pdvSpeechUtils';
import { mensajeTtsTerminalPdv, definicionAlertaPdv } from './pdvAlertasCatalog';
import {
    debeReproducirSonidoPersonalPdv,
    debeReproducirSonidoTerminalPdv,
} from './pdvAlertasAudiencia';

export const PDV_ALERTAS_PREFS_EVENT = 'pdv-alertas-prefs-changed';

/** Silencio temporal de este terminal (misma clave que 6C). */
export const PDV_TERMINAL_SILENCIO_KEY = 'pdv_terminal_tts_silenciado';

export const PDV_SONIDO_TIPOS = new Set([
    'turno.asignado',
    'turno.reatencion',
    'turno.transferido',
]);

export const PDV_PUSH_ESTADO = {
    servidor_deshabilitado: 'servidor_deshabilitado',
    deshabilitado_usuario: 'deshabilitado_usuario',
    no_soportado: 'no_soportado',
    denegado: 'denegado',
    pendiente: 'pendiente',
    activo: 'activo',
};

export const DEFAULT_PDV_ALERTAS_PREFS = {
    canales: {
        sonido: true,
        voz: true,
        web_push: true,
    },
    tono_id: 'default',
};

function mergePrefsUsuario(stored) {
    if (!stored || typeof stored !== 'object') {
        return { ...DEFAULT_PDV_ALERTAS_PREFS, canales: { ...DEFAULT_PDV_ALERTAS_PREFS.canales } };
    }

    const canales = {
        ...DEFAULT_PDV_ALERTAS_PREFS.canales,
        ...(stored.canales || {}),
    };

    Object.keys(DEFAULT_PDV_ALERTAS_PREFS.canales).forEach((canal) => {
        canales[canal] = canales[canal] !== false;
    });

    return {
        canales,
        tono_id: stored.tono_id || DEFAULT_PDV_ALERTAS_PREFS.tono_id,
    };
}

export function mergePdvAlertasPrefsUsuario(stored) {
    return mergePrefsUsuario(stored);
}

export function resolvePdvAlertasPrefsUsuario(temaVisual = {}) {
    return mergePrefsUsuario(temaVisual?.pdv_alertas_prefs);
}

export function leerSilencioTerminalPdv() {
    if (typeof window === 'undefined') return false;
    try {
        return window.localStorage.getItem(PDV_TERMINAL_SILENCIO_KEY) === '1';
    } catch {
        return false;
    }
}

export function guardarSilencioTerminalPdv(silenciado) {
    if (typeof window === 'undefined') return;
    try {
        window.localStorage.setItem(PDV_TERMINAL_SILENCIO_KEY, silenciado ? '1' : '0');
        window.dispatchEvent(new CustomEvent(PDV_ALERTAS_PREFS_EVENT, {
            detail: { silencioTerminal: silenciado },
        }));
    } catch {
        // ponytail: sin persistencia si el almacenamiento está bloqueado
    }
}

export function canalesEfectivosPdv(prefsUsuario, silencioTerminal = false) {
    const base = mergePrefsUsuario(prefsUsuario);

    if (silencioTerminal) {
        return {
            sonido: false,
            voz: false,
            web_push: base.canales.web_push !== false,
        };
    }

    return { ...base.canales };
}

export function debeReproducirSonidoPdv(envelope, prefsUsuario, silencioTerminal = false, opciones = {}) {
    const {
        userId = null,
        terminalActiva = false,
        modo = null,
    } = opciones;

    if (!canalesEfectivosPdv(prefsUsuario, silencioTerminal).sonido) {
        return false;
    }

    if (modo === 'personal') {
        return debeReproducirSonidoPersonalPdv(envelope, prefsUsuario, silencioTerminal, userId);
    }
    if (modo === 'terminal') {
        return debeReproducirSonidoTerminalPdv(envelope, prefsUsuario, silencioTerminal, terminalActiva);
    }

    const tipo = String(envelope?.tipo || '');
    if (!envelope?.audiencia && PDV_SONIDO_TIPOS.has(tipo)) {
        return true;
    }

    return debeReproducirSonidoPersonalPdv(envelope, prefsUsuario, silencioTerminal, userId)
        || debeReproducirSonidoTerminalPdv(envelope, prefsUsuario, silencioTerminal, terminalActiva);
}

export function debeAnunciarVozPdv(envelope, prefsUsuario, silencioTerminal = false, opciones = {}) {
    const {
        userId = null,
        terminalActiva = false,
        modo = null,
    } = opciones;

    if (!canalesEfectivosPdv(prefsUsuario, silencioTerminal).voz) return false;

    if (modo === 'personal') {
        const tipo = String(envelope?.tipo || '');
        if (!PDV_TTS_TIPOS.has(tipo)) return false;
        return mensajeTtsPersonalPdv(envelope) !== null;
    }

    if (modo === 'terminal') {
        return mensajeTtsTerminalPdv(envelope) !== null;
    }

    const tipo = String(envelope?.tipo || '');
    if (!envelope?.audiencia && PDV_TTS_TIPOS.has(tipo)) {
        return mensajeTtsPersonalPdv(envelope) !== null;
    }

    if (PDV_TTS_TIPOS.has(tipo) && envelope?.audiencia === 'usuario') {
        return mensajeTtsPersonalPdv(envelope) !== null;
    }

    if (envelope?.audiencia === 'sucursal' && terminalActiva) {
        return mensajeTtsTerminalPdv(envelope) !== null;
    }

    return false;
}

export function debeReproducirTonoEventoPdv(envelope, modo = 'personal') {
    if (modo === 'personal') {
        return PDV_SONIDO_TIPOS.has(String(envelope?.tipo || ''));
    }
    return definicionAlertaPdv(envelope?.tipo) !== null;
}

export function estadoWebPushPdv(prefsUsuario, webpush = {}) {
    const prefs = mergePrefsUsuario(prefsUsuario);

    if (!webpush?.enabled || !webpush?.public_key) {
        return PDV_PUSH_ESTADO.servidor_deshabilitado;
    }

    if (prefs.canales.web_push === false) {
        return PDV_PUSH_ESTADO.deshabilitado_usuario;
    }

    if (typeof window === 'undefined' || typeof Notification === 'undefined') {
        return PDV_PUSH_ESTADO.no_soportado;
    }

    if (Notification.permission === 'denied') {
        return PDV_PUSH_ESTADO.denegado;
    }

    if (Notification.permission === 'default') {
        return PDV_PUSH_ESTADO.pendiente;
    }

    return PDV_PUSH_ESTADO.activo;
}

export function mensajeFallbackWebPushPdv(estadoPush) {
    switch (estadoPush) {
        case PDV_PUSH_ESTADO.denegado:
            return 'Notificaciones push bloqueadas en este navegador. Las alertas visuales y en tiempo real continúan activas.';
        case PDV_PUSH_ESTADO.pendiente:
            return 'Activa las notificaciones push para recibir avisos con la pestaña en segundo plano.';
        case PDV_PUSH_ESTADO.no_soportado:
            return 'Este navegador no admite notificaciones push. Las alertas visuales continúan activas.';
        default:
            return null;
    }
}

export function reproducirTonoPdv(tonoId, tonosAlertas = []) {
    if (typeof window === 'undefined') return Promise.resolve(false);

    const path = resolveTonoPath(tonosAlertas, tonoId);
    const audio = new Audio(path);

    return audio.play()
        .then(() => true)
        .catch(() => false);
}

export function dispatchPdvAlertasPrefsChanged(prefs) {
    if (typeof window === 'undefined') return;
    window.dispatchEvent(new CustomEvent(PDV_ALERTAS_PREFS_EVENT, { detail: prefs }));
}
