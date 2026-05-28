import { useMemo } from 'react';

export const INPUT_CLASS = 'w-full rounded-xl px-3 py-2 theme-element theme-text-main theme-placeholder border theme-border text-sm font-bold outline-none focus:ring-2';
export const SELECT_CLASS = INPUT_CLASS + ' appearance-none cursor-pointer';
export const TEXTAREA_CLASS = INPUT_CLASS + ' resize-none';
export const LABEL_CLASS = 'block text-[10px] font-black uppercase tracking-widest theme-text-muted mb-1';
export const BTN_ICON_CLASS = 'p-2 rounded-full theme-text-muted hover:theme-text-main hover:bg-black/5 dark:hover:bg-white/5 outline-none';
export const MODAL_OVERLAY_CLASS = 'fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm';
export const MODAL_SHELL_CLASS = 'theme-surface rounded-[2.5rem] border theme-border shadow-2xl w-full max-h-[90vh] overflow-y-auto';
export const BTN_PRIMARY_CLASS = 'flex items-center gap-2 px-5 py-2 rounded-xl text-sm font-black uppercase text-white outline-none disabled:opacity-50';
export const BTN_SECONDARY_CLASS = 'px-5 py-2 rounded-xl text-sm font-black uppercase theme-text-muted hover:theme-text-main outline-none';

export const ESTADO_BADGE = {
    disponible: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
    asignado: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
    mantenimiento: 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300',
    baja: 'bg-zinc-200 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400',
};

export const ESTADO_LABELS = {
    disponible: 'Disponible',
    asignado: 'Asignado',
    mantenimiento: 'Mantenimiento',
    baja: 'Baja',
};

export const ESTILOS_PAGINA = `
    @keyframes slideUpFade { 0% { opacity: 0; transform: translateY(20px); } 100% { opacity: 1; transform: translateY(0); } }
    .animate-page-reveal { opacity: 0; animation: slideUpFade 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
    .paginacion-btn { display: flex; align-items: center; justify-content: center; width: 2.5rem; height: 2.5rem; border-radius: 0.75rem; font-size: 0.75rem; font-weight: 900; border: 1px solid; transition: all 0.15s; cursor: pointer; }
    .paginacion-btn:disabled { opacity: 0.3; cursor: not-allowed; }
`;

export const GLASS_CARD_CLASS = 'bg-white/75 dark:bg-[#121212]/75 backdrop-blur-[24px] border-[1.5px] border-white/80 dark:border-zinc-700/60 shadow-[0_12px_40px_rgba(0,0,0,0.12)] dark:shadow-[0_12px_40px_rgba(0,0,0,0.6)]';
export const SOLID_CARD_CLASS = 'bg-white dark:bg-[#121212] border theme-border shadow-sm';

export function isGlassEffectEnabled() {
    if (typeof window === 'undefined') return true;
    return localStorage.getItem('theme_glass') !== 'false';
}

export function getActivosCardClass({ glass = isGlassEffectEnabled(), extra = '' } = {}) {
    const base = `animate-page-reveal theme-surface rounded-[2.5rem] border theme-border shadow-xl ${extra}`.trim();
    return glass ? `${base} ${GLASS_CARD_CLASS}` : `${base} ${SOLID_CARD_CLASS}`;
}

export function useActivosCardClass(extra = '') {
    return useMemo(() => getActivosCardClass({ extra }), [extra]);
}
