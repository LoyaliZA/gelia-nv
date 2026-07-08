export const IMPORTACION_ALMACEN_STORAGE_KEY = 'gelia_importacion_almacen_log_id';

export const IMPORTACION_ALMACEN_STARTED_EVENT = 'importacion-almacen-started';
export const IMPORTACION_ALMACEN_CLEARED_EVENT = 'importacion-almacen-cleared';
export const IMPORTACION_ALMACEN_DISMISSED_EVENT = 'importacion-almacen-dismissed';
export const IMPORTACION_ALMACEN_COMPLETADA_EVENT = 'importacion-almacen-completada';

export function startImportacionAlmacenTracking(logId) {
    if (typeof window === 'undefined' || !logId) return;
    localStorage.setItem(IMPORTACION_ALMACEN_STORAGE_KEY, String(logId));
    window.dispatchEvent(new CustomEvent(IMPORTACION_ALMACEN_STARTED_EVENT, { detail: { logId: Number(logId) } }));
}

export function clearImportacionAlmacenTracking() {
    if (typeof window === 'undefined') return;
    localStorage.removeItem(IMPORTACION_ALMACEN_STORAGE_KEY);
    window.dispatchEvent(new CustomEvent(IMPORTACION_ALMACEN_CLEARED_EVENT));
}

export function dismissImportacionAlmacenTracking() {
    clearImportacionAlmacenTracking();
    window.dispatchEvent(new CustomEvent(IMPORTACION_ALMACEN_DISMISSED_EVENT));
}

export function getStoredImportacionAlmacenLogId() {
    if (typeof window === 'undefined') return null;
    const raw = localStorage.getItem(IMPORTACION_ALMACEN_STORAGE_KEY);
    return raw ? Number(raw) : null;
}

export const ESTADOS_ACTIVOS = ['pendiente', 'en_proceso'];
export const ESTADOS_TERMINALES = ['completado', 'error', 'cancelado', 'interrumpido'];
export const ESTADOS_REANUDABLES = ['interrumpido', 'error'];

export function etiquetaTipoImportacion(tipo) {
    if (tipo === 'productos') return 'Importación de productos';
    if (tipo === 'inventarios') return 'Importación de inventario';
    if (tipo === 'costos') return 'Importación de costos';
    return 'Importación de almacén';
}
