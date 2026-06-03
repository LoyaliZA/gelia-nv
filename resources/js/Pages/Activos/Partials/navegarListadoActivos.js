import { router } from '@inertiajs/react';

export const STORAGE_FILTROS_ACTIVOS = 'activos_filtros_guardados';

export function leerFiltrosActivosGuardados() {
    if (typeof window === 'undefined') return {};
    try {
        const raw = sessionStorage.getItem(STORAGE_FILTROS_ACTIVOS);
        if (!raw) return {};
        const parsed = JSON.parse(raw);
        return parsed && typeof parsed === 'object' ? parsed : {};
    } catch {
        return {};
    }
}

export function paramsFiltrosActivos(filtros = {}) {
    return Object.fromEntries(
        Object.entries(filtros).filter(([, v]) => v !== '' && v !== false && v !== null && v !== undefined)
    );
}

/** Una sola visita Inertia al listado, con filtros guardados y sin loader global. */
export function navegarListadoActivos(opciones = {}) {
    const params = paramsFiltrosActivos(leerFiltrosActivosGuardados());
    router.cancelAll();
    router.get(route('activos.index'), params, {
        preserveScroll: true,
        showProgress: false,
        ...opciones,
    });
}
