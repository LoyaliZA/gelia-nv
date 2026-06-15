export const WOO_SYNC_STORAGE_KEY = 'gelia_woocommerce_sync_log_id';

export const WOO_SYNC_STARTED_EVENT = 'woocommerce-sync-started';
export const WOO_SYNC_CLEARED_EVENT = 'woocommerce-sync-cleared';
export const WOO_SYNC_DISMISSED_EVENT = 'woocommerce-sync-dismissed';

export function startWooSyncTracking(logId) {
    if (typeof window === 'undefined' || !logId) return;
    localStorage.setItem(WOO_SYNC_STORAGE_KEY, String(logId));
    window.dispatchEvent(new CustomEvent(WOO_SYNC_STARTED_EVENT, { detail: { logId: Number(logId) } }));
}

export function clearWooSyncTracking() {
    if (typeof window === 'undefined') return;
    localStorage.removeItem(WOO_SYNC_STORAGE_KEY);
    window.dispatchEvent(new CustomEvent(WOO_SYNC_CLEARED_EVENT));
}

export function dismissWooSyncTracking() {
    clearWooSyncTracking();
    window.dispatchEvent(new CustomEvent(WOO_SYNC_DISMISSED_EVENT));
}

export function getStoredWooSyncLogId() {
    if (typeof window === 'undefined') return null;
    const raw = localStorage.getItem(WOO_SYNC_STORAGE_KEY);
    return raw ? Number(raw) : null;
}

export const ESTADOS_ACTIVOS = ['pendiente', 'en_proceso'];
export const ESTADOS_TERMINALES = ['completado', 'error', 'cancelado', 'interrumpido'];
export const ESTADOS_REANUDABLES = ['interrumpido', 'error'];

export function etiquetaTipoSync(tipo) {
    if (tipo === 'fetch_prices') return 'Descarga de precios';
    return 'Sincronización de precios';
}
