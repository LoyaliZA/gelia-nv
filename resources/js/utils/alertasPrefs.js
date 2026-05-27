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
    resumen_vencidos: 'Reporte de pagos vencidos',
};

export const DEFAULT_ALERTAS_PREFS = {
    canales: {
        sonido: true,
        voz: true,
        escritorio: true,
        app: true,
    },
    tono_id: 'default',
    tipos: Object.fromEntries(Object.keys(ALERTAS_TIPOS).map((k) => [k, true])),
};

function mergePrefs(stored) {
    if (!stored || typeof stored !== 'object') return { ...DEFAULT_ALERTAS_PREFS };

    return {
        canales: {
            ...DEFAULT_ALERTAS_PREFS.canales,
            ...(stored.canales || {}),
        },
        tono_id: stored.tono_id || DEFAULT_ALERTAS_PREFS.tono_id,
        tipos: {
            ...DEFAULT_ALERTAS_PREFS.tipos,
            ...(stored.tipos || {}),
        },
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

export function resolveAlertasPrefs(auth = {}) {
    return readStoredAlertasPrefs(auth?.tema_visual);
}

export function getTipoAlerta(notification = {}) {
    if (notification?.total_vencidos !== undefined) return 'resumen_vencidos';
    if (notification?.data?.total_vencidos !== undefined) return 'resumen_vencidos';

    return (
        notification?.tipo
        || notification?.data?.tipo
        || notification?.type
        || 'actualizacion'
    );
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
