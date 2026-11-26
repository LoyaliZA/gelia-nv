/** Contrato para abrir el modal de Ajustes desde cualquier punto de la app (sidebar, perfil, URL). */
export const AJUSTES_OPEN_EVENT = 'gelia-ajustes-open';
export const AJUSTES_TABS = ['apariencia', 'alertas', 'navegacion'];
export const AJUSTES_TAB_DEFAULT = 'apariencia';
export const AJUSTES_QUERY_PARAM = 'ajustes';

export function normalizarTabAjustes(tab) {
    return AJUSTES_TABS.includes(tab) ? tab : AJUSTES_TAB_DEFAULT;
}

export function abrirAjustes(tab) {
    if (typeof window === 'undefined') return;
    window.dispatchEvent(new CustomEvent(AJUSTES_OPEN_EVENT, { detail: { tab: normalizarTabAjustes(tab) } }));
}
