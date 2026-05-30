export const ALERTAS_PREFS_STORAGE_KEY = 'alertas_prefs';

export const ALERTAS_TIPOS = {
    nueva: 'Nueva solicitud',
    reparada: 'Solicitud reparada',
    rechazada: 'Error reportado',
    pago_rechazado: 'Pago vencido',
    pago_confirmado: 'Pago confirmado',
    actualizacion: 'Actualización',
    alerta_pago_insuficiente: 'Pago insuficiente',
    alerta_ascenso_lista: 'Ascenso de lista',
    consulta_nueva: 'Consulta TAG/Lista',
    consulta_respondida: 'Consulta respondida',
    rollback_confirmado: 'Reversión confirmada',
    cancelacion_solicitada: 'Cancelación solicitada',
    cancelada: 'Solicitud cancelada',
    resumen_vencidos: 'Reporte de pagos vencidos',
    activo_asignado: 'Activo asignado',
    activo_devuelto: 'Activo devuelto',
    activo_transferido: 'Activo transferido',
    activo_mantenimiento: 'Mantenimiento de activo',
    activo_baja: 'Activo dado de baja',
    activo_vencimiento: 'Vencimiento de activo',
    activo_mantenimiento_proximo: 'Mantenimiento próximo',
    resumen_activos: 'Resumen de activos',
    mensaje_nuevo: 'Mensaje interno',
};

export const MENSAJERIA_TIPO_ALERTA = 'mensaje_nuevo';

/** Modos de texto a voz exclusivos para mensajería interna */
export const MENSAJERIA_VOZ_MODOS = {
    desactivado: 'Desactivado',
    solo_aviso: 'Solo avisar nuevo mensaje',
    leer_mensaje: 'Avisar y leer el mensaje',
};

export const DEFAULT_MENSAJERIA_VOZ = 'solo_aviso';

export const DEFAULT_ALERTAS_PREFS = {
    canales: {
        sonido: true,
        voz: true,
        escritorio: true,
        app: true,
    },
    tono_id: 'default',
    mensajeria_voz: DEFAULT_MENSAJERIA_VOZ,
    tipos: Object.fromEntries(Object.keys(ALERTAS_TIPOS).map((k) => [k, true])),
};

function mergePrefs(stored) {
    if (!stored || typeof stored !== 'object') return { ...DEFAULT_ALERTAS_PREFS };

    const canales = {
        ...DEFAULT_ALERTAS_PREFS.canales,
        ...(stored.canales || {}),
    };
    Object.keys(DEFAULT_ALERTAS_PREFS.canales).forEach((canal) => {
        canales[canal] = canales[canal] !== false;
    });

    const tipos = {
        ...DEFAULT_ALERTAS_PREFS.tipos,
        ...(stored.tipos || {}),
    };
    Object.keys(DEFAULT_ALERTAS_PREFS.tipos).forEach((tipo) => {
        tipos[tipo] = tipos[tipo] !== false;
    });

    return {
        canales,
        tono_id: stored.tono_id || DEFAULT_ALERTAS_PREFS.tono_id,
        mensajeria_voz: MENSAJERIA_VOZ_MODOS[stored.mensajeria_voz]
            ? stored.mensajeria_voz
            : DEFAULT_MENSAJERIA_VOZ,
        tipos,
    };
}

export function mergeAlertasPrefs(stored) {
    return mergePrefs(stored);
}

export function readStoredAlertasPrefs(temaVisual = {}) {
    if (typeof window !== 'undefined') {
        try {
            const raw = localStorage.getItem(ALERTAS_PREFS_STORAGE_KEY);
            if (raw) return mergePrefs(JSON.parse(raw));
        } catch {
            /* ignore */
        }
    }

    return mergePrefs(temaVisual?.alertas_prefs);
}

export function persistAlertasPrefsToStorage(prefs) {
    if (typeof window === 'undefined') return;
    localStorage.setItem(ALERTAS_PREFS_STORAGE_KEY, JSON.stringify(prefs));
    window.dispatchEvent(new CustomEvent('alertas-prefs-changed', { detail: prefs }));
}

export function resolveAlertasPrefs(source = {}) {
    const temaVisual = source?.tema_visual ?? source;
    return readStoredAlertasPrefs(temaVisual);
}

export function getTipoAlerta(notification = {}) {
    if (notification?.total_vencidos !== undefined) return 'resumen_vencidos';
    if (notification?.data?.total_vencidos !== undefined) return 'resumen_vencidos';
    if (notification?.total_activos !== undefined) return 'resumen_activos';
    if (notification?.data?.total_activos !== undefined) return 'resumen_activos';

    const tipoExplicito = notification?.tipo || notification?.data?.tipo;
    if (tipoExplicito) return tipoExplicito;

    // Laravel envía `type` como nombre de clase PHP; no usarlo como clave de preferencias.
    return 'actualizacion';
}

export function isTipoAlertaEnabled(prefs, tipo) {
    const merged = mergePrefs(prefs);
    if (merged.tipos[tipo] === false) return false;
    return true;
}

export function shouldTriggerChannel(prefs, tipo, canal) {
    const merged = mergePrefs(prefs);
    if (!isTipoAlertaEnabled(merged, tipo)) return false;
    if (merged.canales[canal] === false) return false;
    return true;
}

export function resolveTonoPath(tonosAlertas = [], tonoId = 'default') {
    const tono = tonosAlertas.find((t) => t.id === tonoId) || tonosAlertas[0];
    return tono?.path || '/assets/sounds/notification.mp3';
}

export function resolveMensajeriaVozModo(prefs) {
    const merged = mergePrefs(prefs);
    return MENSAJERIA_VOZ_MODOS[merged.mensajeria_voz]
        ? merged.mensajeria_voz
        : DEFAULT_MENSAJERIA_VOZ;
}

export function construirMensajeVozMensajeria(mensaje, prefs, nombreDestinatario = '') {
    const nombre = nombreDestinatario?.trim() || 'Usuario';
    const modo = resolveMensajeriaVozModo(prefs);

    if (modo === 'desactivado') return null;

    const aviso = `${nombre}, tienes un nuevo mensaje`;

    if (modo === 'solo_aviso') {
        return aviso;
    }

    const preview = mensaje?.contenido?.trim()
        || ({
            imagen: 'Te envió una imagen',
            video: 'Te envió un video',
            audio: 'Te envió un audio',
            archivo: 'Te envió un archivo',
        }[mensaje?.tipo] || '');

    if (!preview) return aviso;

    return `${aviso}. ${preview}`.slice(0, 220);
}

export function debeUsarVozMensajeria(prefs) {
    return resolveMensajeriaVozModo(prefs) !== 'desactivado';
}
