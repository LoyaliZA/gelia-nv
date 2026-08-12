export const TN_IMAGE_IMPORT_STORAGE_KEY = 'gelia_tiendanube_image_import_id';
export const TN_IMAGE_IMPORT_POS_KEY = 'gelia_tiendanube_image_import_pos';

export const TN_IMAGE_IMPORT_STARTED_EVENT = 'tiendanube-image-import-started';
export const TN_IMAGE_IMPORT_CLEARED_EVENT = 'tiendanube-image-import-cleared';
export const TN_IMAGE_IMPORT_DISMISSED_EVENT = 'tiendanube-image-import-dismissed';

export function startTiendanubeImageImportTracking(importId) {
    if (typeof window === 'undefined' || !importId) return;
    const next = String(importId);
    const prev = localStorage.getItem(TN_IMAGE_IMPORT_STORAGE_KEY);
    localStorage.setItem(TN_IMAGE_IMPORT_STORAGE_KEY, next);
    // Evita bucle: el widget escucha STARTED y volvía a llamar start → stack overflow.
    if (prev === next) return;
    window.dispatchEvent(new CustomEvent(TN_IMAGE_IMPORT_STARTED_EVENT, { detail: { importId: Number(importId) } }));
}

export function clearTiendanubeImageImportTracking() {
    if (typeof window === 'undefined') return;
    localStorage.removeItem(TN_IMAGE_IMPORT_STORAGE_KEY);
    window.dispatchEvent(new CustomEvent(TN_IMAGE_IMPORT_CLEARED_EVENT));
}

export function dismissTiendanubeImageImportTracking() {
    clearTiendanubeImageImportTracking();
    window.dispatchEvent(new CustomEvent(TN_IMAGE_IMPORT_DISMISSED_EVENT));
}

export function getStoredTiendanubeImageImportId() {
    if (typeof window === 'undefined') return null;
    const raw = localStorage.getItem(TN_IMAGE_IMPORT_STORAGE_KEY);
    return raw ? Number(raw) : null;
}

export function getStoredTiendanubeImportWidgetPos() {
    if (typeof window === 'undefined') return null;
    try {
        const raw = localStorage.getItem(TN_IMAGE_IMPORT_POS_KEY);
        if (!raw) return null;
        const pos = JSON.parse(raw);
        if (typeof pos?.x === 'number' && typeof pos?.y === 'number') {
            return {
                x: pos.x,
                y: pos.y,
                dock: pos.dock === 'left' || pos.dock === 'right' ? pos.dock : null,
            };
        }
    } catch {
        // ignore
    }
    return null;
}

export function setStoredTiendanubeImportWidgetPos(pos) {
    if (typeof window === 'undefined' || !pos) return;
    localStorage.setItem(
        TN_IMAGE_IMPORT_POS_KEY,
        JSON.stringify({
            x: pos.x,
            y: pos.y,
            dock: pos.dock === 'left' || pos.dock === 'right' ? pos.dock : null,
        })
    );
}

export const ESTADOS_ACTIVOS = ['pendiente', 'en_proceso'];
export const ESTADOS_TERMINALES = ['completado', 'error'];
